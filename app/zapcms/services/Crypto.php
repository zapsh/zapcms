<?php

namespace zapcms\services;

/**
 * 对称加解密：优先 AES-256-GCM（openssl），不可用时回退到
 * HMAC-SHA256-CTR + Encrypt-then-MAC（纯 PHP，零扩展依赖）。
 *
 * 密钥由 config('config.app_key')（或环境变量 ZAP_APP_KEY）经 SHA-256 派生，
 * 绝不写入数据库；app_key 为空时加密不可用。
 */
final class Crypto
{
    private const PREFIX_GCM = 'enc:v1:';
    private const PREFIX_CTR = 'enc:v2:';

    private static ?string $key = null;

    /** app_key 是否已配置（即加密能力是否可用） */
    public static function available(): bool
    {
        return self::key() !== '';
    }

    /** 由 app_key 派生的 32 字节密钥（app_key 为空时返回空串） */
    private static function key(): string
    {
        if (self::$key !== null) {
            return self::$key;
        }
        $raw = (string) (getenv('ZAP_APP_KEY') ?: config('config.app_key', ''));
        self::$key = $raw === '' ? '' : hash('sha256', $raw, true);
        return self::$key;
    }

    /**
     * @throws \RuntimeException app_key 未配置时
     */
    public static function encrypt(string $plain): string
    {
        if (self::key() === '') {
            throw new \RuntimeException('未配置 app_key，无法加密存储');
        }
        if (function_exists('openssl_encrypt') && function_exists('openssl_get_cipher_methods')
            && in_array('aes-256-gcm', openssl_get_cipher_methods(), true)) {
            return self::encryptGcm($plain);
        }
        return self::encryptCtr($plain);
    }

    /** 解密失败或无法解密时返回 null */
    public static function decrypt(string $payload): ?string
    {
        if (self::key() === '') {
            return null;
        }
        if (str_starts_with($payload, self::PREFIX_GCM)) {
            return self::decryptGcm($payload);
        }
        if (str_starts_with($payload, self::PREFIX_CTR)) {
            return self::decryptCtr($payload);
        }
        return null;
    }

    // ---- AES-256-GCM ----

    private static function encryptGcm(string $plain): string
    {
        $iv  = random_bytes(12);
        $tag = '';
        $ct  = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        return self::PREFIX_GCM . base64_encode($iv . $tag . $ct);
    }

    private static function decryptGcm(string $payload): ?string
    {
        $raw = base64_decode(substr($payload, strlen(self::PREFIX_GCM)), true);
        if ($raw === false || strlen($raw) < 28) {
            return null;
        }
        $iv  = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ct  = substr($raw, 28);
        $out = openssl_decrypt($ct, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag, '');
        return $out === false ? null : $out;
    }

    // ---- 纯 PHP 回退：HMAC-SHA256-CTR + Encrypt-then-MAC ----

    private static function encryptCtr(string $plain): string
    {
        $iv  = random_bytes(16);
        $ct  = self::xorStream($plain, $iv);
        $mac = hash_hmac('sha256', $iv . $ct, self::key(), true);
        return self::PREFIX_CTR . base64_encode($iv . $ct . $mac);
    }

    private static function decryptCtr(string $payload): ?string
    {
        $raw = base64_decode(substr($payload, strlen(self::PREFIX_CTR)), true);
        if ($raw === false || strlen($raw) < 48) {
            return null;
        }
        $iv     = substr($raw, 0, 16);
        $mac    = substr($raw, -32);
        $ct     = substr($raw, 16, -32);
        $expect = hash_hmac('sha256', $iv . $ct, self::key(), true);
        if (!hash_equals($expect, $mac)) {
            return null;
        }
        return self::xorStream($ct, $iv);
    }

    /** HMAC-CTR 密钥流 XOR：加解密共用（XOR 对称） */
    private static function xorStream(string $data, string $iv): string
    {
        $key    = self::key();
        $out    = '';
        $len    = strlen($data);
        $blocks = intdiv($len, 32) + 1;
        for ($i = 0; $i < $blocks; $i++) {
            $out .= hash_hmac('sha256', $iv . pack('N', $i), $key, true);
        }
        return $data ^ substr($out, 0, $len);
    }
}
