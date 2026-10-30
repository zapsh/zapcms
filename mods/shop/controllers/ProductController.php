<?php
/**
 * 产品控制器
 *
 * Admin 路由: /z-admin/mod/shop/product/...
 * 前端路由:  /mod/shop/product/... 或 /shop/product/...（prefixes 配置后）
 */

namespace mods\shop\controllers;

use mods\shop\models\Product;
use zap\view\View;

class ProductController
{
    // ==================== 后台方法 ====================

    /**
     * 产品管理列表（后台）
     */
    public function index(): void
    {
        $page  = (int) ($_GET['page'] ?? 1);
        $size  = 20;
        $query = Product::query()->orderBy('sort DESC, id DESC');

        $total = $query->count();
        $list  = $query->limit($size, ($page - 1) * $size)->get();

        View::render('product.admin_list', [
            'list'       => $list,
            'total'      => $total,
            'page'       => $page,
            'pageSize'   => $size,
            'totalPages' => (int) ceil($total / $size),
        ]);
    }

    /**
     * 新增 / 编辑产品（后台）
     */
    public function edit(int $id = 0): void
    {
        $product = [];
        if ($id > 0) {
            $product = Product::query()->where('id', $id)->first();
            if (!$product) {
                echo '产品不存在';
                return;
            }
        }

        View::render('product.admin_form', [
            'product' => $product,
        ]);
    }

    /**
     * 保存产品（后台）
     */
    public function save(): void
    {
        $data = [
            'title'        => $_POST['title'] ?? '',
            'slug'         => $_POST['slug'] ?? '',
            'image'        => $_POST['image'] ?? '',
            'price'        => (float) ($_POST['price'] ?? 0),
            'origin_price' => (float) ($_POST['origin_price'] ?? 0),
            'stock'        => (int) ($_POST['stock'] ?? 0),
            'unit'         => $_POST['unit'] ?? '件',
            'status'       => (int) ($_POST['status'] ?? 1),
            'sort'         => (int) ($_POST['sort'] ?? 0),
            'summary'      => $_POST['summary'] ?? '',
            'content'      => $_POST['content'] ?? '',
        ];

        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            Product::query()->where('id', $id)->update($data);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
            Product::query()->insert($data);
        }

        // 不写死后台前缀，交给 Url::action 拼接（后台路径可被修改）
        $back = $_SERVER['HTTP_REFERER'] ?? url_action('mod/shop/product');
        header('Location: ' . $back);
    }

    /**
     * 删除产品（后台）
     */
    public function delete(int $id): void
    {
        if ($id > 0) {
            Product::query()->where('id', $id)->delete();
        }
        header('Location: ' . url_action('mod/shop/product'));
    }

    // ==================== 前端方法 ====================

    /**
     * 产品详情页（前端）
     */
    public function view(string $slug): void
    {
        $product = Product::findBySlug($slug);

        if (!$product) {
            header('HTTP/1.1 404 Not Found');
            echo '产品不存在';
            return;
        }

        View::render('product.detail', [
            'product' => $product,
        ]);
    }

    /**
     * 产品列表页（前端）
     */
    public function lists(): void
    {
        $page = (int) ($_GET['page'] ?? 1);
        $data = Product::getList($page, 12);

        View::render('product.list', $data);
    }
}
