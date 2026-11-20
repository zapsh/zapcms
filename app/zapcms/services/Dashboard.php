<?php

namespace zapcms\services;

use zap\DB;
use zap\view\View;
use zapcms\Hook;
use zapcms\models\Node;

/**
 * 控制台（Dashboard）Widget 注册表 + 每个管理员的布局偏好。
 *
 * ── 扩展点（全部基于 zapcms\Hook，插件在 boot 阶段注册） ──────────────────
 *   filter  dashboard.widgets           ($widgets, $adminId)  注册/改写 widget 定义
 *   filter  dashboard.widget.defaults  ($defaults, $widgets)  改写默认顺序与启用状态
 *   filter  dashboard.layout           ($layout, $adminId, $widgets) 读取后的布局
 *   filter  dashboard.widget.output    ($html, $widget, $adminId) 单个 widget 输出
 *   action  dashboard.before / dashboard.after ($widgets, $layout, $adminId)
 *                                             在控制台首尾注入 HTML（回调返回字符串）
 *   action  dashboard.layout.saved     ($adminId, $layout)   保存成功后通知
 *
 * widget 定义结构：
 *   id, title, description, icon, col, capability, default_on, render(callable)
 *   render 签名 ($widget, $adminId): string；只在 widget 可见时执行，按需查库。
 *   capability 为空表示所有已登录管理员可见，否则走 RBAC::check()。
 *
 * ── 每用户存储 ────────────────────────────────────────────────────────
 *   admin_meta(object_id = admin.id, meta_name = 'dashboard.widgets')
 *   meta_value = {"version":1,"order":[...],"hidden":[...],"updated_at":...}
 *   表没有 (object_id, meta_name) 唯一索引，写入采用「先删后插」避免重复行。
 */
class Dashboard
{
    /** admin_meta.meta_name */
    public const META_KEY = 'dashboard.widgets';

    /** 布局结构版本 */
    public const LAYOUT_VERSION = 1;

    /** 单次最多渲染的 widget 数，防止插件注册过多拖慢首页 */
    public const MAX_WIDGETS = 40;

    /** 允许插件使用的 Bootstrap 栅格列类（白名单，避免任意 class 破坏布局） */
    private const ALLOWED_COLS = [
        'col-12', 'col-6',
        'col-md-6', 'col-md-4', 'col-md-8',
        'col-lg-3', 'col-lg-4', 'col-lg-5', 'col-lg-6', 'col-lg-7', 'col-lg-8',
        'col-xl-3', 'col-xl-4', 'col-xl-6',
    ];

    /** @var array<int,array> 请求级布局缓存 */
    private static array $layoutCache = [];

    // ==================== Widget 注册表 ====================

    /**
     * 当前管理员可见的全部 widget 定义（id => 定义）。
     */
    public static function widgets(int $adminId): array
    {
        $widgets = [];
        foreach (self::builtins() as $widget) {
            $widgets[$widget['id']] = $widget;
        }

        // 插件/主题：Hook::filter('dashboard.widgets', fn($widgets, $adminId) => $widgets)
        $widgets = Hook::applyFilters('dashboard.widgets', $widgets, $adminId);
        if (!is_array($widgets)) {
            $widgets = [];
        }

        $result = [];
        foreach ($widgets as $id => $widget) {
            $id = (string)$id;
            if ($id === '' || !preg_match('/^[a-zA-Z0-9_.-]{1,64}$/', $id) || !is_array($widget)) {
                continue;
            }
            if (!isset($widget['render']) || !is_callable($widget['render'])) {
                continue;
            }
            if (!self::can((string)($widget['capability'] ?? ''))) {
                continue;
            }

            $col = (string)($widget['col'] ?? 'col-12');
            $result[$id] = [
                'id'          => $id,
                'title'       => (string)($widget['title'] ?? $id),
                'description' => (string)($widget['description'] ?? ''),
                'icon'        => (string)($widget['icon'] ?? 'fa fa-cube'),
                'col'         => in_array($col, self::ALLOWED_COLS, true) ? $col : 'col-12',
                'capability'  => (string)($widget['capability'] ?? ''),
                'default_on'  => (bool)($widget['default_on'] ?? true),
                'render'      => $widget['render'],
            ];
            if (count($result) >= self::MAX_WIDGETS) {
                break;
            }
        }
        return $result;
    }

