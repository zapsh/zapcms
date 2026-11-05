<?php

namespace zapcms\controllers;

use zapcms\controllers\AdminController;
use zapcms\models\AdminLog;
use zap\DB;
use zapcms\support\ZapPackageManager;
use zapcms\support\ZapUpdate;
use zap\http\Request;
use zap\http\Response;
use zap\view\View;

/**
 * 插件管理控制器
 * 支持：已安装插件列表、插件市场、安装、卸载、启用/禁用、更新
 */
class PluginController extends AdminController
{
    protected ZapPackageManager $pm;
    protected ZapUpdate $updater;

    public function __construct()
    {
        parent::__construct();
        $this->pm      = new ZapPackageManager();
        $this->updater = new ZapUpdate();
    }

    /**
     * 扩展管理（模块与插件统一列表）
     */
    public function index()
    {
        $extensions = $this->buildExtensions();

        view('plugin.index', [
            'extensions'     => $extensions,
            'page_title'       => '插件',
            'page_subtitle'    => '管理已安装的扩展（模块与插件使用相同的分发方式，统一在此维护），启用、禁用、卸载及更新',
            'breadcrumbs'      => [
                ['title' => '控制台', 'url' => \zap\facades\Url::action('Index')],
                ['title' => '插件'],
            ],
        ]);
    }

    /**
     * 合并「磁盘模块(mods/)」与「plugin 表(市场安装)」为一份扩展清单。
     * 两者分发方式一致，按 name 归并。类型（模块/插件）以 mod.json 的 type 声明为准；
     * 登记/启用状态由 mod.installed.{name} / plugin 表体现，二者互不混淆。
     * 激活状态优先取 mod.installed.{name} 选项（plugin 表 status 作兜底）。
     */
    protected function buildExtensions(): array
    {
        $extensions = [];

        // 1) 以磁盘目录为基底（物理存在的扩展：模块与插件都在 mods/ 下）。
        //    类型（模块/插件）以 mod.json 的 type 声明为准，反映扩展的“性质”，
        //    不与“是否已登记”混为一谈——模块和插件都要安装、都会登记。
        foreach ($this->pm->getLoadedMods() as $mod) {
            $name = $mod['name'];
            $ext  = $mod;
            $ext['status']     = 0;
            $ext['registered'] = false;
            $ext['missing']    = false;

            // 透出后台页面声明，供列表“设置”按钮判断
            $adminPages           = $this->normalizeAdminPages($mod['admin'] ?? null);
            $ext['admin_pages']   = $adminPages;
            $ext['has_settings']  = !empty($adminPages);

            if ($opt = $this->pm->getInstalledModOption($name)) {
                $ext['status']     = (int)($opt['status'] ?? 0);
                $ext['registered'] = true;
            } elseif ($p = $this->pm->getPlugin($name)) {
                $ext['status'] = (int)($p['status'] ?? 0);
            }

            // package_name 仅用于市场卸载/标识，不影响类型判定
            if ($p = $this->pm->getPlugin($name)) {
                $ext['package_name'] = $p['package_name'] ?? '';
            }

            $extensions[$name] = $ext;
        }

        // 2) plugin 表有记录、但磁盘目录已缺失的项（文件被删，仍可卸载 / 重装）
        foreach ($this->pm->getInstalledPlugins() as $p) {
            if (isset($extensions[$p['name']])) {
                continue;
            }
            $extensions[$p['name']] = [
                'name'         => $p['name'],
                'title'        => $p['title'] ?? $p['name'],
                'version'      => $p['version'] ?? '1.0.0',
                'author'       => $p['author'] ?? '',
                'description'  => $p['description'] ?? '',
                'type'         => 'plugin',
                'status'       => (int)($p['status'] ?? 0),
                'registered'   => true,
                'missing'      => true,
                'package_name' => $p['package_name'] ?? '',
                'admin_pages'  => [],
                'has_settings' => false,
            ];
        }

        return array_values($extensions);
    }

