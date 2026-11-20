<?php

namespace zapcms\services;

use zap\DB;

/**
 * 登录限流：同一（IP + 账号）组合在 5 分钟内失败超过阈值即临时锁定。
 *
 * 用于防暴力破解。失败计数存于 admin_login_attempts 表，
 * 窗口结束后自动重置，登录成功后立即清除。
 */
class LoginThrottle
{
    /** 最大允许失败次数 */
    public const MAX_ATTEMPTS = 5;

    /** 锁定窗口（秒），默认 5 分钟 */
    public const WINDOW_SECONDS = 300;

    /**
     * 该 key 当前是否处于锁定状态。
     */
    public static function isLocked(string $key): bool
    {
        $row = self::row($key);
        if (!$row) {
            return false;
        }

        // 超出窗口则视为已过期，调用方负责清理
        if (time() - (int)$row['first_at'] > self::WINDOW_SECONDS) {
            return false;
        }

        return (int)$row['attempts'] >= self::MAX_ATTEMPTS;
    }

    /**
     * 记录一次失败尝试。
     *
     * @return array{locked:bool, attempts:int, remain_try:int, retry_after:int}
     */
    public static function recordFailure(string $key): array
    {
        $now     = time();
        $row     = self::row($key);
        $expired = !$row || ($now - (int)$row['first_at']) > self::WINDOW_SECONDS;

        if ($expired) {
            $attempts = 1;
            $firstAt  = $now;
        } else {
            $attempts = (int)$row['attempts'] + 1;
            $firstAt  = (int)$row['first_at'];
        }

        $data = [
            'attempt_key' => $key,
            'attempts'    => $attempts,
            'first_at'    => $firstAt,
            'last_at'     => $now,
        ];

        if ($row) {
            DB::table('admin_login_attempts')
                ->where('attempt_key', $key)
                ->update($data);
        } else {
            DB::table('admin_login_attempts')->insert($data);
        }

        $locked     = $attempts >= self::MAX_ATTEMPTS;
        $retryAfter = $locked ? (self::WINDOW_SECONDS - ($now - $firstAt)) : 0;
        $remainTry  = $locked ? 0 : max(0, self::MAX_ATTEMPTS - $attempts);

        return [
            'locked'      => $locked,
            'attempts'    => $attempts,
            'remain_try'  => $remainTry,
            'retry_after' => $retryAfter,
        ];
    }

    /**
     * 登录成功后清除失败计数。
     */
    public static function reset(string $key): void
    {
        DB::table('admin_login_attempts')->where('attempt_key', $key)->delete();
    }

    /**
     * 过期清理（可选：定时调用，避免 admin_login_attempts 无限增长）。
     */
    public static function purgeExpired(): int
    {
        $threshold = time() - self::WINDOW_SECONDS;
        return DB::table('admin_login_attempts')
            ->where('last_at', '<', $threshold)
            ->delete();
    }

    protected static function row(string $key): ?array
    {
        $row = DB::table('admin_login_attempts')
            ->where('attempt_key', $key)
            ->fetch(FETCH_ASSOC);
        return $row ?: null;
    }
}