    /**
     * 系统内置 widget。每个 render 都自行准备数据，隐藏的 widget 完全不查库。
     */
    protected static function builtins(): array
    {
        return [
            [
                'id'          => 'stats.node_types',
                'title'       => __('admin.widget_stats'),
                'description' => __('admin.widget_stats_desc'),
                'icon'        => 'fa fa-chart-simple',
                'col'         => 'col-12',
                'default_on'  => true,
                'render'      => static function (): string {
                    $node = new Node();
                    return View::make('dashboard.widgets.stats', [
                        'node_types_statistics' => $node->getAllTypesCount(),
                    ])->fetch();
                },
            ],
            [
                'id'          => 'pages.list',
                'title'       => __('admin.widget_pages'),
                'description' => __('admin.widget_pages_desc'),
                'icon'        => 'fa fa-file-alt',
                'col'         => 'col-lg-7',
                'default_on'  => true,
                'render'      => static function (): string {
                    $node = new Node();
                    return View::make('dashboard.widgets.pages', [
                        'pages' => $node->getPages(),
                    ])->fetch();
                },
            ],
            [
                'id'          => 'system.website',
                'title'       => __('admin.widget_website'),
                'description' => __('admin.widget_website_desc'),
                'icon'        => 'fa fa-circle-info',
                'col'         => 'col-lg-5',
                'default_on'  => true,
                'render'      => static fn(): string => View::make('dashboard.widgets.website')->fetch(),
            ],
            [
                'id'          => 'activity.recent',
                'title'       => __('admin.widget_activity'),
                'description' => __('admin.widget_activity_desc'),
                'icon'        => 'fa fa-clock-rotate-left',
                'col'         => 'col-12',
                'default_on'  => true,
                'render'      => static function (): string {
                    try {
                        $logs = DB::table('admin_logs')
                            ->select('id,username,title,category,level,request_time')
                            ->orderBy('request_time desc')
                            ->limit(8)
                            ->get(FETCH_ASSOC);
                    } catch (\Throwable $e) {
                        $logs = [];
                    }
                    return View::make('dashboard.widgets.activity', ['logs' => $logs])->fetch();
                },
            ],
            [
                'id'          => 'system.server',
                'title'       => __('admin.widget_system'),
                'description' => __('admin.widget_system_desc'),
                'icon'        => 'fa fa-server',
                'col'         => 'col-12',
                'default_on'  => true,
                'render'      => static fn(): string => View::make('dashboard.widgets.system')->fetch(),
            ],
        ];
    }

    /**
     * capability 校验；超管由 RBAC::check() 内部短路放行。
     */
    protected static function can(string $capability): bool
    {
        if ($capability === '') {
            return true;
        }
        try {
            $application = function_exists('app') ? app() : null;
            $rbac = $application !== null ? $application->rbac : null;
        } catch (\Throwable $e) {
            return false;
        }
        if ($rbac === null || !method_exists($rbac, 'check')) {
            return false;
        }
        return (bool)$rbac->check($capability);
    }

    // ==================== 每用户布局 ====================

    /**
     * 默认布局：按注册表顺序排列，default_on=false 的放入 hidden。
     */
    public static function defaults(array $widgets): array
    {
        $order  = array_keys($widgets);
        $hidden = [];
        foreach ($widgets as $id => $widget) {
            if (!$widget['default_on']) {
                $hidden[] = $id;
            }
        }
        $defaults = [
            'version'    => self::LAYOUT_VERSION,
            'order'      => $order,
            'hidden'     => $hidden,
            'updated_at' => 0,
        ];
        return Hook::applyFilters('dashboard.widget.defaults', $defaults, $widgets);
    }

    /**
     * 读取当前管理员的布局（无记录时用默认值，新增 widget 自动补到末尾）。
     */
    public static function layout(int $adminId, array $widgets): array
    {
        if (isset(self::$layoutCache[$adminId])) {
            return self::$layoutCache[$adminId];
        }

        $defaults = self::defaults($widgets);
        $stored   = self::metaGet($adminId);
        $layout   = $defaults;

        if (is_array($stored)) {
            $layout['order']      = self::sanitizeOrder($stored['order'] ?? [], array_keys($widgets), $defaults['order']);
            $layout['hidden']     = self::sanitizeHidden($stored['hidden'] ?? [], array_keys($widgets));
            $layout['updated_at'] = (int)($stored['updated_at'] ?? 0);
        }

        $layout = Hook::applyFilters('dashboard.layout', $layout, $adminId, $widgets);
        if (!is_array($layout)) {
            $layout = $defaults;
        }
        $layout['version']    = self::LAYOUT_VERSION;
        $layout['order']      = self::sanitizeOrder($layout['order'] ?? [], array_keys($widgets), $defaults['order']);
        $layout['hidden']     = self::sanitizeHidden($layout['hidden'] ?? [], array_keys($widgets));
        $layout['updated_at'] = (int)($layout['updated_at'] ?? 0);

        return self::$layoutCache[$adminId] = $layout;
    }

    /**
     * 当前管理员可见、按自定义顺序排列的 widget 定义列表。
     */
    public static function active(int $adminId): array
    {
        $widgets = self::widgets($adminId);
        $layout  = self::layout($adminId, $widgets);
        $hidden  = array_fill_keys($layout['hidden'], true);

        $active = [];
        foreach ($layout['order'] as $id) {
            if (isset($widgets[$id]) && !isset($hidden[$id])) {
                $active[] = $widgets[$id];
            }
        }
        return $active;
    }

    /**
     * 定制弹窗所需数据：全部可选 widget + 当前顺序 / 启用状态。
     */
    public static function picker(int $adminId): array
    {
        $widgets = self::widgets($adminId);
        $layout  = self::layout($adminId, $widgets);
        $hidden  = array_fill_keys($layout['hidden'], true);

        $items = [];
        foreach ($layout['order'] as $id) {
            if (!isset($widgets[$id])) {
                continue;
            }
            $w = $widgets[$id];
            $items[] = [
                'id'          => $id,
                'title'       => $w['title'],
                'description' => $w['description'],
                'icon'        => $w['icon'],
                'col'         => $w['col'],
                'enabled'     => !isset($hidden[$id]),
            ];
        }
        return ['items' => $items, 'layout' => $layout];
    }