    /**
     * 插件市场页面
     */
    public function market()
    {
        $page     = (int)(req()->get('page', 1));
        $search   = trim(req()->get('search', ''));
        $category = trim(req()->get('category', ''));
        $perPage  = 12;

        $result = $this->updater->getMarketPlugins($page, $perPage, $search, $category);

        // 标记已安装
        $installed = DB::table('plugin')->pluck('package_name');

        view('plugin.market', [
            'result'    => $result,
            'page'      => $page,
            'search'    => $search,
            'category'  => $category,
            'installed' => $installed ?: [],
            'page_title'       => '插件市场',
            'page_subtitle'    => '发现和安装更多插件扩展',
            'breadcrumbs'      => [
                ['title' => '控制台', 'url' => \zap\facades\Url::action('Index')],
                ['title' => '插件管理', 'url' => \zap\facades\Url::action('Plugin@index')],
                ['title' => '插件市场'],
            ],
        ]);
    }

    /**
     * AJAX: 获取市场插件列表
     */
    public function ajaxMarketList()
    {
        $page     = (int)(req()->get('page', 1));
        $search   = trim(req()->get('search', ''));
        $category = trim(req()->get('category', ''));

        $result = $this->updater->getMarketPlugins($page, 12, $search, $category);

        $installed = DB::table('plugin')->pluck('package_name');

        Response::json([
            'code'      => $result ? 0 : 1,
            'data'      => $result,
            'installed' => $installed ?: [],
        ]);
    }

    /**
     * 安装插件（从市场安装）
     */
    public function install()
    {
        if (!Request::isPost()) {
            Response::json(['code' => 1, 'msg' => '无效请求']);
        }

        $packageName = trim(req()->post('package', ''));
        if (empty($packageName)) {
            Response::json(['code' => 1, 'msg' => '请输入插件包名']);
        }

        $result = $this->updater->installMarketPlugin($packageName);

        if (!empty($result['success'])) {
            AdminLog::info(AdminLog::CATEGORY_SYSTEM, '安装插件', '包名：' . $packageName);
        }

        Response::json([
            'code' => $result['success'] ? 0 : 1,
            'msg'  => $result['message'],
        ]);
    }

