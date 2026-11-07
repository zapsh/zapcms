<?php

namespace zapcms\controllers;

use zap\http\Request;
use zap\http\Response;
use zap\view\View;
use zapcms\models\AdminLog;
use zapcms\services\WidgetManager;

/**
 * 小工具管理（外观 > 小工具）
 */
class WidgetController extends AdminController
{
    public function index()
    {
        $manager = WidgetManager::instance();

        $data['areas']      = $manager->areas();
        $data['registered'] = $manager->registered();

        $data['page_title'] = '小工具';
        $data['breadcrumbs'] = [
            ['title' => '控制台', 'url' => url_action('Index')],
            ['title' => '外观'],
            ['title' => '小工具'],
        ];

        View::render('widgets.index', $data);
    }

    /**
     * 某区域的小工具列表（AJAX）
     */
    public function areaList()
    {
        $area    = trim((string) Request::get('area', ''));
        $manager = WidgetManager::instance();
        $registered = $manager->registered();

        $rows = $manager->all($area);
        foreach ($rows as &$row) {
            $k = (string) $row['widget_key'];
            $row['widget_title'] = $registered[$k]['title'] ?? $k;
            $row['widget_icon']  = $registered[$k]['icon']  ?? 'fa-solid fa-puzzle-piece';
            $row['config'] = json_decode((string) ($row['config'] ?? ''), true) ?: [];
        }
        unset($row);

        Response::json(['code' => 0, 'data' => $rows]);
    }

    /**
     * 新增 / 编辑小工具（AJAX）
     */
    public function save()
    {
        $id    = (int) Request::post('id', 0);
        $key   = trim((string) Request::post('widget_key', ''));
        $area  = trim((string) Request::post('area', ''));
        $title = trim((string) Request::post('title', ''));
        $config = Request::post('config', []);
        if (!is_array($config)) {
            $config = [];
        }

        $manager = WidgetManager::instance();
        if ($manager->getClass($key) === null) {
            Response::json(['code' => 1, 'msg' => '未知的小工具类型']);
            return;
        }
        if ($area === '') {
            Response::json(['code' => 1, 'msg' => '请选择区域']);
            return;
        }

        $data = [
            'widget_key' => $key,
            'area'       => $area,
            'title'      => $title,
            'config'     => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        if ($id > 0) {
            $manager->update($data, $id);
            $msg = '编辑小工具';
        } else {
            $data['sort_order'] = count($manager->all($area)) + 1;
            $data['status']     = 1;
            $manager->add($data);
            $msg = '新增小工具';
        }

        AdminLog::info(AdminLog::CATEGORY_SYSTEM, $msg, "区域：{$area} / 类型：{$key}");
        Response::json(['code' => 0, 'msg' => '保存成功']);
    }

    /**
     * 排序（AJAX，传入完整 id 顺序）
     */
    public function reorder()
    {
        $area = trim((string) Request::post('area', ''));
        $ids  = Request::post('ids', []);
        if (!is_array($ids)) {
            $ids = [];
        }
        WidgetManager::instance()->reorder($area, array_map('intval', $ids));
        Response::json(['code' => 0, 'msg' => '已更新排序']);
    }

    /**
     * 删除（AJAX）
     */
    public function remove()
    {
        $id = (int) Request::post('id', 0);
        WidgetManager::instance()->remove($id);
        Response::json(['code' => 0, 'msg' => '已删除']);
    }
}
