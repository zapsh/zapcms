<?php

namespace app\controllers\api;

use app\ZQuery;

/**
 * 内容节点 API（示范：复用后台 RBAC 权限键 admin_menu_21）。
 *
 *  GET /api/admin/node/list?node_type=article&posts_per_page=10
 *  POST /api/admin/node/list  (JSON body 同 GET 参数)
 *
 * 鉴权：必须经会话或 Bearer Token 登录，并拥有 admin_menu_21 权限。
 */
class NodeApiController extends ApiController
{
    public function list(): void
    {
        if (!$this->requirePerm('admin_menu_21')) {
            return;
        }

        $params = $this->request()->isPost()
            ? $this->request()->all()
            : $this->request()->get();

        $query = new ZQuery($params);
        $this->ok($query->to_array());
    }
}
