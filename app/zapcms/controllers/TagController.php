<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 *
 */

namespace zapcms\controllers;

use zapcms\controllers\AdminController;
use zapcms\models\AdminLog;
use zapcms\services\SlugHelper;
use zapcms\services\Tag;
use zap\DB;
use zap\http\Request;
use zap\http\Response;
use zap\view\View;

/**
 * 标签管理（内容管理 › 标签管理）
 *
 * 复用统一分类法：标签 = node 表中 node_type='tag' 的节点（扁平，parent_id=0），
 * 内容与标签的关联由 node_relation(catalog_id=标签节点id, node_id) 承载。
 * node_type='tag' 是系统保留类型（前台路由/sitemap 已内置），故以独立后台控制器
 * 挂载到 /z-admin/tag，对应菜单 admin_menu_61。
 */
class TagController extends AdminController
{
    /** 菜单权限键：admin_menu_61，extras 子项 view/add/edit/delete */
    protected string $permKey = 'admin_menu_61';

    public function index()
    {
        $this->requirePerm('view', '查看');

        $search = trim((string)Request::get('search', ''));
        $builder = DB::table('node')->where('node_type', 'tag');
        if ($search !== '') {
            $builder->where('title', 'LIKE', "%{$search}%");
        }
        $all = $builder->orderBy('id', 'DESC')->fetchAll() ?: [];

        // 使用次数实时统计（标签量级小，逐个 count 足够）
        $rows = [];
        foreach ($all as $row) {
            $row['usage_count'] = Tag::getUsageCount((int)$row['id']);
            $rows[] = $row;
        }

        $total = count($rows);
        $limit = 20;
        $page = max(1, intval(Request::get('page', 1)));
        $rows = array_slice($rows, ($page - 1) * $limit, $limit);

        $data = [
            'rows'        => $rows,
            'search'      => $search,
            'total'       => $total,
            'page_title'  => '标签管理',
            'breadcrumbs' => [
                ['title' => '控制台', 'url' => url_action('Index')],
                ['title' => '内容管理', 'url' => url_action('Node')],
                ['title' => '标签管理'],
            ],
        ];
        View::render('tag.index', $data);
    }

    /**
     * 标签编辑/新增表单（模态框内容，无独立布局）
     */
    public function form()
    {
        $this->requirePerm('view', '查看');

        $id = intval(Request::get('id', 0));
        $tag = $id > 0
            ? (DB::table('node')->where('id', $id)->where('node_type', 'tag')->first() ?: [])
            : [];

        View::render('tag.form', ['tag' => $tag ?: []]);
    }

    public function save()
    {
        $id = intval(Request::post('tag_id', 0));
        $this->requirePerm($id ? 'edit' : 'add', '保存');

        $title = trim((string)Request::post('title', ''));
        if ($title === '') {
            Response::json(['code' => 1, 'msg' => '标签名称不能为空']);
            return;
        }

        // 同名复用同一标签节点
        $exists = DB::table('node')->where('node_type', 'tag')->where('title', $title)->first();
        if ($exists && (int)$exists['id'] !== $id) {
            Response::json(['code' => 1, 'msg' => '标签「' . $title . '」已存在']);
            return;
        }

        $slug = trim((string)Request::post('slug', ''));
        if ($slug === '') {
            $slug = SlugHelper::generate($title);
        }
        if ($slug === '' || DB::table('node')->where('slug', $slug)->where('id', '!=', $id)->exists()) {
            $slug = 'tag-' . substr(md5($title . microtime()), 0, 8);
        }

        if ($id > 0) {
            DB::table('node')->where('id', $id)->where('node_type', 'tag')
                ->update(['title' => $title, 'slug' => $slug, 'update_time' => time()]);
            AdminLog::info(AdminLog::CATEGORY_CONTENT, '编辑标签', '标签：' . $title . ' #' . $id);
        } else {
            $now = time();
            $id = (int)DB::table('node')->insert([
                'author_id'  => 0,
                'node_type'  => 'tag',
                'title'      => $title,
                'slug'       => $slug,
                'status'     => 'publish',
                'sort_order' => 0,
                'pub_time'   => $now,
                'add_time'   => $now,
                'update_time'=> $now,
                'parent_id'  => 0,
                'level'      => 1,
            ]);
            if ($id > 0) {
                DB::table('node')->where('id', $id)->update(['path' => "{$id},"]);
            }
            AdminLog::info(AdminLog::CATEGORY_CONTENT, '新增标签', '标签：' . $title . ' #' . $id);
        }

        Response::json(['code' => 0, 'msg' => '保存成功', 'tag_id' => $id]);
    }

    public function remove()
    {
        $this->requirePerm('delete', '删除');

        $id = intval(Request::post('tag_id', 0));
        if ($id <= 0) {
            Response::json(['code' => 1, 'msg' => '参数错误']);
            return;
        }

        DB::beginTransaction();
        try {
            // 级联清理关联行（与 Catalog::remove 同策略）
            DB::table('node_relation')->where('catalog_id', $id)->delete();
            DB::table('node')->where('id', $id)->where('node_type', 'tag')->delete();
            AdminLog::info(AdminLog::CATEGORY_CONTENT, '删除标签', '标签 #' . $id);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Response::json(['code' => 1, 'msg' => '删除失败：' . $e->getMessage()]);
            return;
        }

        Response::json(['code' => 0, 'msg' => '删除成功']);
    }

    /**
     * 标签联想（文章表单自动补全）
     */
    public function suggest()
    {
        $keyword = trim((string)Request::get('keyword', ''));
        Response::json(['code' => 0, 'list' => Tag::suggest($keyword, 10)]);
    }
}
