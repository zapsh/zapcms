<?php

namespace zapcms\controllers;

use zapcms\controllers\AdminController;
use zap\DB;
use zapcms\support\ZapPackageManager;
use zapcms\support\ZapUpdate;
use zap\http\Request;
use zap\http\Response;

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
     * 已安装插件列表
     */
    public function index()
    {
        $plugins = $this->pm->getInstalledPlugins();
        $loadedMods = $this->pm->getLoadedMods();

        // 标记已注册到数据库的插件
        $registeredNames = array_column($plugins, 'name');

        view('plugin.index', [
            'plugins'          => $plugins,
            'loaded_mods'      => $loadedMods,
            'registered_names' => $registeredNames,
            'page_title'       => '插件管理',
            'page_subtitle'    => '管理已安装的插件，启用、禁用、卸载及更新',
            'breadcrumbs'      => [
                ['title' => '控制台', 'url' => \zap\facades\Url::action('Index')],
                ['title' => '插件管理'],
            ],
        ]);
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
            if ($this->pm->isPluginInstalled($name)) {
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
}
