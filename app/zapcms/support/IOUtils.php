<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS
 */

namespace zapcms\support;

use zap\util\FileUtils;

/**
 * CMS 配置/缓存文件读写工具
 *
 * 通用文件操作复用框架的 zap\util\FileUtils，本类专注 CMS 里的
 * JSON 配置（theme.json / *.json）与 PHP return 数组缓存：
 *  - 读：静默降级、UTF-8 BOM、JSON 注释/尾逗号、大整数不丢精度，错误可查 lastError()；
 *  - 写：自动建目录、临时文件 + LOCK_EX + rename 的原子写，失败不留半成品；
 *  - 兼容：readJsonFile() 前两个参数与旧版一致，失败仍返回 false，
 *    现有 `IOUtils::readJsonFile($f, true) ?: []` 写法不受影响。
 */
class IOUtils
{
    /** 默认 JSON 编码选项：中文不转义、斜杠不转义、格式化便于人工编辑 */
    public const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;

    /** 最近一次操作的错误信息（成功为空串） */
    private static string $lastError = '';

    public static function lastError(): string
    {
        return self::$lastError;
    }

    protected static function fail(string $message): void
    {
        self::$lastError = $message;
    }

    protected static function ok(): void
    {
        self::$lastError = '';
    }

    // ========== JSON ==========

    /**
     * 读取 JSON 文件
     *
     * @param string    $file          文件路径
     * @param bool|null $associative   true=关联数组，false/null=对象
     * @param mixed     $default       文件不存在/无法解析时的返回值（默认 false，保持旧行为）
     * @param bool      $allowComments 严格解析失败后，尝试去掉 // /* 注释与尾逗号再解析
     */
    public static function readJsonFile(
        string $file,
        ?bool $associative = null,
        mixed $default = false,
        bool $allowComments = false
    ): mixed {
        $content = self::readFile($file, false);
        if ($content === false) {
            return $default;
        }
        $content = self::stripBom($content);
        if (trim($content) === '') {
            self::fail("JSON 文件为空：{$file}");
            return $default;
        }

        [$success, $data] = self::decodeJson($content, $associative, $file);
        if ($success) {
            return $data;
        }

        if ($allowComments) {
            $clean = self::stripJsonComments($content);
            if ($clean !== $content) {
                [$success, $data] = self::decodeJson($clean, $associative, $file);
                if ($success) {
                    return $data;
                }
            }
        }
        return $default;
    }

