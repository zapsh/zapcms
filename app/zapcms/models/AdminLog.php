<?php

namespace zapcms\models;

use zap\DB;

/**
 * 后台操作审计日志。
 *
 * 统一写入 admin_logs 表，支持日志级别（level）与业务分类（category），
 * 便于后台「日志查看」按级别 / 分类筛选，以及安全事件（登录失败、锁定）追踪。
 *
 * 级别约定（与 PSR-3 / 系统日志对齐）：
 *   EMERGENCY > ALERT > CRITICAL > ERROR > WARNING > NOTICE > INFO > DEBUG
 * 常用分类：auth / user / content / system / security
 *
 * 兼容旧调用：AdminLog::log('标题', '内容') 仍可用，默认 INFO + system。
 */
class AdminLog
{
    // ───────────────── 日志级别 ─────────────────
    public const LEVEL_EMERGENCY = 'EMERGENCY';
    public const LEVEL_ALERT     = 'ALERT';
    public const LEVEL_CRITICAL  = 'CRITICAL';
    public const LEVEL_ERROR     = 'ERROR';
    public const LEVEL_WARNING   = 'WARNING';
    public const LEVEL_NOTICE    = 'NOTICE';
    public const LEVEL_INFO      = 'INFO';
    public const LEVEL_DEBUG     = 'DEBUG';

    // ───────────────── 业务分类 ─────────────────
    public const CATEGORY_AUTH     = 'auth';
    public const CATEGORY_USER     = 'user';
    public const CATEGORY_CONTENT  = 'content';
    public const CATEGORY_SYSTEM   = 'system';
    public const CATEGORY_SECURITY = 'security';

    /**
     * 记录一条审计日志。
     *
     * @param string      $title    简短标题（如“管理员登录”）
     * @param string      $content  详细说明
     * @param int|null    $uid      操作人 ID（默认取当前登录管理员）
     * @param string|null $username 操作人用户名
     * @param string      $level    日志级别
     * @param string      $category 业务分类
     */
    public static function log(
        string $title,
        string $content = '',
        ?int $uid = null,
        ?string $username = null,
        string $level = self::LEVEL_INFO,
        string $category = self::CATEGORY_SYSTEM
    ): void {
        $uid      = $uid ?? (int)(\zapcms\services\Auth::user()['id'] ?? 0);
        $username = $username ?? (\zapcms\services\Auth::user()['username'] ?? 'system');

        DB::table('admin_logs')->insert([
            'uid'          => $uid,
            'username'     => $username,
            'category'     => $category,
            'title'        => $title,
            'content'      => $content,
            'level'        => strtoupper($level),
            'ipaddress'    => \zap\http\Request::ip(),
            'request_url'  => \zap\http\Request::url(),
            'user_agent'   => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'request_time' => time(),
        ]);
    }

    // ───────────────── 便捷方法 ─────────────────
    public static function info(string $category, string $title, string $content = '', ?int $uid = null, ?string $username = null): void
    {
        self::log($title, $content, $uid, $username, self::LEVEL_INFO, $category);
    }

    public static function notice(string $category, string $title, string $content = '', ?int $uid = null, ?string $username = null): void
    {
        self::log($title, $content, $uid, $username, self::LEVEL_NOTICE, $category);
    }

    public static function warning(string $category, string $title, string $content = '', ?int $uid = null, ?string $username = null): void
    {
        self::log($title, $content, $uid, $username, self::LEVEL_WARNING, $category);
    }

    public static function error(string $category, string $title, string $content = '', ?int $uid = null, ?string $username = null): void
    {
        self::log($title, $content, $uid, $username, self::LEVEL_ERROR, $category);
    }

    /**
     * 分页检索审计日志（供后台日志查看器使用）。
     *
     * @param array $filter ['category'=>, 'level'=>, 'keyword'=>]
     */
    public static function search(array $filter = [], int $limit = 50, int $offset = 0): array
    {
        $applyFilter = static function ($query) use ($filter) {
            if (!empty($filter['category'])) {
                $query->where('category', $filter['category']);
            }
            if (!empty($filter['level'])) {
                $query->where('level', strtoupper($filter['level']));
            }
            if (!empty($filter['keyword'])) {
                $kw = $filter['keyword'];
                $query->where(function ($q) use ($kw) {
                    $q->where('title', 'LIKE', "%{$kw}%")
                      ->orWhere('content', 'LIKE', "%{$kw}%")
                      ->orWhere('username', 'LIKE', "%{$kw}%");
                });
            }
            return $query;
        };

        // COUNT 与列表必须用两个独立的 Query 对象：
        // Query::select() 会覆盖列清单，复用同一对象会让列表查询仍执行
        // SELECT COUNT(*) AS total，只返回一行 total 字段（日志显示不全的根因）。
        $countQuery = $applyFilter(DB::table('admin_logs'));
        $total = (int)($countQuery->select('COUNT(*) AS total')->fetch(FETCH_ASSOC)['total'] ?? 0);

        $listQuery = $applyFilter(DB::table('admin_logs'));
        $rows = $listQuery->orderBy('request_time', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->fetchAll(FETCH_ASSOC);

        return [
            'total'   => $total,
            'limit'   => $limit,
            'offset'  => $offset,
            'entries' => $rows,
        ];
    }

    /**
     * 获取指定用户的活动日志（分页）。
     */
    public static function getByUser(int $uid, int $limit = 50, int $offset = 0): array
    {
        return DB::table('admin_logs')
            ->where('uid', $uid)
            ->orderBy('request_time', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->fetchAll(FETCH_ASSOC);
    }

    /**
     * 获取活动日志总数。
     */
    public static function countByUser(int $uid): int
    {
        $result = DB::table('admin_logs')
            ->select('COUNT(*) AS total')
            ->where('uid', $uid)
            ->fetch(FETCH_ASSOC);
        return (int)($result['total'] ?? 0);
    }
}
