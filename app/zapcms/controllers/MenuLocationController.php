<?php

namespace zapcms\controllers;

use zapcms\controllers\AdminController;
use zapcms\models\AdminLog;
use zapcms\services\Menu;
use zapcms\services\MenuLocation;
use zapcms\services\MenuItem;
use zap\DB;
use zap\http\Request;
use zap\http\Response;
use zap\view\View;

/*
 * WordPress 式菜单管理：菜单实体（可自由创建）+ 菜单项构建器 + 位置分配
 */
class MenuLocationController extends AdminController
{
    public function index()
    {
        $data = [];
        $data['locations'] = MenuLocation::instance()->all();
        $data['menus']     = Menu::instance()->all();

        $menuId = intval(Request::get('menu_id', 0));
        if ($menuId <= 0 && !empty($data['menus'])) {
            $menuId = (int) $data['menus'][0]['id'];
        }
        $data['current_menu'] = $menuId > 0 ? Menu::instance()->get($menuId) : null;
        if ($data['current_menu']) {
            $data += $this->prepareBuilderData((int) $data['current_menu']['id']);
        }

        // 「管理菜单」列表所需：每个菜单的菜单项数量 + 已分配的位置标题
        $data['menu_item_counts'] = [];
        foreach ($data['menus'] as $m) {
            $data['menu_item_counts'][(int) $m['id']] =
                (int) DB::table('menu_items')->where('menu_id', (int) $m['id'])->count();
        }
        $data['menu_locations'] = [];
        foreach ($data['locations'] as $loc) {
            $mid = (int) ($loc['menu_id'] ?? 0);
            if ($mid > 0) {
                $data['menu_locations'][$mid][] = $loc['title'];
            }
        }

        // 默认激活的 tab（edit=编辑菜单 / locations=管理位置；list 仅兼容旧链接）
        $tab = strtolower((string) Request::get('tab', 'edit'));
        if (!in_array($tab, ['list', 'edit', 'locations'], true)) {
            $tab = 'edit';
        }
        $data['active_tab'] = $tab;

        $data['page_title'] = '菜单';
        $data['breadcrumbs'] = [
            ['title' => '控制台', 'url' => url_action('Index')],
            ['title' => '外观'],
            ['title' => '菜单'],
        ];
        View::render('menu_location.index', $data);
    }

    /**
     * 组装构建器片段所需数据（index 整页与 builder 片段共用）
     */
    protected function prepareBuilderData(int $menuId): array
    {
        $items = MenuItem::instance()->all($menuId);
        // all() 按 parent_id 排序会把所有子级排到末尾，导致构建器里子菜单
        // 不嵌套在父项下方；这里重排为树序（深度优先），子项紧跟父项。
        $items = $this->sortTreeOrder($items);

        return [
            'items'    => $items,
            'resolved' => MenuItem::instance()->resolveBatch($items),
        ];
    }

    public function form()
    {
        $id = intval(Request::get('id', 0));
        $data['location'] = $id ? MenuLocation::instance()->get($id) : [];
        View::render('menu_location.form', $data);
    }

    /**
     * 列表片段（供栏目管理页抽屉内 ajax 刷新）
     */
    public function listFragment()
    {
        $data['locations'] = MenuLocation::instance()->all();
        View::render('menu_location._list', $data);
    }

    public function save()
    {
        $id          = intval(Request::post('id', 0));
        $title       = trim((string) Request::post('title', ''));
        $key         = trim((string) Request::post('key', ''));
        $description = trim((string) Request::post('description', ''));
        $sortOrder   = intval(Request::post('sort_order', 0));

        if ($title === '') {
            Response::json(['code' => 1, 'msg' => '请填写位置名称']);
            return;
        }
        if ($key === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $key)) {
            Response::json(['code' => 1, 'msg' => '位置标识只能包含字母、数字、下划线和连字符']);
            return;
        }
        $existing = MenuLocation::instance()->getByKey($key);
        if ($existing && (int) $existing['id'] !== $id) {
            Response::json(['code' => 1, 'msg' => '位置标识已存在']);
            return;
        }

