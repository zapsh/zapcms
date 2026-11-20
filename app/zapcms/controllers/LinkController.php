<?php

namespace zapcms\controllers;

use zap\http\Request;
use zap\http\Response;
use zap\view\View;
use zapcms\models\AdminLog;
use zapcms\services\LinkManager;

/**
 * 链接/广告位管理（外观 > 链接管理）
 *
 * 全部数据存于系统 options 表，不新增数据表：
 *   - 分组：轮播(slide) / 友链(link)，每组分唯一 code，供前台 ad_items(code) 调用
 *   - 条目：图片、链接、打开方式、标题、描述、状态、排序、生效时间段
 */
class LinkController extends AdminController
{
    protected string $permKey = 'admin_menu_62';

    public function index()
    {
        $this->requirePerm('view');
        $data['groups']      = LinkManager::groups();
        $data['types']       = LinkManager::types();
        $data['itemsByGroup'] = [];
        foreach ($data['groups'] as $g) {
            $data['itemsByGroup'][$g['code']] = LinkManager::items($g['code']);
        }

        $data['page_title'] = '链接管理';
        $data['breadcrumbs'] = [
            ['title' => '控制台', 'url' => url_action('Index')],
            ['title' => '外观'],
            ['title' => '链接管理'],
        ];

        View::render('link.index', $data);
    }

    /** 保存整页（分组 + 各组条目一次性提交）AJAX */
    public function save()
    {
        $this->requirePerm('edit');

        $groupsRaw = Request::post('groups', []);
        $itemsRaw  = Request::post('items', []);
        if (!is_array($groupsRaw)) {
            $groupsRaw = [];
        }
        if (!is_array($itemsRaw)) {
            $itemsRaw = [];
        }

        $types = LinkManager::types();
        $groups = [];
        $sort = 1;
        foreach ($groupsRaw as $g) {
            if (!is_array($g)) {
                continue;
            }
            $name = trim((string)($g['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $code = trim((string)($g['code'] ?? ''));
            $type = (string)($g['type'] ?? LinkManager::TYPE_LINK);
            if (!isset($types[$type])) {
                $type = LinkManager::TYPE_LINK;
            }
            $groups[] = [
                'code'   => $code !== '' ? $code : ('g' . substr((string)time(), -6) . '_' . $sort),
                'name'   => $name,
                'type'   => $type,
                'status' => !empty($g['status']) ? '1' : '0',
                'sort'   => $sort++,
            ];
        }

        // 新分组：前台表单 code 以 __new_ 开头，生成真实 code 并迁移其条目键
        $rename = [];
        foreach ($groups as &$g) {
            if (str_starts_with($g['code'], '__new_')) {
                $newCode = 'g' . substr((string)time(), -6) . '_' . $g['sort'];
                $rename[$g['code']] = $newCode;
                $g['code'] = $newCode;
            }
        }
        unset($g);
        if ($rename !== []) {
            $fixed = [];
            foreach ($itemsRaw as $oldCode => $items) {
                $fixed[$rename[$oldCode] ?? $oldCode] = $items;
            }
            $itemsRaw = $fixed;
        }

        // 规范条目字段
        $itemsByGroup = [];
        foreach ($itemsRaw as $code => $items) {
            $code = (string)$code;
            if (!is_array($items)) {
                continue;
            }
            $rows = [];
            $i = 1;
            foreach ($items as $it) {
                if (!is_array($it)) {
                    continue;
                }
                $img = trim((string)($it['img_path'] ?? ''));
                $link = trim((string)($it['link'] ?? ''));
                $title = trim((string)($it['title'] ?? ''));
                if ($img === '' && $link === '' && $title === '') {
                    continue;
                }
                $rows[] = [
                    'title'      => $title,
                    'img_path'   => $img,
                    'link'       => $link,
                    'target'     => (($it['target'] ?? '_self') === '_blank') ? '_blank' : '_self',
                    'desc'       => trim((string)($it['desc'] ?? '')),
                    'status'     => !empty($it['status']) ? '1' : '0',
                    'sort'       => $i++,
                    'start_time' => trim((string)($it['start_time'] ?? '')),
                    'end_time'   => trim((string)($it['end_time'] ?? '')),
                ];
            }
            $itemsByGroup[$code] = $rows;
        }

        LinkManager::saveAll($groups, $itemsByGroup);
        AdminLog::info(AdminLog::CATEGORY_SYSTEM, '保存链接/广告位', '分组数：' . count($groups));
        Response::json(['code' => 0, 'msg' => '保存成功']);
    }

    /** 新增空分组（AJAX，返回含真实 code 的分组，供前端立即编辑） */
    public function addGroup()
    {
        $this->requirePerm('edit');
        $name = trim((string) Request::post('name', ''));
        $type = (string) Request::post('type', LinkManager::TYPE_LINK);
        $code = (string) Request::post('code', '');
        $group = LinkManager::addGroup($name, $type, 1, $code);
        if ($group === null) {
            Response::json(['code' => 1, 'msg' => '参数错误（名称不能为空，code 需为唯一字母数字下划线）']);
            return;
        }
        Response::json(['code' => 0, 'msg' => '已添加', 'data' => $group]);
    }

    /** 删除分组（AJAX） */
    public function removeGroup()
    {
        $this->requirePerm('delete');
        $code = trim((string) Request::post('code', ''));
        if ($code !== '') {
            LinkManager::removeGroup($code);
            AdminLog::info(AdminLog::CATEGORY_SYSTEM, '删除链接分组', 'code：' . $code);
        }
        Response::json(['code' => 0, 'msg' => '已删除']);
    }
}