    /**
     * 手动上传插件安装
     */
    public function uploadInstall()
    {
        if (!Request::isPost()) {
            Response::json(['code' => 1, 'msg' => '无效请求']);
        }

        $file = $_FILES['plugin_zip'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            // 无文件上传时：注册已存在于 mods 目录的模块（register 模式）
            $name = trim(req()->post('name', ''));
            $name = preg_replace('/[^a-zA-Z0-9\-_]/', '', $name);
            if (empty($name)) {
                Response::json(['code' => 1, 'msg' => '请上传有效的ZIP文件']);
            }
            // 以 mod.installed 选项为准判断「已注册」（与列表的 registered 判定一致；
            // PluginLoader 也只按它加载）。若仅有 plugin 表残留登记、缺 mod 选项，
            // 视为未注册完成，允许补全注册而不是直接拒绝。
            if ($this->pm->getInstalledModOption($name)) {
                Response::json(['code' => 1, 'msg' => '模块已注册']);
            }
            $pluginInfo = $this->pm->readModInfoFromDir($name);
            if (empty($pluginInfo)) {
                Response::json(['code' => 1, 'msg' => '模块目录不存在或缺少 mod.json']);
            }
            $depError = $this->pm->checkDependencies($pluginInfo);
            if ($depError !== null) {
                Response::json(['code' => 1, 'msg' => $depError]);
            }
            $now = time();
            $pluginRow = [
                'title'        => $pluginInfo['title'] ?? $name,
                'version'      => $pluginInfo['version'] ?? '1.0.0',
                'author'       => $pluginInfo['author'] ?? '',
                'description'  => $pluginInfo['description'] ?? '',
                'homepage'     => $pluginInfo['homepage'] ?? '',
                'package_name' => $pluginInfo['package_name'] ?? $name,
                'status'       => 1,
                'sort_order'   => 0,
                'updated_at'   => $now,
            ];
            if ($this->pm->getPlugin($name)) {
                // 已有残留登记：更新补齐，避免重复插入主键
                DB::table('plugin')->where('name', $name)->update($pluginRow);
            } else {
                $pluginRow['name']         = $name;
                $pluginRow['installed_at'] = $now;
                DB::table('plugin')->insert($pluginRow);
            }
            $this->pm->saveInstalledModOption($name, [
                'name'         => $name,
                'type'         => $pluginInfo['type'] ?? 'module',
                'title'        => $pluginInfo['title'] ?? $name,
                'version'      => $pluginInfo['version'] ?? '1.0.0',
                'author'       => $pluginInfo['author'] ?? '',
                'description'  => $pluginInfo['description'] ?? '',
                'homepage'     => $pluginInfo['homepage'] ?? '',
                'autoload'     => $pluginInfo['autoload'] ?? '',
                'dependencies' => $pluginInfo['dependencies'] ?? [],
                'status'       => 1,
                'installed_at' => $now,
            ]);
            // 执行模块安装脚本（建表等），否则“注册已存在模块”只有登记、没有初始化
            $this->pm->runPluginInstallScript($name);
            // 重新生成模块路由配置（config/module.php）
            $this->pm->syncModuleRoutes();
            AdminLog::info(AdminLog::CATEGORY_SYSTEM, '注册模块', '模块：' . $name);
            Response::json(['code' => 0, 'msg' => '模块注册成功']);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            Response::json(['code' => 1, 'msg' => '只支持ZIP格式的插件包']);
        }

        $tmpFile = $file['tmp_name'];

        // 读取插件信息
        $pluginInfo = $this->readPluginInfoFromZip($tmpFile);
        $name = $pluginInfo['name'] ?? pathinfo($file['name'], PATHINFO_FILENAME);
        $name = preg_replace('/[^a-zA-Z0-9\-_]/', '', $name);

        if (empty($name)) {
            Response::json(['code' => 1, 'msg' => '无法识别插件名称']);
        }

        // 检查是否已安装
        if ($this->pm->isPluginInstalled($name)) {
            Response::json(['code' => 1, 'msg' => '插件已安装，如需更新请使用更新功能']);
        }

        // 校验依赖（zapcms 版本、依赖模块版本）
        $depError = $this->pm->checkDependencies($pluginInfo);
        if ($depError !== null) {
            Response::json(['code' => 1, 'msg' => $depError]);
        }

        // 解压并安装
        $pluginDir = APP_ROOT . '/mods/' . $name;
        if (is_dir($pluginDir)) {
            $this->pm->deleteDirectory($pluginDir);
        }

        $extractResult = $this->pm->extract($tmpFile, $pluginDir);
        if (!$extractResult) {
            Response::json(['code' => 1, 'msg' => '解压失败']);
        }

        // 写入数据库
        $now = time();
        DB::table('plugin')->insert([
            'name'         => $name,
            'title'        => $pluginInfo['title'] ?? $name,
            'version'      => $pluginInfo['version'] ?? '1.0.0',
            'author'       => $pluginInfo['author'] ?? '',
            'description'  => $pluginInfo['description'] ?? '',
            'homepage'     => $pluginInfo['homepage'] ?? '',
            'package_name' => $pluginInfo['package_name'] ?? $name,
            'status'       => 1,
            'sort_order'   => 0,
            'installed_at' => $now,
            'updated_at'   => $now,
        ]);

        // 写入 options 表（供后台启动加载 autoload 脚本）
        $this->pm->saveInstalledModOption($name, [
            'name'         => $name,
            'type'         => $pluginInfo['type'] ?? 'module',
            'title'        => $pluginInfo['title'] ?? $name,
            'version'      => $pluginInfo['version'] ?? '1.0.0',
            'author'       => $pluginInfo['author'] ?? '',
            'description'  => $pluginInfo['description'] ?? '',
            'homepage'     => $pluginInfo['homepage'] ?? '',
            'autoload'     => $pluginInfo['autoload'] ?? '',
            'dependencies' => $pluginInfo['dependencies'] ?? [],
            'status'       => 1,
            'installed_at' => $now,
        ]);

        // 执行模块安装脚本（建表等）
        $this->pm->runPluginInstallScript($name);

        // 重新生成模块路由配置（config/module.php）
        $this->pm->syncModuleRoutes();
        AdminLog::info(AdminLog::CATEGORY_SYSTEM, '安装插件', '插件：' . $name);

        Response::json(['code' => 0, 'msg' => '插件安装成功']);
    }