    /**
     * 写入 JSON 文件（原子写）
     *
     * @param int|null $flags json_encode 选项，null 使用 JSON_FLAGS
     */
    public static function writeJsonFile(string $file, mixed $data, ?int $flags = null): bool
    {
        $flags = $flags ?? self::JSON_FLAGS;
        $json  = json_encode($data, $flags);

        // 内容里有非法 UTF-8 时再试一次替换，避免整个文件写不进去
        if ($json === false) {
            $json = json_encode($data, $flags | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        if ($json === false) {
            self::fail('JSON 编码失败：' . json_last_error_msg());
            return false;
        }
        return self::writeFile($file, $json . PHP_EOL);
    }

    /** @return array{0:bool,1:mixed} */
    protected static function decodeJson(string $content, ?bool $associative, string $file): array
    {
        $data = json_decode($content, $associative, 512, JSON_BIGINT_AS_STRING);
        if (json_last_error() === JSON_ERROR_NONE) {
            self::ok();
            return [true, $data];
        }
        self::fail('JSON 解析失败（' . json_last_error_msg() . "）：{$file}");
        return [false, null];
    }

    // ========== PHP return 数组缓存 ==========

    /**
     * 读取 `<?php return ...;` 形式的 PHP 文件
     *
     * 在独立闭包里 include，隔离变量作用域；文件抛异常或无 return 时返回 $default。
     */
    public static function readPhpFile(string $file, mixed $default = []): mixed
    {
        if (!is_file($file) || !is_readable($file)) {
            self::fail("文件不存在或不可读：{$file}");
            return $default;
        }
        try {
            $data = (static function (string $__path) {
                return include $__path;
            })($file);
        } catch (\Throwable $e) {
            self::fail("PHP 文件读取失败（{$e->getMessage()}）：{$file}");
            return $default;
        }
        // include 对「没有 return 的文件」返回 1
        if ($data === 1) {
            self::fail("PHP 文件没有 return 值：{$file}");
            return $default;
        }
        self::ok();
        return $data;
    }

    /**
     * 写入 `<?php return ...;` 形式的 PHP 数组文件（原子写）
     *
     * 仅支持数组 / 标量 / null / stdClass，其它对象无法被 var_export 安全还原。
     */
    public static function writePhpFile(string $file, mixed $data): bool
    {
        if (!self::isExportable($data)) {
            self::fail('PHP 文件写入失败：数据包含无法 var_export 还原的对象');
            return false;
        }
        return self::writeFile($file, '<?php return ' . var_export($data, true) . ';' . PHP_EOL);
    }

    protected static function isExportable(mixed $value): bool
    {
        if ($value === null || is_scalar($value)) {
            return true;
        }
        if ($value instanceof \stdClass) {
            $value = (array) $value;
        }
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $item) {
            if (!self::isExportable($item)) {
                return false;
            }
        }
        return true;
    }

    // ========== 文本 / 目录 ==========

    /** 读取文本内容，失败返回 $default */
    public static function readFile(string $file, mixed $default = false): mixed
    {
        if (!is_file($file) || !is_readable($file)) {
            self::fail("文件不存在或不可读：{$file}");
            return $default;
        }
        $content = @file_get_contents($file);
        if ($content === false) {
            self::fail("文件读取失败：{$file}");
            return $default;
        }
        self::ok();
        return $content;
    }

    /**
     * 原子写文本：同目录临时文件 + LOCK_EX + rename，避免并发读到半截内容
     *
     * Windows 下 rename 无法覆盖已存在文件，会退化到「删除后重命名」，
     * 最后再兜底直接写；任何失败都不会留下临时文件。
     */
    public static function writeFile(string $file, string $content): bool
    {
        $dir = dirname($file);
        if (!self::ensureDir($dir)) {
            self::fail("无法创建目录：{$dir}");
            return false;
        }

        $tmp = @tempnam($dir, '.zapio_');
        if ($tmp === false) {
            self::fail("无法创建临时文件：{$dir}");
            return false;
        }

        if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
            @unlink($tmp);
            self::fail("临时文件写入失败：{$tmp}");
            return false;
        }

        // 继承目标文件权限，新建文件则用默认 umask 权限
        $mode = is_file($file) ? (@fileperms($file) & 0777) : (0666 & ~umask());
        @chmod($tmp, $mode ?: 0644);

        if (@rename($tmp, $file)) {
            self::ok();
            return true;
        }
        if (is_file($file) && @unlink($file) && @rename($tmp, $file)) {
            self::ok();
            return true;
        }

        @unlink($tmp);
        if (@file_put_contents($file, $content, LOCK_EX) !== false) {
            self::ok();
            return true;
        }
        self::fail("文件写入失败：{$file}");
        return false;
    }

    /** 确保目录存在（委托框架 FileUtils，并规避并发创建时的竞态） */
    public static function ensureDir(string $dir, int $mode = 0775): bool
    {
        if (is_dir($dir)) {
            self::ok();
            return true;
        }
        if (FileUtils::ensureDir($dir, $mode) || is_dir($dir)) {
            self::ok();
            return true;
        }
        self::fail("目录创建失败：{$dir}");
        return false;
    }

    /** 删除单个文件 */
    public static function deleteFile(string $file): bool
    {
        $result = FileUtils::delete($file);
        if ($result) {
            self::ok();
        } else {
            self::fail("文件删除失败：{$file}");
        }
        return $result;
    }

    // ========== 内容预处理 ==========

    protected static function stripBom(string $content): string
    {
        return str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content;
    }

    /**
     * 去掉 JSON 里的 // 与 /* *\/ 注释、以及对象/数组最后一个元素后的逗号
     *
     * 用状态机逐字符扫描，字符串内的 `//`、`}`、逗号都不会被误伤。
     */
    protected static function stripJsonComments(string $json): string
    {
        $out      = '';
        $len      = strlen($json);
        $inString = false;
        $escaped  = false;

        for ($i = 0; $i < $len; $i++) {
            $ch   = $json[$i];
            $next = $i + 1 < $len ? $json[$i + 1] : '';

            if ($inString) {
                $out .= $ch;
                if ($escaped) {
                    $escaped = false;
                } elseif ($ch === '\\') {
                    $escaped = true;
                } elseif ($ch === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($ch === '"') {
                $inString = true;
                $out .= $ch;
                continue;
            }

            if ($ch === '/' && $next === '/') {
                $j = $i + 2;
                while ($j < $len && $json[$j] !== "\n" && $json[$j] !== "\r") {
                    $j++;
                }
                $i = $j - 1;
                $out .= "\n";
                continue;
            }

            if ($ch === '/' && $next === '*') {
                $end = strpos($json, '*/', $i + 2);
                $i   = $end === false ? $len : $end + 1;
                $out .= ' ';
                continue;
            }

            if ($ch === '}' || $ch === ']') {
                // 仅移除输出末尾紧邻的尾逗号，不会碰字符串内容
                $out = preg_replace('/,\s*$/', '', $out) ?? $out;
            }
            $out .= $ch;
        }
        return $out;
    }
}
