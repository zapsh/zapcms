<?php

namespace zapcms\services\storage;

use zapcms\services\Crypto;
use zapcms\services\Option;

/**
 * 敏感值的加密存取（云存储密钥、SMTP 密码、Redis 密码、第三方 API 密钥等）。
 *
 * 明文经 Crypto（app_key 派生密钥）加密后写入 options 表，
 * 键名形如 secret.<field>，autoload=0（不进全量加载）。
 * 读取时解密失败/未配置 app_key 返回 $default，由调用方回退到 config/env。
 */
final class Secret
{
    private const PREFIX = 'secret.';

    public static function available(): bool
    {
        return Crypto::available();
    }

    /** 读取并解密；无值或解密失败返回 $default */
    public static function get(string $vendorField, $default = null)
    {
        $raw = Option::get(self::PREFIX . $vendorField);
        if ($raw === null || $raw === '') {
            return $default;
        }
        $plain = Crypto::decrypt((string) $raw);
        return $plain === null ? $default : $plain;
    }

    public static function set(string $vendorField, string $plain): void
    {
        Option::upsert(self::PREFIX . $vendorField, Crypto::encrypt($plain), 0, 0);
    }

    public static function remove(string $vendorField): void
    {
        Option::remove(self::PREFIX . $vendorField);
    }

    /** 该字段是否已存有密文（用于后台显示「已配置」） */
    public static function configured(string $vendorField): bool
    {
        $raw = Option::get(self::PREFIX . $vendorField);
        return $raw !== null && $raw !== '';
    }
}