    /**
     * 卸载插件
     */
    public function uninstall()
    {
        if (!Request::isPost()) {
            Response::json(['code' => 1, 'msg' => '无效请求']);
        }

        $name = trim(req()->post('name', ''));
        if (empty($name)) {
            Response::json(['code' => 1, 'msg' => '请指定要卸载的插件']);
        }

        // keep_data=1：保留数据表；keep_files=1：保留模块目录（之后可直接注册激活）
        $keepData  = (bool)req()->post('keep_data', 0);
        $keepFiles = (bool)req()->post('keep_files', 0);

        $result = $this->pm->uninstallPlugin($name, $keepData, $keepFiles);

        if (!empty($result['success'])) {
            AdminLog::info(
                AdminLog::CATEGORY_SYSTEM,
                '卸载插件',
                '插件：' . $name . ($keepData ? '（保留数据）' : '') . ($keepFiles ? '（保留文件）' : '')
            );
        }

        Response::json([
            'code' => $result['success'] ? 0 : 1,
            'msg'  => $result['message'],
        ]);
    }

    /**
     * 更新插件
     */
    public function update()
    {
        if (!Request::isPost()) {
            Response::json(['code' => 1, 'msg' => '无效请求']);
        }

        $name         = trim(req()->post('name', ''));
        $packageName  = trim(req()->post('package_name', ''));
        $downloadUrl  = trim(req()->post('download_url', ''));
        $version      = trim(req()->post('version', ''));

        if (empty($name) || empty($downloadUrl) || empty($version)) {
            Response::json(['code' => 1, 'msg' => '参数不完整']);
        }

        $result = $this->pm->updatePlugin($name, $downloadUrl, $version);

        if (!empty($result['success'])) {
            AdminLog::info(AdminLog::CATEGORY_SYSTEM, '更新插件', '插件：' . $name . ' → ' . $version);
        }

        Response::json([
            'code' => $result['success'] ? 0 : 1,
            'msg'  => $result['message'],
        ]);
    }

    /**
     * 启用/禁用插件
     */
    public function toggleStatus()
    {
        if (!Request::isPost()) {
            Response::json(['code' => 1, 'msg' => '无效请求']);
        }

        $name   = trim(req()->post('name', ''));
        $status = (int)(req()->post('status', 0));

        if (empty($name)) {
            Response::json(['code' => 1, 'msg' => '请指定插件']);
        }

        $result = $this->pm->togglePluginStatus($name, $status);

        // 启用时注册模块后台菜单，禁用时移除
        if (!empty($result['success'])) {
            AdminLog::info(
                AdminLog::CATEGORY_SYSTEM,
                $status === 1 ? '启用插件' : '禁用插件',
                '插件：' . $name
            );
            try {
                $status === 1
                    ? \zapcms\support\ModMenu::sync($name, [], true)
                    : \zapcms\support\ModMenu::remove($name);
            } catch (\Throwable $e) {
                // 菜单同步失败不影响启停结果
            }
        }

        Response::json([
            'code' => $result['success'] ? 0 : 1,
            'msg'  => $result['message'],
        ]);
    }

    /**
     * 批量检查插件更新
     */
    public function checkUpdates()
    {
        $updates = $this->updater->checkPluginUpdates();

        Response::json([
            'code'    => 0,
            'data'    => $updates,
            'count'   => count($updates),
        ]);
    }

    /**
     * 从ZIP文件中读取模块信息（统一 mod.json，兼容旧格式 plugin.json）
     */
    protected function readPluginInfoFromZip(string $zipFile): array
    {
        $info = [];
        if (!class_exists('ZipArchive')) {
            return $info;
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipFile) !== true) {
            return $info;
        }

