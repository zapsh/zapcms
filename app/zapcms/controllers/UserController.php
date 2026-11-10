<?php

namespace zapcms\controllers;

use zapcms\auth\RBAC;
use zapcms\controllers\AdminController;
use zapcms\auth\Permissions;
use zapcms\services\Auth;
use zapcms\services\ApiAuth;
use zapcms\models\Admin;
use zapcms\models\AdminLog;
use zap\DB;
use zap\facades\Url;
use zap\helpers\Pagination;
use zap\http\Request;
use zap\http\Response;
use zap\util\Password;

class UserController extends AdminController
{

    protected string $permKey = 'admin_menu_8';

    /**
     * 后台会员管理 — 列表页
     */
    public function index()
    {
        $this->requirePerm('view');
        $keyword = Request::get('keyword', '');
        $status  = Request::get('status', '');
        $page    = max(1, (int)Request::get('page', 1));
        $perPage = 20;

        $query = DB::table('admin');

        // 搜索
        if ($keyword !== '') {
            $query->where('username', 'LIKE', "%{$keyword}%");
        }
        // 状态筛选
        if ($status !== '' && in_array($status, ['activated', 'disabled'], true)) {
            $query->where('status', $status);
        }

        $total = (int)$query->count();
        $query->orderBy('id', 'DESC');
        $users = $query->limit($perPage)->offset(($page - 1) * $perPage)->fetchAll();

        // 统计数据
        $totalAll   = DB::table('admin')->count();
        $activeCount = DB::table('admin')->where('status', 'activated')->count();
        $latestUser  = $users ? ($users[0]['username'] ?? '-') : '-';

        // 分页
        $queryParams = Request::get();
        unset($queryParams['page']);
        $pageHelper = new Pagination($page, $perPage, $queryParams);
        $pageHelper->setTotal($total);
        // 显式指定分页基准 URL，保留筛选参数，强制 ?page=N 形式，避免 path 为空时生成 "/N" 错误链接
        $pageHelper->withPath(Url::action('User@index') . '?' . ($queryParams ? http_build_query($queryParams) . '&' : '') . 'page={page}');

        // 角色列表（供 Modal 表单使用）
        $roles = DB::table('roles')->orderBy('role_id')->fetchAll();

        $data = [
            'page_title'  => '用户管理',
            'users'       => $users,
            'pageHelper'  => $pageHelper,
            'total'       => $total,
            'totalAll'    => $totalAll,
            'activeCount' => $activeCount,
            'latestUser'  => $latestUser,
            'keyword'     => $keyword,
            'status'      => $status,
            'roles'       => $roles,
        ];

        view('user.index', $data);
    }

    /**
     * 编辑表单（Modal 内嵌）
     */
    public function form()
    {
        $id         = (int)Request::get('id', 0);
        $user       = [];
        $userRoles  = [];

        if ($id > 0) {
            $user = DB::table('admin')->where('id', $id)->fetch(FETCH_ASSOC);
            if (!$user) {
                return '用户不存在';
            }
            // 获取该用户已分配的角色 ID
            $rows = DB::table('admin_roles')->where('admin_id', $id)->fetchAll();
            $userRoles = array_column($rows, 'role_id');
        } else {
            $user = [
                'id'           => 0,
                'username'     => '',
                'full_name'    => '',
                'email'        => '',
                'phone_number' => '',
                'status'       => Admin::STATUS_ACTIVATED,
            ];
        }

        view('user.form', [
            'user'       => $user,
            'user_roles' => $userRoles,
            'roles'      => DB::table('roles')->orderBy('role_id')->fetchAll(),
        ]);
    }

