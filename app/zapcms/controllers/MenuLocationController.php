<?php

namespace zapcms\controllers;

use zapcms\controllers\AdminController;
use zapcms\models\AdminLog;
use zapcms\services\MenuLocation;
use zapcms\services\MenuItem;
use zap\http\Request;
use zap\http\Response;
use zap\view\View;

/*
 * 菜单位置管理（自定义导航槽位）
 */
class MenuLocationController extends AdminController
{
    public function index()
    {
        $data = [];
        $data['locations'] = MenuLocation::instance()->all();
        $data['page_title'] = '菜单位置';
        $data['breadcrumbs'] = [
            ['title' => '控制台', 'url' => url_action('Index')],
            ['title' => '设置', 'url' => url_action('System@settings')],
            ['title' => '菜单位置'],
        ];
        View::render('menu_location.index', $data);
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
     * 菜单构建器（抽屉二级）：返回某位置的菜单项列表片段
     */
    public function builder()
    {
        $key = trim((string) Request::get('key', ''));
        if ($key === '') {
            Response::json(['code' => 1, 'msg' => '缺少位置参数']);
            return;
        }
        $items = MenuItem::instance()->all($key);
        // all() 按 parent_id 排序会把所有子级排到末尾，导致构建器里子菜单
        // 不嵌套在父项下方；这里重排为树序（深度优先），子项紧跟父项。
        $items = $this->sortTreeOrder($items);

        // 为每个菜单项预计算可选父级（排除自身及其子树，防止成环）与解析结果
        $parentOptions = [];
        foreach ($items as $it) {
            $exclude = MenuItem::instance()->subtreeIds($items, (int) $it['id']);
            $exclude[(int) $it['id']] = true;
            $opts = [];
            foreach ($items as $o) {
                if (isset($exclude[(int) $o['id']])) {
                    continue;
                }
                $opts[] = $o;
            }
            $parentOptions[(int) $it['id']] = $opts;
        }
        $resolved = MenuItem::instance()->resolveBatch($items);

        $data['location_key']   = $key;
        $data['items']          = $items;
        $data['parent_options'] = $parentOptions;
        $data['resolved']       = $resolved;
        $data['locations']      = MenuLocation::instance()->all();
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
        $id          = intval(Request::post('id', 0));
        $locationKey = trim((string) Request::post('location_key', ''));
        if ($locationKey === '') {
            Response::json(['code' => 1, 'msg' => '缺少位置']);
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
            'location_key' => $locationKey,
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
            $all = MenuItem::instance()->all($locationKey);
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
        AdminLog::info(AdminLog::CATEGORY_SYSTEM, $msg, "位置：{$locationKey}");
        MenuItem::instance()->clearMenuCache();
        Response::json(['code' => 0, 'msg' => '保存成功']);
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
        MenuItem::instance()->remove($id);
        MenuItem::instance()->clearMenuCache();
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
        Response::json(['code' => 0, 'msg' => '排序已保存']);
    }
}
