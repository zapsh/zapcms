<?php

namespace zapcms\services\storage;

use zap\facades\Cache;
use zapcms\services\Crypto;
use zapcms\services\Option;

/**
 * 敏感值的加密存取（云存储密钥、SMTP 密码、Redis 密码、第三方 API 密钥等）。
 *
 * 明文经 Crypto（app_key 派生密钥）加密后写入 options 表，
 * 键名形如 secret.<field>，autoload=0（不进全量加载）。
 * 读取时解密失败/未配置 app_key 返回 $default，由调用方回退到 config/env。
 *
 * 查询策略：任一字段首次读取时按前缀一次批量载入全部 secret.* 密文
 * （而非逐字段 SELECT），并做两级缓存：
 *  - 请求级：$rawMap（密文映射）+ $cache（解密后的明文），进程内零开销；
 *  - 持久级：Cache（默认 1 小时），set()/remove() 写库后立即失效。
 */
final class Secret
{
    private const PREFIX = 'secret.';

    /** REGEXP 匹配源（不带锚点，Option::getArray 会自动包成 ^(secret\.).*） */
    private const PREFIX_REGEX_SRC = 'secret\.';

    /** 持久缓存键：全部 secret.* 密文映射（field => cipher） */
    private const CACHE_KEY = '_opts_secret.';
    private const CACHE_TTL = 3600;

    /** @var array<string,string|null> 请求级明文缓存：field => 明文（null=未配置/解密失败） */
    private static array $cache = [];

    /** @var array<string,string>|null 请求级密文映射（field => cipher），null=尚未加载 */
    private static ?array $rawMap = null;

    public static function available(): bool
    {
        return Crypto::available();
    }

    /** 读取并解密；无值或解密失败返回 $default */
    public static function get(string $vendorField, $default = null)
    {
        // 命中请求级明文缓存
        if (array_key_exists($vendorField, self::$cache)) {
            return self::$cache[$vendorField] ?? $default;
        }

        $raw = self::rawMap()[$vendorField] ?? null;
        if ($raw === null || $raw === '') {
            self::$cache[$vendorField] = null;
            return $default;
        }

        $plain = Crypto::decrypt((string) $raw);
        self::$cache[$vendorField] = $plain;
        return $plain === null ? $default : $plain;
    }

    /**
     * 批量读取并解密（全部 secret.* 一次查询载入，不逐字段 SELECT）。
     *
     * @param string[] $vendorFields 字段名列表（不带 secret. 前缀）
     * @return array<string,string> field => 明文（仅含有值且解密成功的项）
     */
    public static function getMany(array $vendorFields): array
    {
        $raw = self::rawMap();
        $out = [];
        foreach ($vendorFields as $field) {
            $field = (string) $field;
            if ($field === '') {
                continue;
            }

            // 命中请求级明文缓存
            if (array_key_exists($field, self::$cache)) {
                if (self::$cache[$field] !== null) {
                    $out[$field] = self::$cache[$field];
                }
                continue;
            }

            $row = $raw[$field] ?? null;
            if ($row === null || $row === '') {
                self::$cache[$field] = null;
                continue;
            }

            $plain = Crypto::decrypt((string) $row);
            self::$cache[$field] = $plain;
            if ($plain !== null) {
                $out[$field] = $plain;
            }
        }
        return $out;
    }

    public static function set(string $vendorField, string $plain): void
    {
        $cipher = Crypto::encrypt($plain);
        Option::upsert(self::PREFIX . $vendorField, $cipher, 0, 0);

        // 同步请求级缓存；持久缓存整体失效（映射小，重载成本可忽略）
        if (self::$rawMap !== null) {
            self::$rawMap[$vendorField] = $cipher;
        }
        unset(self::$cache[$vendorField]);
        self::flushCache();
    }

    public static function remove(string $vendorField): void
    {
        Option::remove(self::PREFIX . $vendorField);

        unset(self::$rawMap[$vendorField]);
        unset(self::$cache[$vendorField]);
        self::flushCache();
    }

    /** 该字段是否已存有密文（用于后台显示「已配置」） */
    public static function configured(string $vendorField): bool
    {
        $raw = self::rawMap()[$vendorField] ?? null;
        return $raw !== null && $raw !== '';
    }

    /**
     * 全部 secret.* 密文映射（field => cipher），请求级 + 持久两级缓存。
     *
     * @return array<string,string>
     */
    private static function rawMap(): array
    {
        if (self::$rawMap !== null) {
            return self::$rawMap;
        }

        $map = null;
        try {
            $cached = Cache::get(self::CACHE_KEY);
            if (is_array($cached)) {
                $map = $cached;
            }
        } catch (\Throwable $e) {
            // 缓存读取失败按未命中处理
        }

        if ($map === null) {
            $map = [];
            try {
                // 一次批量查询载入全部 secret.*（此前是逐字段 SELECT，N 个字段 N 条 SQL）
                $rows = Option::getArray(self::PREFIX_REGEX_SRC, 'REGEXP');
                $len  = strlen(self::PREFIX);
                foreach ($rows as $optionName => $value) {
                    $field = substr((string) $optionName, $len);
                    if ($field !== '' && $field !== false) {
                        $map[$field] = (string) $value;
                    }
                }
            } catch (\Throwable $e) {
                $map = [];
            }
            try {
                Cache::set(self::CACHE_KEY, $map, self::CACHE_TTL);
            } catch (\Throwable $e) {
                // 写缓存失败不影响本次读取
            }
        }

        self::$rawMap = $map;
        return self::$rawMap;
    }

    /** 失效持久缓存（set/remove 后调用） */
    private static function flushCache(): void
    {
        try {
            Cache::delete(self::CACHE_KEY);
        } catch (\Throwable $e) {
            // 忽略
        }
    }
}