    /**
     * 保存 / 更新管理员（响应 Modal 表单提交）
     */
    public function saveUser()
    {
        $this->requirePerm('edit');
        if (!Request::isPost()) {
            Response::json(['code' => 1, 'msg' => '非法请求']);
        }

        $adminId   = (int)Request::post('admin_id', 0);
        $data      = Request::post('data', []);
        $userRoles = Request::post('user_roles', []);

        $username    = trim($data['username'] ?? '');
        $password    = trim($data['password'] ?? '');
        $newPassword = trim($data['new_password'] ?? '');

        // 验证
        if ($username === '') {
            Response::json(['code' => 1, 'msg' => '用户名不能为空']);
        }
        $len = mb_strlen($username);
        if ($len < 3 || $len > 20) {
            Response::json(['code' => 1, 'msg' => '用户名长度须在 3~20 位之间']);
        }

        // 检查用户名唯一性
        $exist = DB::table('admin')->where('username', $username)->fetch(FETCH_ASSOC);
        if ($exist && (int)$exist['id'] !== $adminId) {
            Response::json(['code' => 1, 'msg' => '用户名已被占用']);
        }

        // 新增时必须填写密码
        if ($adminId === 0 && $password === '') {
            Response::json(['code' => 1, 'msg' => '密码不能为空']);
        }
        if ($password !== '' && mb_strlen($password) < 6) {
            Response::json(['code' => 1, 'msg' => '密码至少需要 6 个字符']);
        }
        if ($password !== $newPassword) {
            Response::json(['code' => 1, 'msg' => '两次输入的密码不一致']);
        }

        // ── 超管保护 ─────────────────────────────────────────────
        $currentIsSuper = app()->rbac->isSuperAdmin();
        $targetIsSuper  = $adminId > 0 && DB::table('admin_roles')
            ->where('admin_id', $adminId)->where('role_id', 1)->exists();
        $newIsSuper = in_array(1, array_map('intval', (array)$userRoles), true);

        // 分配/收回超管角色、编辑超管账号，仅限超级管理员本人操作
        if (($targetIsSuper || $newIsSuper) && !$currentIsSuper) {
            AdminLog::warning(
                AdminLog::CATEGORY_SECURITY,
                '越权操作被拦截',
                '非超级管理员尝试' . ($newIsSuper ? '分配超管角色给' : '编辑超管账号') . " {$username}"
            );
            Response::json(['code' => 1, 'msg' => '只有超级管理员可以管理超级管理员账号']);
            return;
        }

        // 最后一个可登录的超管不可被降级或禁用（避免系统失去超管）
        if ($targetIsSuper) {
            $others = (int) DB::query(
                "SELECT COUNT(*) FROM {admin_roles} ar"
                . " INNER JOIN {admin} a ON a.id = ar.admin_id"
                . " WHERE ar.role_id = 1 AND ar.admin_id != ? AND a.status = ?",
                [$adminId, Admin::STATUS_ACTIVATED]
            )->fetchColumn();
            $status      = $data['status'] ?? Admin::STATUS_ACTIVATED;
            $stillSuper  = $newIsSuper && $status === Admin::STATUS_ACTIVATED;
            if ($others === 0 && !$stillSuper) {
                Response::json(['code' => 1, 'msg' => '系统至少需要保留一个可登录的超级管理员']);
                return;
            }
        }

        $now = time();
        $row = [
            'username'        => $username,
            'full_name'       => trim($data['full_name'] ?? ''),
            'email'           => trim($data['email'] ?? ''),
            'phone_number'    => trim($data['phone_number'] ?? ''),
            'status'          => $data['status'] ?? Admin::STATUS_ACTIVATED,
            'avatar_url'      => '',
            'last_ip'         => '',
            'last_access_time' => 0,
            'updated_at'      => $now,
        ];

        if ($password !== '') {
            $row['password'] = Password::hash($password);
        }

        // 同步角色关联前，先记录原有角色（用于审计对比）
        $oldRoleIds = $adminId > 0
            ? array_column(DB::table('admin_roles')->where('admin_id', $adminId)->fetchAll(), 'role_id')
            : [];

        if ($adminId > 0) {
            DB::update('admin', $row, ['id' => $adminId]);
            AdminLog::info(AdminLog::CATEGORY_USER, '修改管理员', "更新了管理员: {$username}（ID {$adminId}）");
        } else {
            $row['created_at'] = $now;
            $adminId = DB::insert('admin', $row);
            AdminLog::info(AdminLog::CATEGORY_USER, '新增管理员', "添加了管理员: {$username}（ID {$adminId}）");
        }

        // 同步角色关联
        DB::delete('admin_roles', ['admin_id' => $adminId]);
        if (!empty($userRoles)) {
            foreach ((array)$userRoles as $roleId) {
                DB::insert('admin_roles', [
                    'admin_id'        => $adminId,
                    'role_id'         => (int)$roleId,
                    'assignment_time' => $now,
                ]);
            }
        }

        // 角色变更审计（授予/收回超管角色属敏感操作，用 WARNING 级别）
        $newRoleIds = array_map('intval', (array)$userRoles);
        $granted    = array_diff($newRoleIds, array_map('intval', $oldRoleIds));
        $revoked    = array_diff(array_map('intval', $oldRoleIds), $newRoleIds);
        if ($granted !== [] || $revoked !== []) {
            $detail = sprintf(
                '用户 %s（ID %d）角色变更：授予 [%s]，收回 [%s]',
                $username, $adminId,
                implode(',', $granted) ?: '-', implode(',', $revoked) ?: '-'
            );
            if (in_array(1, $granted, true) || in_array(1, $revoked, true)) {
                AdminLog::warning(AdminLog::CATEGORY_SECURITY, '超管角色变更', $detail);
            } else {
                AdminLog::info(AdminLog::CATEGORY_USER, '用户角色变更', $detail);
            }
        }

        // 失效该用户的权限缓存
        RBAC::invalidateUser($adminId);

        Response::json(['code' => 0, 'msg' => '保存成功']);
    }