        if ($id) {
            MenuLocation::instance()->update([
                'title'       => $title,
                'key'         => $key,
                'description' => $description,
                'sort_order'  => $sortOrder,
            ], $id);
            $msg = '编辑菜单位置';
        } else {
            MenuLocation::instance()->add([
                'title'       => $title,
                'key'         => $key,
                'description' => $description,
                'sort_order'  => $sortOrder,
                'is_system'   => 0,
            ]);
            $msg = '新增菜单位置';
        }

        AdminLog::info(AdminLog::CATEGORY_SYSTEM, $msg, "位置：{$title} ({$key})");
        Response::json(['code' => 0, 'msg' => '保存成功']);
    }

    public function remove()
    {
        $id  = intval(Request::post('id', 0));
        $row = MenuLocation::instance()->get($id);
        if (empty($row)) {
            Response::json(['code' => 1, 'msg' => '位置不存在']);
            return;
        }
        if (!empty($row['is_system'])) {
            Response::json(['code' => 1, 'msg' => '系统内置位置不可删除']);
            return;
        }
        MenuLocation::instance()->remove($id);
        AdminLog::info(AdminLog::CATEGORY_SYSTEM, '删除菜单位置', "位置：{$row['title']} ({$row['key']})");
        Response::json(['code' => 0, 'msg' => '删除成功']);
    }

    /**
     * 菜单构建器片段：按菜单 id（或位置 key，自动解析为分配的菜单）返回编辑片段
     */
    public function builder()
    {
        $menuId = intval(Request::get('menu_id', 0));
        $key    = trim((string) Request::get('key', ''));
        if ($menuId <= 0 && $key === '') {
            // 容错：兼容客户端误用 POST 传参
            $menuId = intval(Request::post('menu_id', 0));
            $key    = trim((string) Request::post('key', ''));
        }
        if ($menuId <= 0 && $key !== '') {
            $menuId = MenuItem::instance()->resolveMenuId($key);
        }
        $menu = $menuId > 0 ? Menu::instance()->get($menuId) : null;
        if (empty($menu)) {
            Response::json(['code' => 1, 'msg' => '菜单不存在']);
            return;
        }

        $data = $this->prepareBuilderData($menuId);
        $data['menu']       = $menu;
        $data['locations']  = MenuLocation::instance()->all();
        View::render('menu_location._builder', $data);
    }

    /**
     * 把扁平菜单项重排为树序（深度优先）：子项紧跟其父项，保证构建器缩进可读。
     *
     * 孤儿节点（父级已不存在）追加在末尾，避免丢失显示。
     */
    protected function sortTreeOrder(array $items): array
    {
        $byParent = [];
        foreach ($items as $it) {
            $byParent[(int) $it['parent_id']][] = $it;
        }

        $out = [];
        $walk = function (int $pid) use (&$walk, &$out, $byParent): void {
            foreach ($byParent[$pid] ?? [] as $child) {
                $out[] = $child;
                $walk((int) $child['id']);
            }
        };
        $walk(0);

        if (count($out) < count($items)) {
            $seen = [];
            foreach ($out as $o) {
                $seen[(int) $o['id']] = true;
            }
            foreach ($items as $it) {
                if (!isset($seen[(int) $it['id']])) {
                    $out[] = $it;
                }
            }
        }
        return $out;
    }

    /**
     * 新增 / 编辑菜单项
     */
    /**
     * 新增 / 编辑菜单项
     *
     * 采用「局部更新」：仅写入 POST 中出现的字段，避免改文案时把 parent_id / sort_order
     * 误清零。mega-menu 富字段（icon/image/badge/css_class/column_break）也在此接收。
     */
    public function saveItem()
    {
        $id     = intval(Request::post('id', 0));
        $menuId = intval(Request::post('menu_id', 0));
        if ($menuId <= 0) {
            // 兼容旧调用：按位置 key 解析（未分配时自动创建同名菜单）
            $key = trim((string) Request::post('location_key', ''));
            if ($key !== '') {
                $menuId = MenuItem::instance()->menuIdForLocation($key);
            }
        }
        if ($menuId <= 0) {
            Response::json(['code' => 1, 'msg' => '缺少菜单']);
            return;
        }

        $existing = null;
        if ($id > 0) {
            $existing = MenuItem::instance()->get($id);
            if (empty($existing)) {
                Response::json(['code' => 1, 'msg' => '菜单项不存在']);
                return;
            }
        }

        // 仅当字段真正出现在 POST 中才写入（兼容逐个字段的异步保存）
        $post = $_POST ?: [];
        $has  = static function (string $k) use ($post): bool {
            return array_key_exists($k, $post);
        };

        // 类型 / 目标：新增必填；编辑沿用已有记录
        $itemType = $existing ? $existing['item_type'] : trim((string) Request::post('item_type', ''));
        $refId    = $existing ? (int) $existing['ref_id'] : intval(Request::post('ref_id', 0));
        $url      = $existing ? (string) ($existing['url'] ?? '') : trim((string) Request::post('url', ''));

        if ($id === 0) {
            if (!in_array($itemType, ['catalog', 'node', 'custom_link'], true)) {
                Response::json(['code' => 1, 'msg' => '无效的菜单项类型']);
                return;
            }
            if ($itemType === 'custom_link' && $url === '') {
                Response::json(['code' => 1, 'msg' => '请填写自定义链接地址']);
                return;
            }
            if ($itemType !== 'custom_link' && $refId <= 0) {
                Response::json(['code' => 1, 'msg' => '请选择要添加的目标']);
                return;
            }
        }

        $data = [
            'menu_id'      => $menuId,
            // menu_items.location_key 为 NOT NULL 且无默认值；菜单项现归属菜单，
            // 该列仅作历史兼容，统一写空串。
            'location_key' => '',
            'item_type'    => $itemType,
            'ref_id'       => $itemType === 'custom_link' ? null : $refId,
            'url'          => $itemType === 'custom_link' ? $url : null,
        ];

        if ($has('title')) {
            $v = trim((string) Request::post('title', ''));
            $data['title'] = $v === '' ? null : $v;
        }
        if ($has('link_target')) {
            $v = trim((string) Request::post('link_target', ''));
            $data['link_target'] = $v === '' ? null : $v;
        }
        if ($has('parent_id')) {
            $data['parent_id'] = intval(Request::post('parent_id', 0));
        }
        if ($has('sort_order')) {
            $data['sort_order'] = intval(Request::post('sort_order', 0));
        }
        // mega-menu 富字段
        if ($has('icon'))         $data['icon'] = trim((string) Request::post('icon', ''));
        if ($has('image'))        $data['image'] = trim((string) Request::post('image', ''));
        if ($has('badge'))        $data['badge'] = trim((string) Request::post('badge', ''));
        if ($has('css_class'))    $data['css_class'] = trim((string) Request::post('css_class', ''));
        if ($has('column_break')) $data['column_break'] = intval(Request::post('column_break', 0));

        // 父级不可选为自身子树（防环）；仅当 parent_id 被修改时校验
        if ($id > 0 && isset($data['parent_id']) && $data['parent_id'] > 0) {
            $all = MenuItem::instance()->all($menuId);
            if (isset(MenuItem::instance()->subtreeIds($all, $id)[$data['parent_id']])) {
                Response::json(['code' => 1, 'msg' => '父级不能选择自身或其子项']);
                return;
            }
        }

        if ($id > 0) {
            MenuItem::instance()->update($data, $id);
            $msg = '编辑菜单项';
        } else {
            MenuItem::instance()->add($data);
            $msg = '新增菜单项';
        }
        AdminLog::info(AdminLog::CATEGORY_SYSTEM, $msg, "菜单：{$menuId}");
        MenuItem::instance()->clearMenuCache();
        Response::json(['code' => 0, 'msg' => '保存成功']);
    }

    /**
     * 创建 / 重命名菜单
     */
    public function saveMenu()
    {
        $id    = intval(Request::post('id', 0));
        $title = trim((string) Request::post('title', ''));
        if ($title === '') {
            Response::json(['code' => 1, 'msg' => '请填写菜单名称']);
            return;
        }

        if ($id > 0) {
            if (empty(Menu::instance()->get($id))) {
                Response::json(['code' => 1, 'msg' => '菜单不存在']);
                return;
            }
            Menu::instance()->rename($id, $title);
            $msg = '重命名菜单';
        } else {
            $id = Menu::instance()->add($title);
            $msg = '创建菜单';
        }

        AdminLog::info(AdminLog::CATEGORY_SYSTEM, $msg, "菜单：{$title}");
        MenuItem::instance()->clearMenuCache();
        Response::json(['code' => 0, 'msg' => '保存成功', 'menu_id' => $id]);
    }

    /**
     * 删除菜单（其下菜单项一并删除，引用它的位置恢复为未分配）
     */
    public function deleteMenu()
    {
        $id = intval(Request::post('id', 0));
        $menu = Menu::instance()->get($id);
        if (empty($menu)) {
            Response::json(['code' => 1, 'msg' => '菜单不存在']);
            return;
        }
        Menu::instance()->remove($id);
        AdminLog::info(AdminLog::CATEGORY_SYSTEM, '删除菜单', "菜单：{$menu['title']}");
        Response::json(['code' => 0, 'msg' => '删除成功']);
    }

    /**
     * 把菜单分配到位置
     */
    public function assign()
    {
        $locationId = intval(Request::post('location_id', 0));
        $menuId     = intval(Request::post('menu_id', 0));
        $loc = MenuLocation::instance()->get($locationId);
        if (empty($loc)) {
            Response::json(['code' => 1, 'msg' => '位置不存在']);
            return;
        }
        if ($menuId > 0 && empty(Menu::instance()->get($menuId))) {
            Response::json(['code' => 1, 'msg' => '菜单不存在']);
            return;
        }
        MenuLocation::instance()->assignMenu($locationId, $menuId);
        AdminLog::info(AdminLog::CATEGORY_SYSTEM, '菜单位置分配', "位置：{$loc['title']}");
        Response::json(['code' => 0, 'msg' => '分配已保存']);
    }

    /**
     * 删除菜单项（级联子树）
     */
    public function removeItem()
    {
        $id = intval(Request::post('id', 0));
        if ($id <= 0) {
            Response::json(['code' => 1, 'msg' => '参数错误']);
            return;
        }
        $item = MenuItem::instance()->get($id);
        MenuItem::instance()->remove($id);
        MenuItem::instance()->clearMenuCache();
        AdminLog::info(AdminLog::CATEGORY_SYSTEM, '删除菜单项', '菜单项：' . ($item['title'] ?? "ID {$id}") . "（ID {$id}，含子树）");
        Response::json(['code' => 0, 'msg' => '删除成功']);
    }

    /**
     * 批量保存排序与层级
     *
     * @param array items [{id, parent_id, sort_order}]
     */
    public function reorderItems()
    {
        $raw = Request::post('items', []);
        if (!is_array($raw)) {
            Response::json(['code' => 1, 'msg' => '参数错误']);
            return;
        }
        $orderMap = [];
        foreach ($raw as $row) {
            $orderMap[(int) $row['id']] = [
                'parent_id'  => (int) ($row['parent_id'] ?? 0),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ];
        }

        // 防环校验：任意项的父级链不允许回到自身
        foreach ($orderMap as $id => $v) {
            $pid   = $v['parent_id'];
            $guard = 0;
            while ($pid > 0 && $guard++ < 100) {
                if ($pid === $id) {
                    Response::json(['code' => 1, 'msg' => '层级设置成环，已取消本次排序保存']);
                    return;
                }
                if (!isset($orderMap[$pid])) { break; } // 父级不在本次提交内，视为顶级链终止
                $pid = $orderMap[$pid]['parent_id'];
            }
        }

        MenuItem::instance()->reorder($orderMap);
        MenuItem::instance()->clearMenuCache();
        AdminLog::info(AdminLog::CATEGORY_SYSTEM, '菜单项排序', '批量保存 ' . count($orderMap) . ' 个菜单项的排序与层级');
        Response::json(['code' => 0, 'msg' => '排序已保存']);
    }
}