        // 先尝试 mod.json，再回退 plugin.json（兼容旧包）
        $jsonStr = false;
        foreach (['mod.json', 'plugin.json'] as $candidate) {
            $jsonStr = $zip->getFromName($candidate);
            if ($jsonStr !== false) {
                break;
            }
        }
        if ($jsonStr === false) {
            // 尝试第一级子目录
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                foreach (['mod.json', 'plugin.json'] as $candidate) {
                    if (preg_match('#^[^/]+/' . preg_quote($candidate, '#') . '$#', $name)) {
                        $jsonStr = $zip->getFromName($name);
                        break 2;
                    }
                }
            }
        }

        if ($jsonStr !== false) {
            $info = json_decode($jsonStr, true) ?: [];
        }

        $zip->close();
        return $info;
    }

    /**
     * 插件后台宿主页（无左侧菜单的插件配置 / 管理入口）
     *
     * URL: /{后台前缀}/plugin/settings?plugin={name}&page={key}
     * 依据插件 manifest 的 admin.pages 渲染左侧子导航，并把选中页内容嵌入内容区。
     * 支持三类页面声明：
     *   - view  : 直接渲染模块视图片段（mods/{name}/views/{view}.php），无需控制器，最常用
     *   - action: 分发到模块控制器 \mods\{name}\controllers\{Controller}Controller::{action}()
     *             （模块目录名须为合法 PHP 命名空间，连字符需改为下划线）
     *   - url   : 外链 / 独立路由，点击在新窗口打开，不嵌入内容区
     */
    public function settings()
    {
        $name = trim((string)req()->get('plugin', ''));
        if ($name === '') {
            $this->showAdminError('未指定插件');
            return;
        }

        $modDir = base_path("mods/{$name}");
        if (!is_dir($modDir)) {
            $this->showAdminError('插件目录不存在：' . htmlspecialchars($name));
            return;
        }

        $info  = $this->pm->readModInfoFromDir($name);
        $pages = $this->normalizeAdminPages($info['admin'] ?? null);

        if (empty($pages)) {
            view('plugin.admin', [
                'plugin'           => ['name' => $name, 'title' => $info['title'] ?? $name, 'type' => $info['type'] ?? 'plugin'],
                'pages'            => [],
                'activeKey'        => '',
                'content'          => '<div class="alert alert-info mb-0"><i class="fa fa-info-circle me-1"></i>该插件暂无可配置项。</div>',
                'page_title'       => '插件设置',
                'page_subtitle'    => '',
                'breadcrumbs'      => $this->adminBreadcrumbs($name, $info['title'] ?? $name),
            ]);
            return;
        }

        $pageKey = trim((string)req()->get('page', ''));
        $active  = null;
        foreach ($pages as $p) {
            if ($p['key'] === $pageKey) {
                $active = $p;
                break;
            }
        }
        if ($active === null) {
            $active = $pages[0];
        }

        if (!empty($active['url'])) {
            $content = '<div class="text-center py-5">'
                . '<a class="btn btn-primary" href="' . htmlspecialchars($active['url']) . '" target="_blank" rel="noopener">'
                . '<i class="fa fa-external-link me-1"></i>打开：' . htmlspecialchars($active['title']) . '</a></div>';
        } else {
            // POST 请求（如表单保存）直接转发给 action 页处理；view 页原样渲染
            $content = $this->dispatchPluginPage($name, $active, Request::isPost());
        }

        view('plugin.admin', [
            'plugin'           => ['name' => $name, 'title' => $info['title'] ?? $name, 'type' => $info['type'] ?? 'plugin'],
            'pages'            => $pages,
            'activeKey'        => $active['key'],
            'content'          => $content,
            'page_title'       => ($info['title'] ?? $name) . ' · 设置',
            'page_subtitle'    => '在此配置与管理该插件（无需在左侧导航建菜单）',
            'breadcrumbs'      => $this->adminBreadcrumbs($name, $info['title'] ?? $name),
        ]);
    }

    protected function adminBreadcrumbs(string $name, string $title): array
    {
        return [
            ['title' => '控制台', 'url' => \zap\facades\Url::action('Index')],
            ['title' => '插件管理', 'url' => \zap\facades\Url::action('Plugin@index')],
            ['title' => $title],
        ];
    }

    protected function showAdminError(string $msg): void
    {
        view('plugin.admin', [
            'plugin'           => ['name' => '', 'title' => '出错了', 'type' => ''],
            'pages'            => [],
            'activeKey'        => '',
            'content'          => '<div class="alert alert-warning mb-0"><i class="fa fa-exclamation-triangle me-1"></i>' . $msg . '</div>',
            'page_title'       => '插件设置',
            'page_subtitle'    => '',
            'breadcrumbs'      => $this->adminBreadcrumbs('', '插件设置'),
        ]);
    }

    /**
     * 把插件 manifest 的 admin 配置归一化为统一的页面数组
     */
    protected function normalizeAdminPages($admin): array
    {
        if (is_array($admin)) {
            if (isset($admin['pages']) && is_array($admin['pages'])) {
                $raw = $admin['pages'];
            } elseif (isset($admin['view']) || isset($admin['action']) || isset($admin['url'])) {
                $raw = [$admin]; // 单页快捷写法
            } else {
                $raw = [];
            }
        } elseif (is_string($admin) && $admin !== '') {
            $raw = [['title' => '设置', 'url' => $admin]]; // 顶层 "admin": "mod/x/..." 快捷
        } else {
            $raw = [];
        }

        $out = [];
        $idx = 0;
        foreach ($raw as $p) {
            if (!is_array($p)) {
                continue;
            }
            $out[] = [
                'key'    => $p['key'] ?? ('p' . $idx),
                'title'  => $p['title'] ?? '页面',
                'icon'   => $p['icon'] ?? 'fa-solid fa-gear',
                'view'   => $p['view'] ?? null,
                'action' => $p['action'] ?? null,
                'url'    => $p['url'] ?? null,
            ];
            $idx++;
        }
        return $out;
    }

    /**
     * 渲染插件某个页面的内容片段（嵌入宿主页内容区）
     */
    protected function dispatchPluginPage(string $name, array $page, bool $isPost): string
    {
        $viewRoot = base_path("mods/{$name}/views");
        View::addPath($viewRoot, true); // 低优先级追加，避免覆盖系统视图

        if (!empty($page['view'])) {
            return View::render($page['view'], ['plugin' => $name, 'is_post' => $isPost], true) ?? '';
        }

        if (!empty($page['action'])) {
            [$controller, $action] = $this->parseAction($page['action']);
            $class = "\\mods\\{$name}\\controllers\\{$controller}Controller";
            if (!class_exists($class) || !method_exists($class, $action)) {
                return '<div class="alert alert-warning mb-0">插件页面控制器不存在：<code>'
                    . htmlspecialchars($class . '::' . $action) . '</code></div>';
            }
            try {
                $inst   = new $class();
                ob_start();
                $result = $inst->$action();
                $echoed = ob_get_clean();
                if ($result instanceof View) {
                    return $result->fetch();
                }
                return $echoed;
            } catch (\Throwable $e) {
                return '<div class="alert alert-danger mb-0">插件页面执行出错：<code>'
                    . htmlspecialchars($e->getMessage()) . '</code></div>';
            }
        }

        return '<div class="text-muted mb-0">该页面未配置内容（view / action / url）。</div>';
    }

    /**
     * 解析 "controller/action"（与 ModController 分发规则一致：连字符转驼峰、action 首字母大写）
     */
    protected function parseAction(string $action): array
    {
        $parts      = explode('/', trim($action, '/'));
        $controller = $parts[0] ?? 'Admin';
        $action     = $parts[1] ?? 'index';
        $controller = str_replace('-', '', ucwords($controller, '-'));
        $action     = str_replace('-', '', ucwords($action, '-'));
        return [$controller, $action];
    }
}
