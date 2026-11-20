<?php

namespace zapcms\services;

use zap\DB;

/**
 * API 鉴权：支持「后台会话」与「Bearer Token」两种方式。
 *
 *  - 会话：复用后台已登录管理员（Auth::isLogin），零额外存储。
 *  - Token：明文仅签发时返回一次，库内存 sha256 哈希；可指定过期时间与 scope。
 *
 * 解析到的管理员身份会通过 app()->rbac->setUser() 注入 RBAC，
 * 因此 API 内可直接复用现有 admin_menu_{id} 权限键做授权。
 */
final class ApiAuth
{
    private const TOKEN_BYTES = 32;

    /**
     * 解析当前请求的管理员 ID：会话优先，其次 Bearer Token。无则返回 null。
     */
    public static function resolve(): ?int
    {
        if (Auth::isLogin()) {
            $id = (int) Auth::user('id');
            return $id > 0 ? $id : null;
        }

        $token = self::bearerToken();
        if ($token !== null) {
            return self::verify($token);
        }

        return null;
    }

    /**
     * 校验 Bearer Token，返回对应的 admin_id（无效 / 过期 / 表不存在返回 null）。
     */
    public static function verify(string $token): ?int
    {
        $hash = hash('sha256', $token);

        try {
            $row = DB::table('api_tokens')
                ->where('token_hash', $hash)
                ->first();
        } catch (\Throwable $e) {
            return null;
        }

        if (empty($row)) {
            return null;
        }

        $expires = (int) ($row['expires_at'] ?? 0);
        if ($expires > 0 && $expires < time()) {
            return null;
        }

        // 更新最后使用时间（失败忽略，不影响鉴权）
        try {
            DB::update('api_tokens', ['last_used_at' => time()], ['id' => (int) $row['id']]);
        } catch (\Throwable $e) {
        }

        return (int) $row['admin_id'];
    }

    /**
     * 为管理员签发 Token。
     *
     * @param int    $adminId
     * @param array  $scopes   授权范围（如 ['node:read','user:write']），存为 JSON
     * @param int    $ttl      有效期秒数，0 = 永不过期
     * @return string|null 原始 token（仅此一次返回明文），失败返回 null
     */
    public static function issue(int $adminId, array $scopes = [], int $ttl = 0): ?string
    {
        $raw  = bin2hex(random_bytes(self::TOKEN_BYTES));
        $hash = hash('sha256', $raw);

        try {
            DB::insert('api_tokens', [
                'admin_id'   => $adminId,
                'token_hash' => $hash,
                'scopes'     => $scopes === [] ? null : json_encode($scopes, JSON_UNESCAPED_UNICODE),
                'expires_at' => $ttl > 0 ? time() + $ttl : 0,
                'created_at' => time(),
            ]);
        } catch (\Throwable $e) {
            return null;
        }

        return $raw;
    }

    /**
     * 吊销 Token：指定 $token 仅吊销该条，否则吊销该管理员全部 Token。
     *
     * @return int 受影响行数
     */
    public static function revoke(int $adminId, ?string $token = null): int
    {
        try {
            if ($token !== null) {
                return DB::delete(
                    'api_tokens',
                    'admin_id=:a AND token_hash=:h',
                    ['a' => $adminId, 'h' => hash('sha256', $token)]
                );
            }
            return DB::delete('api_tokens', 'admin_id=:a', ['a' => $adminId]);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** 从请求头解析 Bearer Token */
    private static function bearerToken(): ?string
    {
        $header = null;
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $header  = $headers['Authorization'] ?? null;
        }

        if ($header === null) {
            return null;
        }

        if (preg_match('/^Bearer\s+(.+)$/i', trim($header), $m)) {
            return $m[1];
        }

        return null;
    }
}