    /**
     * 保存当前管理员的布局（顺序 + 隐藏项）。
     */
    public static function saveLayout(int $adminId, array $order, array $hidden, array $widgets): array
    {
        $known = array_keys($widgets);
        $layout = [
            'version'    => self::LAYOUT_VERSION,
            'order'      => self::sanitizeOrder($order, $known, $known),
            'hidden'     => self::sanitizeHidden($hidden, $known),
            'updated_at' => time(),
        ];

        self::metaSet($adminId, $layout);
        self::$layoutCache[$adminId] = $layout;

        // 回调可做审计、清缓存等副作用；fire 只收集字符串返回值，这里忽略返回值
        Hook::fire('dashboard.layout.saved', $adminId, $layout);
        return $layout;
    }

    /**
     * 恢复默认布局（删除该用户的 meta 记录）。
     */
    public static function resetLayout(int $adminId): void
    {
        if ($adminId > 0) {
            try {
                DB::table('admin_meta')
                    ->where('object_id', $adminId)
                    ->where('meta_name', self::META_KEY)
                    ->delete();
            } catch (\Throwable $e) {
                // 表不可用时忽略，仍按默认布局渲染
            }
        }
        unset(self::$layoutCache[$adminId]);
        Hook::fire('dashboard.layout.saved', $adminId, null);
    }

    // ==================== 渲染 ====================

    /**
     * 渲染单个 widget，并应用输出过滤器。单个组件异常不影响整页。
     */
    public static function renderWidget(array $widget, int $adminId): string
    {
        try {
            $html = (string)call_user_func($widget['render'], $widget, $adminId);
        } catch (\Throwable $e) {
            $html = '<div class="alert alert-warning mb-0 small">' . e(__('admin.widget_failed', ['title' => $widget['title']])) . '</div>';
        }
        $filtered = Hook::applyFilters('dashboard.widget.output', $html, $widget, $adminId);
        return is_string($filtered) ? $filtered : $html;
    }

    /** 控制台内容区顶部注入 */
    public static function beforeHtml(array $widgets, array $layout, int $adminId): string
    {
        return Hook::fire('dashboard.before', $widgets, $layout, $adminId);
    }

    /** 控制台内容区底部注入 */
    public static function afterHtml(array $widgets, array $layout, int $adminId): string
    {
        return Hook::fire('dashboard.after', $widgets, $layout, $adminId);
    }

    // ==================== admin_meta 存取 ====================

    protected static function metaGet(int $adminId): ?array
    {
        if ($adminId <= 0) {
            return null;
        }
        try {
            $rows = DB::table('admin_meta')
                ->where('object_id', $adminId)
                ->where('meta_name', self::META_KEY)
                ->get(FETCH_ASSOC);
        } catch (\Throwable $e) {
            return null; // 表不存在（安装/升级中）时回落默认布局
        }
        if (empty($rows) || !isset($rows[0]['meta_value'])) {
            return null;
        }
        $data = json_decode((string)$rows[0]['meta_value'], true);
        return is_array($data) ? $data : null;
    }

    protected static function metaSet(int $adminId, array $layout): void
    {
        if ($adminId <= 0) {
            return;
        }
        // admin_meta 无 (object_id, meta_name) 唯一索引，先删后插避免同键多行
        // 分别取新查询对象，避免 delete() 的 where 条件残留到 insert()
        DB::table('admin_meta')->where('object_id', $adminId)->where('meta_name', self::META_KEY)->delete();
        DB::table('admin_meta')->insert([
            'object_id'  => $adminId,
            'meta_name'  => self::META_KEY,
            'meta_value' => json_encode($layout, JSON_UNESCAPED_UNICODE),
        ]);
    }

    // ==================== 内部工具 ====================

    /**
     * 顺序白名单校验：丢弃未知/重复 id，补齐缺失 id。
     */
    protected static function sanitizeOrder($order, array $known, array $fallback): array
    {
        $clean = [];
        if (is_array($order)) {
            foreach ($order as $id) {
                $id = (string)$id;
                if (in_array($id, $known, true) && !in_array($id, $clean, true)) {
                    $clean[] = $id;
                }
            }
        }
        foreach ($fallback as $id) {
            if (in_array($id, $known, true) && !in_array($id, $clean, true)) {
                $clean[] = $id;
            }
        }
        return array_slice($clean, 0, self::MAX_WIDGETS);
    }

    /**
     * 隐藏项白名单校验。
     */
    protected static function sanitizeHidden($hidden, array $known): array
    {
        $clean = [];
        if (is_array($hidden)) {
            foreach ($hidden as $id) {
                $id = (string)$id;
                if (in_array($id, $known, true) && !in_array($id, $clean, true)) {
                    $clean[] = $id;
                }
            }
        }
        return $clean;
    }
}