    /**
     * 删除选中的管理员
     */
    public function remove()
    {
        $this->requirePerm('delete');
        if (!Request::isPost()) {
            Response::json(['code' => 1, 'msg' => '非法请求']);
        }

        $items = Request::post('admin', []);
        if (empty($items)) {
            Response::json(['code' => 1, 'msg' => '请选择需要删除的用户']);
        }

        $ids = [];
        foreach ($items as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $ids = array_unique($ids);

        if (empty($ids)) {
            Response::json(['code' => 1, 'msg' => '没有有效的用户 ID']);
        }

        // 保护超级管理员 (id=1)
        if (in_array(1, $ids, true)) {
            Response::json(['code' => 1, 'msg' => '不能删除超级管理员']);
            return;
        }

        // 禁止删除自己
        $currentId = (int)Auth::user('id');
        if (in_array($currentId, $ids, true)) {
            Response::json(['code' => 1, 'msg' => '不能删除当前登录的账号']);
            return;
        }

        // 删除超管账号仅限超级管理员本人操作
        $idPh = implode(',', array_fill(0, count($ids), '?'));
        $hasSuper = DB::query(
            "SELECT COUNT(*) FROM {admin_roles} WHERE role_id = 1 AND admin_id IN ({$idPh})",
            $ids
        )->fetchColumn();
        if ($hasSuper && !app()->rbac->isSuperAdmin()) {
            AdminLog::warning(
                AdminLog::CATEGORY_SECURITY,
                '越权操作被拦截',
                '非超级管理员尝试删除超级管理员账号（IDs: ' . implode(',', $ids) . '）'
            );
            Response::json(['code' => 1, 'msg' => '只有超级管理员可以删除超级管理员账号']);
            return;
        }

        // 保护最后一个可登录的超管：删除后系统必须仍保留超管
        $remaining = (int) DB::query(
            "SELECT COUNT(*) FROM {admin_roles} ar"
            . " INNER JOIN {admin} a ON a.id = ar.admin_id"
            . " WHERE ar.role_id = 1 AND ar.admin_id NOT IN ({$idPh}) AND a.status = ?",
            array_merge([Admin::STATUS_ACTIVATED], $ids)
        )->fetchColumn();
        if ($remaining === 0) {
            Response::json(['code' => 1, 'msg' => '不能删除最后一个可登录的超级管理员']);
            return;
        }

        // 删除前收集用户名（审计用）并失效这些用户的权限缓存
        $names = array_column(
            DB::query("SELECT username FROM {admin} WHERE id IN ({$idPh})", $ids)->fetchAll(),
            'username'
        );
        foreach ($ids as $id) {
            RBAC::invalidateUser((int)$id);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        DB::query("DELETE FROM {admin} WHERE id IN ({$placeholders})", $ids);
        DB::query("DELETE FROM {admin_roles} WHERE admin_id IN ({$placeholders})", $ids);

        AdminLog::warning(
            AdminLog::CATEGORY_USER,
            '删除管理员',
            '删除了 ' . count($ids) . ' 个管理员：' . implode(', ', $names)
        );

        Response::json(['code' => 0, 'msg' => '删除成功']);
    }

    /**
     * 设置页 — 集成个人资料 / 修改密码 / 操作记录
     */
    public function profile()
    {
        $user  = Admin::getProfile();
        $tab   = Request::get('tab', 'account');

        // 操作记录分页
        $page     = max(1, (int)Request::get('page', 1));
        $perPage  = 15;
        $total    = AdminLog::countByUser($user['id']);
        $logs     = $total > 0 ? AdminLog::getByUser($user['id'], $perPage, ($page - 1) * $perPage) : [];
        $totalPages = max(1, (int)ceil($total / $perPage));

        // ──── API Token 管理（仅本账户）────
        $apiTokens      = [];
        $apiTokensReady = false;
        try {
            $apiTokens      = DB::table('api_tokens')
                ->where('admin_id', (int)$user['id'])
                ->orderBy('created_at DESC')
                ->fetchAll();
            $apiTokensReady = true;
        } catch (\Throwable $e) {
            // api_tokens 表未创建（迁移未执行）
        }
        // 创建后的一次性明文展示
        $newApiToken = (string)session()->get('api_token_plaintext', '');
        if ($newApiToken !== '') {
            session()->remove('api_token_plaintext');
        }

        $data = [
            'user'        => $user,
            'active_tab'  => $tab,
            'page_title'  => '账户设置',
            'logs'        => $logs,
            'log_page'    => $page,
            'log_total_pages' => $totalPages,
            'log_total'   => $total,
            'api_tokens'      => $apiTokens,
            'api_tokens_ready' => $apiTokensReady,
            'new_api_token'   => $newApiToken,
        ];

        view('user.settings', $data);
    }

    // ========== API Token 管理 ==========

    /**
     * 创建 API Token（明文一次性存入 session，跳回 tokens 页展示）
     */
    public function createApiToken()
    {
        if (!Request::isPost()) {
            Response::redirect(Url::action('User@profile', ['tab' => 'tokens']));
        }

        $ttlChoice = (string)Request::post('ttl', '30');
        $ttlMap    = ['1' => 86400, '7' => 7 * 86400, '30' => 30 * 86400, '0' => 0];
        $ttl       = $ttlMap[$ttlChoice] ?? (30 * 86400);

        $token = ApiAuth::issue((int)Auth::user('id'), [], $ttl);
        if ($token === null) {
            Response::redirect(Url::action('User@profile', ['tab' => 'tokens']))
                ->with('error', '创建失败：api_tokens 表不存在，请先执行迁移（php console zapcms:Migrate）');
        }

        session()->set('api_token_plaintext', $token);
        AdminLog::info(
            AdminLog::CATEGORY_USER,
            '创建 API Token',
            $ttl > 0 ? '有效期 ' . round($ttl / 86400) . ' 天' : '永不过期'
        );
        Response::redirect(Url::action('User@profile', ['tab' => 'tokens']))
            ->with('success', 'Token 创建成功，请立即复制保存（仅显示一次）');
    }

    /**
     * 吊销 API Token（仅限本账户的 Token）
     */
    public function revokeApiToken()
    {
        if (!Request::isPost()) {
            Response::redirect(Url::action('User@profile', ['tab' => 'tokens']));
        }

        $id      = (int)Request::post('id', 0);
        $adminId = (int)Auth::user('id');
        $back    = Url::action('User@profile', ['tab' => 'tokens']);

        if ($id <= 0) {
            Response::redirect($back)->with('error', '参数错误');
        }

        $deleted = DB::delete('api_tokens', 'id=:id AND admin_id=:a', ['id' => $id, 'a' => $adminId]);
        if ($deleted > 0) {
            AdminLog::info(AdminLog::CATEGORY_USER, '吊销 API Token', "Token #{$id}");
            Response::redirect($back)->with('success', 'Token 已吊销，使用它的客户端将立即失效');
        }
        Response::redirect($back)->with('error', 'Token 不存在或无权操作');
    }

    // ========== 个人资料更新 ==========

    public function updateProfile()
    {
        if (!Request::isPost()) {
            Response::redirect(Url::action('User@profile'));
        }

        $userId   = Auth::user('id');
        $fullName = trim(Request::post('full_name', ''));
        $email    = trim(Request::post('email', ''));
        $phone    = trim(Request::post('phone_number', ''));

        if ($fullName === '') {
            Response::redirect(Url::action('User@profile', ['tab' => 'account']))->with('error', '姓名不能为空');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::redirect(Url::action('User@profile', ['tab' => 'account']))->with('error', '邮箱格式不正确');
        }

        $data = [
            'full_name'    => $fullName,
            'email'        => $email,
            'phone_number' => $phone,
            'updated_at'   => time(),
        ];
        DB::update('admin', $data, ['id' => $userId]);

        // 更新 session
        $session = session()->get('zapAdmin');
        $session['full_name']    = $fullName;
        $session['email']        = $email;
        $session['phone_number'] = $phone;
        session()->set('zapAdmin', $session);
        app()->set('zapAdmin', $session);

        AdminLog::info(AdminLog::CATEGORY_USER, '更新个人资料', "姓名: {$fullName}");

        Response::redirect(Url::action('User@profile', ['tab' => 'account']))->with('success', '个人资料更新成功');
    }

    // ========== 头像上传 ==========

    public function uploadAvatar()
    {
        if (!Request::isPost()) {
            Response::redirect(Url::action('User@profile'));
        }

        $userId  = Auth::user('id');
        $uploads = Request::file('avatar');

        if (empty($uploads) || empty($uploads['tmp_name']) || $uploads['error'] !== UPLOAD_ERR_OK) {
            Response::redirect(Url::action('User@profile', ['tab' => 'account']))->with('error', '请选择要上传的头像');
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $uploads['tmp_name']);

        if (!in_array($mime, $allowedTypes, true)) {
            Response::redirect(Url::action('User@profile', ['tab' => 'account']))->with('error', '仅支持 JPG / PNG / GIF / WebP 格式');
        }

        $maxSize = 2 * 1024 * 1024; // 2MB
        if ($uploads['size'] > $maxSize) {
            Response::redirect(Url::action('User@profile', ['tab' => 'account']))->with('error', '头像文件不能超过 2MB');
        }

        $extMap = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ];
        $ext      = isset($extMap[$mime]) ? $extMap[$mime] : 'jpg';
        $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;

        // 统一走存储门面，落 storage/avatar（local/both/oss 由系统设置决定）
        try {
            $result = \zapcms\services\storage\StorageManager::put(
                'avatar/' . $filename,
                $uploads['tmp_name'],
                ['size' => (int)$uploads['size'], 'mime' => $mime]
            );
        } catch (\Throwable $e) {
            Response::redirect(Url::action('User@profile', ['tab' => 'account']))
                ->with('error', '头像保存失败：' . $e->getMessage());
            return;
        }

        $avatarUrl = $result->url;
        DB::update('admin', ['avatar_url' => $avatarUrl, 'updated_at' => time()], ['id' => $userId]);

        $session = session()->get('zapAdmin');
        $session['avatar_url'] = $avatarUrl;
        session()->set('zapAdmin', $session);
        app()->set('zapAdmin', $session);

        AdminLog::info(AdminLog::CATEGORY_USER, '更新头像', '上传了新头像');

        Response::redirect(Url::action('User@profile', ['tab' => 'account']))->with('success', '头像更新成功');
    }

    // ========== 修改密码 ==========

    public function changePassword()
    {
        if (!Request::isPost()) {
            Response::redirect(Url::action('User@profile'));
        }

        $userId      = Auth::user('id');
        $oldPassword = Request::post('old_password', '');
        $newPassword = Request::post('new_password', '');
        $confirmPwd  = Request::post('confirm_password', '');

        if ($oldPassword === '' || $newPassword === '' || $confirmPwd === '') {
            Response::redirect(Url::action('User@profile', ['tab' => 'security']))->with('error', '请填写所有密码字段');
        }

        if (mb_strlen($newPassword) < 6) {
            Response::redirect(Url::action('User@profile', ['tab' => 'security']))->with('error', '新密码至少需要 6 个字符');
        }

        if ($newPassword !== $confirmPwd) {
            Response::redirect(Url::action('User@profile', ['tab' => 'security']))->with('error', '两次输入的新密码不一致');
        }

        $admin = DB::table('admin')->where('id', $userId)->fetch(FETCH_ASSOC);
        if (empty($admin) || !Password::verify($oldPassword, $admin['password'])) {
            Response::redirect(Url::action('User@profile', ['tab' => 'security']))->with('error', '旧密码不正确');
        }

        DB::update('admin', [
            'password'   => Password::hash($newPassword),
            'updated_at' => time(),
        ], ['id' => $userId]);

        AdminLog::warning(AdminLog::CATEGORY_SECURITY, '修改密码', '更新了登录密码');

        Response::redirect(Url::action('User@profile', ['tab' => 'security']))->with('success', '密码修改成功');
    }

    // ================================================================
    //  角色管理
    // ================================================================

    /**
     * 角色列表
     */
    public function roles()
    {
        $page    = max(1, (int)Request::get('page', 1));
        $perPage = 20;

        $total = (int)DB::table('roles')->count();
        $roles = DB::table('roles')
            ->orderBy('role_id', 'DESC')
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->fetchAll();

        $queryParams = Request::get();
        unset($queryParams['page']);
        $pageHelper = new Pagination($page, $perPage, $queryParams);
        $pageHelper->setTotal($total);
        // 显式指定分页基准 URL，强制 ?page=N 形式
        $pageHelper->withPath(Url::action('User@roles') . '?' . ($queryParams ? http_build_query($queryParams) . '&' : '') . 'page={page}');

        view('user.roles', [
            'data'        => $roles,
            'pageHelper'  => $pageHelper,
            'page_title'  => '角色管理',
            'breadcrumbs' => [
                ['title' => '首页', 'url' => Url::action('Index')],
                ['title' => '用户管理', 'url' => Url::action('User@index')],
                ['title' => '角色管理'],
            ],
        ]);
    }

    /**
     * 角色编辑表单（Modal）
     */
    public function formRole()
    {
        $id           = (int)Request::get('id', 0);
        $data         = ['role_id' => 0, 'name' => '', 'description' => ''];
        $rolePermissions = [];

        if ($id > 0) {
            $data = DB::table('roles')->where('role_id', $id)->fetch(FETCH_ASSOC);
            if (!$data) {
                return '角色不存在';
            }
            // 已分配的权限 — 按 perm_key 索引
            $rows = DB::table('roles_permissions')->where('role_id', $id)->fetchAll();
            foreach ($rows as $row) {
                $permKey = $row['perm_key'];
                $rolePermissions[$permKey] = array_fill_keys(
                    array_filter(explode(',', $row['extras'] ?? '')),
                    1
                );
            }
        }

        // 所有权限（按 pid 树结构 DFS 排序，并计算 level 供视图缩进；
        // permissions 表同时存在菜单镜像与手动创建两类来源，perm_id 顺序 ≠ 树顺序）
        $permissions = $this->sortPermissionTree(
            DB::table('permissions')->fetchAll()
        );

        // 系统菜单（用于菜单权限）
        $adminMenus = $this->getAdminMenuTree();

        view('user.roles_form', [
            'data'              => $data,
            'permissions'       => $permissions,
            'role_permissions'  => $rolePermissions,
            'admin_menus'       => $adminMenus,
        ]);
    }

    /**
     * 把权限行按 pid 组装成树并返回 DFS 顺序（父项在前、子项紧随），
     * 同级按 sort_order、perm_id 排序；每行附上树深度 level（顶级 = 0）。
     *
     * 孤儿行（父级已删除且自身 pid 非顶级）降级为顶级追加在末尾，保证不丢权限。
     */
    protected function sortPermissionTree(array $rows): array
    {
        $children = [];
        foreach ($rows as $row) {
            $children[(int)($row['pid'] ?? 0)][] = $row;
        }
        foreach ($children as &$list) {
            usort($list, static function ($a, $b) {
                return [(int)($a['sort_order'] ?? 0), (int)$a['perm_id']]
                    <=> [(int)($b['sort_order'] ?? 0), (int)$b['perm_id']];
            });
        }
        unset($list);

        $out = [];
        $walk = static function (int $pid, int $level) use (&$walk, $children, &$out): void {
            foreach ($children[$pid] ?? [] as $row) {
                $row['level'] = $level;
                $out[] = $row;
                $walk((int)$row['perm_id'], $level + 1);
            }
        };
        $walk(0, 0);

        // 兜底：任何未被树遍历覆盖的行（父级指向不存在且不在顶级桶里）追加末尾
        if (count($out) < count($rows)) {
            $seen = [];
            foreach ($out as $row) {
                $seen[(int)$row['perm_id']] = true;
            }
            foreach ($rows as $row) {
                if (!isset($seen[(int)$row['perm_id']])) {
                    $row['level'] = 0;
                    $out[] = $row;
                }
            }
        }

        return $out;
    }

    /**
     * 保存角色
     */
    public function saveRole()
    {
        if (!Request::isPost()) {
            Response::json(['code' => 1, 'msg' => '非法请求']);
        }

        $roleId    = (int)Request::post('role_id', 0);
        $data      = Request::post('data', []);
        $perms     = Request::post('perms', []);
        $extras    = Request::post('extras', []);

        $name = trim($data['name'] ?? '');
        if ($name === '') {
            Response::json(['code' => 1, 'msg' => '角色名称不能为空']);
        }

        // 超管角色（role_id=1）仅限超级管理员编辑；
        // 非超管也不能修改自己所属角色的权限（自我提权）
        if (!$currentIsSuper = app()->rbac->isSuperAdmin()) {
            if ($roleId === 1) {
                AdminLog::warning(AdminLog::CATEGORY_SECURITY, '越权操作被拦截', "非超级管理员尝试编辑超级管理员角色");
                Response::json(['code' => 1, 'msg' => '只有超级管理员可以编辑超级管理员角色']);
                return;
            }
            $myRoleIds = array_map('intval', array_column(app()->rbac->roles() ?? [], 'role_id'));
            if ($roleId > 0 && in_array($roleId, $myRoleIds, true)) {
                AdminLog::warning(AdminLog::CATEGORY_SECURITY, '越权操作被拦截', "用户尝试修改自己所属角色（role_id={$roleId}）的权限");
                Response::json(['code' => 1, 'msg' => '不能修改自己所属角色的权限']);
                return;
            }
        }

        $now = time();
        $row = [
            'name'        => $name,
            'description' => trim($data['description'] ?? ''),
            'updated_at'  => $now,
        ];

        if ($roleId > 0) {
            // 更新：表单未提供 status 时不改动现有启用状态
            if (isset($data['status'])) {
                $row['status'] = (int)(bool)$data['status'];
            }
            DB::update('roles', $row, ['role_id' => $roleId]);
            AdminLog::info(AdminLog::CATEGORY_USER, '修改角色', "修改了角色: {$name}（ID {$roleId}），权限 " . count((array)$perms) . ' 项');
        } else {
            // 新建：必须默认启用。roles.status 默认 0（禁用），而 RBAC 只认
            // status=1 的角色（queryRoleIds INNER JOIN ... r.status=1），
            // 不写 status 会导致新角色被静默禁用 → 分了权限菜单/页面也全被过滤
            $row['status']     = 1;
            $row['created_at'] = $now;
            $roleId = DB::insert('roles', $row);
            AdminLog::info(AdminLog::CATEGORY_USER, '新增角色', "添加了角色: {$name}（ID {$roleId}），权限 " . count((array)$perms) . ' 项');
        }

        // 同步权限关联
        DB::delete('roles_permissions', ['role_id' => $roleId]);
        if (!empty($perms)) {
            foreach ((array)$perms as $permKey) {
                $permKey = trim($permKey);
                if ($permKey === '') {
                    continue;
                }
                $extraStr = '';
                if (isset($extras[$permKey]) && is_array($extras[$permKey])) {
                    $extraStr = implode(',', $extras[$permKey]);
                }
                DB::insert('roles_permissions', [
                    'role_id'         => $roleId,
                    'perm_key'        => $permKey,
                    'extras'          => $extraStr ?: null,
                    'assignment_time' => $now,
                ]);
            }
        }

        // 角色权限变更，全局失效
        RBAC::invalidate();

        Response::json(['code' => 0, 'msg' => '保存成功']);
    }

    /**
     * 删除角色
     */
    public function removeRole()
    {
        if (!Request::isPost()) {
            Response::json(['code' => 1, 'msg' => '非法请求']);
        }

        $items = Request::post('data', []);
        if (empty($items)) {
            Response::json(['code' => 1, 'msg' => '请选择需要删除的角色']);
        }

        $ids = [];
        foreach ($items as $item) {
            $id = (int)($item['role_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $ids = array_unique($ids);

        if (empty($ids)) {
            Response::json(['code' => 1, 'msg' => '没有有效的角色 ID']);
        }

        // 保护超级管理员角色
        if (in_array(1, $ids, true)) {
            Response::json(['code' => 1, 'msg' => '不能删除超级管理员角色']);
            return;
        }

        // 删除仍挂有成员的角色：先失效这些成员的权限缓存
        $ph0 = implode(',', array_fill(0, count($ids), '?'));
        $memberIds = array_column(
            DB::query("SELECT DISTINCT admin_id FROM {admin_roles} WHERE role_id IN ({$ph0})", $ids)->fetchAll(),
            'admin_id'
        );
        foreach ($memberIds as $mid) {
            RBAC::invalidateUser((int)$mid);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        DB::query("DELETE FROM {roles} WHERE role_id IN ({$placeholders})", $ids);
        DB::query("DELETE FROM {roles_permissions} WHERE role_id IN ({$placeholders})", $ids);
        DB::query("DELETE FROM {admin_roles} WHERE role_id IN ({$placeholders})", $ids);

        AdminLog::warning(AdminLog::CATEGORY_USER, '删除角色', '删除了 ' . count($ids) . ' 个角色（IDs: ' . implode(',', $ids) . '）');

        RBAC::invalidate();

        Response::json(['code' => 0, 'msg' => '删除成功']);
    }

    // ================================================================
    //  权限管理
    // ================================================================

    /**
     * 权限列表（树形结构）
     */
    public function permissions()
    {
        $tree = Permissions::instance()->getFlatTree();

        $pageHelper = new Pagination(1, 999, []);
        $pageHelper->setTotal(count($tree));
        // 树形展示通常单页渲染；仍显式指定基准 URL，避免动态属性弃用警告
        $pageHelper->withPath(Url::action('User@permissions') . '?page={page}');

        view('user.permissions', [
            'data'        => $tree,
            'pageHelper'  => $pageHelper,
            'page_title'  => '权限管理',
            'breadcrumbs' => [
                ['title' => '首页', 'url' => Url::action('Index')],
                ['title' => '用户管理', 'url' => Url::action('User@index')],
                ['title' => '权限管理'],
            ],
        ]);
    }

    /**
     * 权限编辑表单（Modal）
     */
    public function formPermission()
    {
        $id  = (int)Request::get('id', 0);
        $pid = (int)Request::get('pid', 0);

        $data = [
            'perm_id'     => 0,
            'title'       => '',
            'perm_key'    => '',
            'extras'      => '{}',
            'description' => '',
            'pid'         => $pid,
        ];
        $extras = [];

        if ($id > 0) {
            $row = DB::table('permissions')->where('perm_id', $id)->fetch(FETCH_ASSOC);
            if (!$row) {
                return '权限不存在';
            }
            $data   = $row;
            $pid    = (int)$data['pid'];
            $extras = json_decode($data['extras'] ?? '{}', true);
            if (!is_array($extras)) {
                $extras = [];
            }
        }

        view('user.permissions_form', [
            'data'   => $data,
            'pid'    => $pid,
            'extras' => $extras,
        ]);
    }

    /**
     * 保存权限
     */
    public function savePermission()
    {
        if (!Request::isPost()) {
            Response::json(['code' => 1, 'msg' => '非法请求']);
        }

        $permId = (int)Request::post('perm_id', 0);
        $data   = Request::post('data', []);
        $extras = Request::post('extras', []);

        $title   = trim($data['title'] ?? '');
        $permKey = trim($data['perm_key'] ?? '');
        $pid     = (int)($data['pid'] ?? 0);

        if ($title === '') {
            Response::json(['code' => 1, 'msg' => '权限名称不能为空']);
        }

        // 构建 path 和 level
        $level = 0;
        $path  = '';
        if ($pid > 0) {
            $parent = DB::table('permissions')->where('perm_id', $pid)->fetch(FETCH_ASSOC);
            if ($parent) {
                $level = (int)$parent['level'] + 1;
                $path  = ($parent['path'] ? $parent['path'] . ',' : '') . (string)$pid;
            }
        }

        // 构建 extras JSON（注意：循环变量不要与外层 $title/$permKey 同名，避免覆盖）
        $extrasJson = '{}';
        if (!empty($extras)) {
            $extrasMap = [];
            foreach ($extras as $row) {
                $extraKey   = trim($row['key'] ?? '');
                $extraTitle = trim($row['title'] ?? '');
                if ($extraKey !== '' && $extraTitle !== '') {
                    $extrasMap[$extraKey] = $extraTitle;
                }
            }
            if (!empty($extrasMap)) {
                $extrasJson = json_encode($extrasMap, JSON_UNESCAPED_UNICODE);
            }
        }

        $now = time();
        $row = [
            'title'       => $title,
            'perm_key'    => $permKey,
            'pid'         => $pid,
            'path'        => $path,
            'level'       => $level,
            'extras'      => $extrasJson,
            'description' => trim($data['description'] ?? ''),
            'updated_at'  => $now,
        ];

        if ($permId > 0) {
            DB::update('permissions', $row, ['perm_id' => $permId]);
            AdminLog::info(AdminLog::CATEGORY_USER, '修改权限', "修改了权限: {$title}");
        } else {
            $row['created_at'] = $now;
            $permId = DB::insert('permissions', $row);
            AdminLog::info(AdminLog::CATEGORY_USER, '新增权限', "添加了权限: {$title}");
        }

        RBAC::invalidate();

        Response::json(['code' => 0, 'msg' => '保存成功']);
    }

    /**
     * 删除权限
     */
    public function removePermission()
    {
        if (!Request::isPost()) {
            Response::json(['code' => 1, 'msg' => '非法请求']);
        }

        $items = Request::post('data', []);
        if (empty($items)) {
            Response::json(['code' => 1, 'msg' => '请选择需要删除的权限']);
        }

        $ids = [];
        foreach ($items as $item) {
            $id = (int)($item['perm_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $ids = array_unique($ids);

        if (empty($ids)) {
            Response::json(['code' => 1, 'msg' => '没有有效的权限 ID']);
        }

        // 删除前收集所有被删除的 perm_key（含子权限）
        $permKeys = [];
        foreach ($ids as $id) {
            $perm = Permissions::instance()->get($id);
            if ($perm && !empty($perm['perm_key'])) {
                $permKeys[] = $perm['perm_key'];
            }
        }
        // 收集子权限的 perm_key（Categories::remove 会级联删除子权限）
        $allPerms = Permissions::instance()->getFlatTree();
        foreach ($allPerms as $perm) {
            foreach ($ids as $pid) {
                $prefix = $pid . ',';
                // path 格式：祖先看父级 ID，或者自己的 pid 就是目标 ID
                $inPath = '/' . $pid . '/' === '/' . ($perm['path'] ?? '') . '/' || 
                          strpos(',' . ($perm['path'] ?? '') . ',', ',' . $pid . ',') !== false ||
                          (int)$perm['pid'] === $pid;
                if ($perm['perm_id'] !== $pid && $inPath && !empty($perm['perm_key'])) {
                    $permKeys[] = $perm['perm_key'];
                }
            }
        }
        $permKeys = array_unique($permKeys);

        // 使用 Permissions 模型执行级联删除
        foreach ($ids as $id) {
            Permissions::instance()->remove($id);
        }

        // 清理 roles_permissions 中的关联记录
        if (!empty($permKeys)) {
            $pkPlaceholders = implode(',', array_fill(0, count($permKeys), '?'));
            DB::query("DELETE FROM {roles_permissions} WHERE perm_key IN ({$pkPlaceholders})", $permKeys);
        }

        AdminLog::warning(AdminLog::CATEGORY_USER, '删除权限', '删除了 ' . count($ids) . ' 个权限（IDs: ' . implode(',', $ids) . '）');

        RBAC::invalidate();

        Response::json(['code' => 0, 'msg' => '删除成功']);
    }

    // ================================================================
    //  辅助方法
    // ================================================================

    /**
     * 获取后台菜单（扁平列表，带 level）
     */
    private function getAdminMenuTree(): array
    {
        $menus = DB::table('admin_menu')->orderBy('sort_order')->fetchAll();
        $tree  = $this->toTree($menus, 0);
        return $this->flattenTree($tree);
    }

    /**
     * 将嵌套树展开为带 level 的扁平列表
     */
    private function flattenTree(array $items, array &$out = []): array
    {
        foreach ($items as $item) {
            $out[] = $item;
            if (!empty($item['children'])) {
                $this->flattenTree($item['children'], $out);
            }
        }
        return $out;
    }

    /**
     * 扁平列表 → 树
     */
    private function toTree(array $items, int $pid = 0): array
    {
        $tree = [];
        foreach ($items as $item) {
            if ((int)$item['pid'] === $pid) {
                $item['children'] = $this->toTree($items, (int)$item['id']);
                $tree[] = $item;
            }
        }
        return $tree;
    }
}
