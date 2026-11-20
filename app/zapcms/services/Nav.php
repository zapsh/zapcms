<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS
 */

namespace zapcms\services;

use zapcms\models\NodeRelation;

/**
 * 前台导航惰性服务。
 *
 * 目标：把 breadcrumb / category_nav（侧栏）/ 顶部·底部·左侧菜单的取数
 * 从 controller 统一预取中解放出来，改为「主题渲染时按需调用」，
 * 并提供请求级缓存避免同一请求内重复计算。
 *
 * 主题中一行调用即可：
 *   PHP  : <?= breadcrumb() ?>  <?= category_nav() ?>  <?= nav_menu('top') ?>
 *   Twig : {{ breadcrumb() }}   {{ category_nav() }}   {{ nav_menu('top') }}
 */
class Nav
{
    /** @var array<string,mixed> 请求级缓存 */
    private static array $cache = [];

    /** 清空请求级缓存（写入/语言切换后如需刷新可调用） */
    public static function forget(): void
    {
        self::$cache = [];
    }

    private static function remember(string $key, callable $factory): mixed
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }
        return self::$cache[$key] = $factory();
    }

    // ───────────────────────────── 面包屑 ─────────────────────────────

    /**
     * 面包屑项（惰性推导）：list<array{title,url,active}>。
     *
     * 兼容：若外部（controller / mod）已向 BreadCrumb 累加过内容，直接复用；
     * 否则按当前 pageState 自动推导「首页 + 栏目路径 + 当前节点」。
     */
    public static function breadcrumbItems(): array
    {
        return self::remember('breadcrumb_items', function () {
            $bc = BreadCrumb::instance();
            if (!$bc->isEmpty()) {
                return $bc->toArray();
            }

            $state   = pageState();
            $items   = [];
            $isHome  = (bool) ($state['isHome'] ?? false);
            $isSearch = (bool) ($state['isSearch'] ?? false);

            if (!$isHome) {
                $items[] = ['title' => __t('front.home', '首页'), 'url' => base_url('/'), 'active' => false];
            }

            if ($isSearch) {
                $items[] = ['title' => __t('front.title_search_result', '搜索结果'), 'url' => '#', 'active' => true];
                return $items;
            }

            if ($isHome) {
                return $items;
            }

            // 栏目路径（内容页/栏目页通用，惰性取）
            foreach (self::catalogPaths() as $cat) {
                $items[] = [
                    'title'  => (string) ($cat['title'] ?? ''),
                    'url'    => smart_node_url([
                        'node_type' => 'category',
                        'slug'      => $cat['slug'] ?? '',
                        'id'        => $cat['id'] ?? 0,
                    ]),
                    'active' => false,
                ];
            }

            // 当前节点（栏目页的 node 即栏目，避免与路径末端重复）
            $node = $state['node'] ?? null;
            if (is_array($node) && !empty($node['title'])) {
                $nodeType = (string) ($node['node_type'] ?? '');
                $paths    = self::catalogPaths();
                $last     = $paths === [] ? null : end($paths);
                $isTerm   = in_array($nodeType, ['category', 'tag'], true);
                $sameAsLast = $isTerm && $last && (int) ($last['id'] ?? 0) === (int) ($node['id'] ?? 0);
                if (!$sameAsLast) {
                    $items[] = [
                        'title'  => (string) $node['title'],
                        'url'    => smart_node_url($node),
                        'active' => true,
                    ];
                } elseif (!empty($items)) {
                    // 栏目页：把路径末端标记为当前
                    $items[count($items) - 1]['active'] = true;
                }
            }

            return $items;
        });
    }

    /**
     * 直接渲染面包屑 HTML（不依赖 controller 预取）。
     */
    public static function breadcrumb(): string
    {
        $items = self::breadcrumbItems();
        if ($items === []) {
            return '';
        }

        $divider = "--bs-breadcrumb-divider: url('data:image/svg+xml,"
            . "%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%228%22 height=%228%22"
            . "%3E%3Cpath d=%22M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z%22"
            . " fill=%22%236c757d%22/%3E%3C/svg%3E');";

        $html  = "<nav style=\"{$divider}\" aria-label=\"breadcrumb\"><ol class=\"breadcrumb mb-0\">";
        $last  = array_key_last($items);
        foreach ($items as $i => $item) {
            $title  = htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8');
            $url    = htmlspecialchars((string) ($item['url'] ?? '#'), ENT_QUOTES, 'UTF-8');
            $active = ($i === $last) || !empty($item['active']);
            if ($active) {
                $html .= "<li class=\"breadcrumb-item active\" aria-current=\"page\"><span class=\"text-secondary\">{$title}</span></li>";
            } else {
                $html .= "<li class=\"breadcrumb-item\"><a href=\"{$url}\">{$title}</a></li>";
            }
        }
        return $html . '</ol></nav>';
    }

    // ───────────────────────────── 栏目路径 ─────────────────────────────

    /**
     * 当前位置的栏目路径（含自身，从根到叶）：list<array{id,title,slug}>。
     * 优先用 pageState 已有值；否则按节点类型惰性查询。
     */
    public static function catalogPaths(): array
    {
        $state = pageState();
        $paths = $state['catalogPaths'] ?? null;
        if (is_array($paths)) {
            return $paths;
        }

        $paths = self::remember('catalog_paths', function () use ($state) {
            $nodeId = (int) ($state['nodeId'] ?? 0);
            if ($nodeId <= 0) {
                return [];
            }
            $node     = $state['node'] ?? [];
            $nodeType = (string) ($node['node_type'] ?? '');

            if (in_array($nodeType, ['category', 'tag'], true)) {
                // 栏目页：按 path 字段取祖先链（Catalog 内部已含自身）
                $rows = Catalog::instance()->getCatalogPathById($nodeId);
            } else {
                // 内容页：按 NodeRelation 取所属栏目链
                $rows = NodeRelation::where('node_id', $nodeId)
                    ->leftJoin(['node', 'n'], 'node_relation.catalog_id=n.id')
                    ->orderByRaw('{node_relation}.level ASC')
                    ->select('n.title,n.id,n.slug')
                    ->get(FETCH_ASSOC);
            }
            return Translator::applyBatch((array) $rows, null, Translator::TYPE_NODE, ['title', 'slug']);
        });

        // 回填，供仍直接读取 pageState()->catalogPaths 的旧模板/服务使用
        $state['catalogPaths'] = $paths;
        return $paths;
    }

    // ───────────────────────────── 侧栏 / 栏目导航 ─────────────────────────────

    /**
     * 侧边栏栏目导航数据，结构 [['title'=>..., 'children'=>[...]], ...]。
     * 规则：栏目页/内容页优先「当前栏目的子栏目导航」，取不到回退后台 left 菜单；
     *      搜索页用搜索专用菜单。
     */
    public static function categoryNav(): array
    {
        return self::remember('category_nav', function () {
            $state = pageState();

            // 搜索页：用搜索专用侧栏
            if ($state['isSearch'] ?? false) {
                return $state->getSearchSidebarMenu();
            }

            $menu = $state->getCatalogSidebarMenu();
            return $menu ?: $state->getSidebarMenu('left');
        });
    }

    // ───────────────────────────── 顶部/底部/左侧菜单 ─────────────────────────────

    /**
     * 菜单树（含译文与 active 标记），惰性取 + 请求级缓存。
     *
     * @param int|string $key 菜单 id（如 4，对应 zap_menus.id）或位置 key（top / footer / left 等）
     */
    public static function menu(int|string $key = 'top'): array
    {
        $cacheKey = 'menu_' . (is_int($key) || ctype_digit((string) $key) ? 'id_' : 'key_') . $key;
        return self::remember($cacheKey, fn() => pageState()->getNavMenu($key));
    }
}
