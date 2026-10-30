<?php

namespace zapcms\controllers;

use zapcms\helpers\Database;
use zapcms\controllers\AdminController;
use zapcms\services\Mailer;
use zapcms\services\Option;
use zapcms\services\SlugHelper;
use zap\http\Request;
use zap\http\Response;
use zap\view\View;
use zap\facades\Url;

class SystemController extends AdminController
{
    function settings(){
        // 基础设置同时管理：站点信息（website.*）、文件上传（upload.*）
        $keyPrefix = ['website\.', 'upload\.'];
        if(Request::isPost()){
            $options = Request::post('options',[]);
            // 网站网址必须为完整的 http(s):// 地址
            $siteUrl = trim((string)($options['website.url'] ?? ''));
            if ($siteUrl !== '') {
                if (!preg_match('#^https?://#i', $siteUrl) || filter_var($siteUrl, FILTER_VALIDATE_URL) === false) {
                    Response::json(['code' => 1, 'msg' => '网站网址必须是完整的 http:// 或 https:// 地址，例如 https://example.com']);
                    return;
                }
                $options['website.url'] = rtrim($siteUrl, '/');
            }
            $optionKeys = Option::getKeys($keyPrefix,'REGEXP');
            foreach ($options as $key=>$value){
                if(in_array($key,$optionKeys)){
                    Option::update($key,$value,null,1);
                }else{
                    Option::add($key,$value,0,1);
                }
            }
            // 服务器设置（server.*）与缓存设置（cache.*）保存到 options 表
            $this->saveServerOptions((array)Request::post('server', []));
            $this->saveCacheOptions((array)Request::post('cache', []));
            // 清除相关配置缓存，使新设置立即生效
            \zap\facades\Cache::delete('_opts_server');
            \zap\facades\Cache::delete('_opts_cache');
            \zap\facades\Cache::delete('_opts_website');
            \zap\facades\Cache::delete('_opts_upload');
            Response::json(['code'=>0,'msg'=>'保存成功']);
        }
        $data = [
            'options'=> Option::getArray($keyPrefix,'REGEXP'),
            // 服务器设置当前值（保存在 options 表 server.*）
            'server' => [
                'log' => (bool)option('server.log', true),
                'debug' => (bool)option('server.debug', false),
                'maintenance' => (bool)option('server.maintenance', false),
                'admin_prefix' => option('server.admin_prefix', 'z-admin'),
            ],
            // 缓存设置当前值（保存在 options 表 cache.*，未设置时取 config/cache.php 默认值）
            'cache' => [
                'status' => option('cache.status', config('cache.status', 'disabled')),
                'default' => option('cache.default', config('cache.default', 'file')),
                'ttl' => option('cache.ttl', config('cache.ttl', 0)),
                'redis_host' => option('cache.redis_host', config('cache.redis.host', '127.0.0.1')),
                'redis_port' => option('cache.redis_port', config('cache.redis.port', 6379)),
                'redis_password' => option('cache.redis_password', config('cache.redis.password', '')),
                'redis_database' => option('cache.redis_database', config('cache.redis.database', 0)),
            ],
        ];
        View::render("system.settings",$data);
    }

    /**
     * 将服务器开关（maintenance / log / debug / admin_prefix）保存到 options 表（server.*）
     */
    private function saveServerOptions(array $server): void
    {
        $map = [
            'maintenance'  => 'bool',
            'log'          => 'bool',
            'debug'        => 'bool',
            'admin_prefix' => 'prefix',
        ];
        foreach ($map as $key => $type) {
            if (!array_key_exists($key, $server)) {
                continue;
            }
            $optionName = 'server.' . $key;
            $value = (string)$server[$key];
            if ($type === 'bool') {
                $value = !empty($value) ? '1' : '0';
            } else {
                $value = trim(preg_replace('/[^a-zA-Z0-9_-]/', '', $value) ?: 'z-admin', '-_');
                $value = $value !== '' ? $value : 'z-admin';
            }
            $existing = Option::get($optionName);
            if ($existing !== null) {
                Option::update($optionName, $value, 0, 1);
            } else {
                Option::add($optionName, $value, 0, 1);
            }
            // 清除单个 option 缓存
            \zap\facades\Cache::delete($optionName);
        }
    }

    /**
     * 将缓存设置（status / default / ttl / redis_*）保存到 options 表（cache.*）
     */
    private function saveCacheOptions(array $cache): void
    {
        $map = [
            'status'         => ['enum', ['disabled', 'enabled']],
            'default'        => ['enum', ['file', 'redis']],
            'ttl'            => ['int'],
            'redis_host'     => ['string'],
            'redis_port'     => ['int'],
            'redis_password' => ['string'],
            'redis_database' => ['int'],
        ];
        foreach ($map as $key => $rule) {
            if (!array_key_exists($key, $cache)) {
                continue;
            }
            $optionName = 'cache.' . $key;
            $value = (string)$cache[$key];
            switch ($rule[0]) {
                case 'enum':
                    $value = in_array($value, $rule[1], true) ? $value : $rule[1][0];
                    break;
                case 'int':
                    $value = (string)max(0, (int)$value);
                    break;
                default:
                    $value = trim($value);
            }
            $existing = Option::get($optionName);
            if ($existing !== null) {
                Option::update($optionName, $value, 0, 1);
            } else {
                Option::add($optionName, $value, 0, 1);
            }
            // 清除单个 option 缓存
            \zap\facades\Cache::delete($optionName);
        }
    }

    /**
     * 日志查看
     */
    public function logs()
    {
        $viewer = new \zapcms\services\LogViewer();

        \view('system.logs', [
            'page_title'    => '日志查看',
            'page_subtitle' => '查看运行日志，支持按级别与关键字筛选',
            'breadcrumbs'   => [
                ['title' => '控制台', 'url' => \zap\facades\Url::action('Index')],
                ['title' => '日志查看'],
            ],
            'log_files' => $viewer->files(),
            'log_dir'   => $viewer->directory(),
        ]);
    }

    /**
     * 日志数据（AJAX：筛选 + 分页）
     */
    public function logsData()
    {
        if (!Request::isPost()) {
            Response::json(['code' => 1, 'msg' => '非法请求']);
            return;
        }

        $page  = max(1, (int)Request::post('page', 1));
        $limit = 100;

        try {
            $viewer = new \zapcms\services\LogViewer();
            $result = $viewer->read(
                (string)Request::post('file', ''),
                [
                    'level'   => (string)Request::post('level', ''),
                    'keyword' => (string)Request::post('keyword', ''),
                ],
                $limit,
                ($page - 1) * $limit
            );

            Response::json(['code' => 0, 'data' => $result]);
        } catch (\Throwable $e) {
            Response::json(['code' => 1, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 下载日志文件
     */
    public function logsDownload()
    {
        $viewer = new \zapcms\services\LogViewer();
        $file   = $viewer->resolve((string)($_GET['file'] ?? ''));

        if ($file === null) {
            http_response_code(404);
            echo '<h1>日志文件不存在</h1>';
            exit;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }

    /**
     * 清空日志（指定文件或全部）
     */
    public function logsClear()
    {
        if (!Request::isPost()) {
            Response::json(['code' => 1, 'msg' => '非法请求']);
            return;
        }

        try {
            $viewer = new \zapcms\services\LogViewer();
            $count  = $viewer->clear((string)Request::post('file', ''));

            Response::json(['code' => 0, 'msg' => "已清空 {$count} 个日志文件"]);
        } catch (\Throwable $e) {
            Response::json(['code' => 1, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 缓存管理：缓存 / 模板 / 缩略图 / 日志 / 临时文件的统计与清理
     */
    public function cache()
    {
        \view('system.cache', [
            'page_title'    => '缓存管理',
            'page_subtitle' => '运行时缓存、模板、缩略图、日志与临时文件',
            'breadcrumbs'   => [
                ['title' => '控制台', 'url' => \zap\facades\Url::action('Index')],
                ['title' => '缓存管理'],
            ],
            'cache_targets' => \zapcms\services\CacheManager::targets(),
            'cache_stats'   => \zapcms\services\CacheManager::stats(),
            'cache_driver'  => \zapcms\services\CacheManager::driver(),
            'cache_enabled' => \zapcms\services\CacheManager::enabled(),
        ]);
    }

    /**
     * 清理缓存。
     *
     * type: cache(默认，数据+模板) / data / template / thumbs / logs / tmp / all
     */
    public function cacheClear()
    {
        if (!Request::isPost()) {
            Response::json(['code' => 1, 'msg' => '非法请求']);
            return;
        }

        $type = strtolower(trim((string)Request::post('type', 'cache')));
        if ($type === '') {
            $type = 'cache';
        }

        try {
            $result = \zapcms\services\CacheManager::clear($type);

            if (!empty($result['redis'])) {
                $msg = 'Redis 缓存已清空（仅限本应用前缀）';
            } else {
                $msg = sprintf(
                    '已清理 %d 个文件，释放 %s',
                    $result['removed'],
                    \zapcms\services\CacheManager::formatBytes($result['freed'])
                );
            }

            if (!empty($result['failed'])) {
                $msg .= sprintf('；%d 个文件被占用，已跳过', $result['failed']);
            }

            Response::json(['code' => 0, 'msg' => $msg, 'data' => $result]);
        } catch (\Throwable $e) {
            Response::json(['code' => 1, 'msg' => '清理失败: ' . $e->getMessage()]);
        }
    }

    /**
     * 发送测试邮件
     */
    public function mailTest()
    {
        if (!Request::isPost()) {
            Response::json(['code' => 1, 'msg' => '非法请求']);
            return;
        }

        $testEmail = Request::post('test_email', '');

        if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            Response::json(['code' => 1, 'msg' => '请输入有效的邮箱地址']);
            return;
        }

        try {
            $siteName = option('website.title', 'ZAP CMS');
            $subject  = "[{$siteName}] SMTP 测试邮件";
            $body     = "<h3>邮件发送测试成功！</h3><p>这是一封来自 <strong>{$siteName}</strong> 的 SMTP 配置测试邮件。</p><p>如果您能收到此邮件，说明 SMTP 邮件配置正确。</p><hr><p style='color:#999;font-size:12px;'>此邮件由系统自动发送，请勿回复。</p>";

            Mailer::send($testEmail, $subject, $body);

            Response::json(['code' => 0, 'msg' => "测试邮件已发送至 {$testEmail}，请检查收件箱"]);
        } catch (\Exception $e) {
            Response::json(['code' => 1, 'msg' => '邮件发送失败: ' . $e->getMessage()]);
        }
    }

    public function sysInfo()
    {
        View::render("system.sysinfo",[
            'page_title' => '服务器信息',
            'page_subtitle' => '系统运行环境与配置详情',
            'breadcrumbs' => [
                ['title' => '控制台', 'url' => \zap\facades\Url::action('Index')],
                ['title' => '服务器信息'],
            ],
        ]);
    }

    public function database(){
        \view('system.database',[
            'page_title' => '数据库管理',
            'page_subtitle' => '查看数据库信息、备份与还原',
            'breadcrumbs' => [
                ['title' => '控制台', 'url' => \zap\facades\Url::action('Index')],
                ['title' => '数据库管理'],
            ],
        ]);
    }

    /**
     * 备份数据库（结构 + 数据）
     */
    public function backup(){
        $result = Database::backup();
        if($result !== false){
            Response::json(['code'=>0,'msg'=>'备份成功', 'file' => basename($result)]);
        }else{
            Response::json(['code'=>1,'msg'=>'备份失败']);
        }
    }

    /**
     * 仅备份数据（不含表结构）
     */
    public function backupData(){
        $result = Database::backupData();
        if($result !== false){
            Response::json(['code'=>0,'msg'=>'数据备份成功', 'file' => basename($result)]);
        }else{
            Response::json(['code'=>1,'msg'=>'备份失败']);
        }
    }

    /**
     * 备份文件列表
     */
    public function backupList(){
        $rawFiles = Database::listBackups();
        // 映射字段以兼容视图（name/mtime 为视图使用的字段名）
        $files = [];
        foreach ($rawFiles as $f) {
            $files[] = [
                'name'       => $f['filename'],
                'size'       => $f['size'],
                'size_human' => $f['size_human'],
                'mtime'      => filemtime($f['file']),
                'time'       => $f['time'],
                'tables'     => $f['tables'],
                'total_rows' => $f['total_rows'],
                'compressed' => $f['compressed'],
            ];
        }

        View::render('system.backup-list', [
            'files' => $files,
            'page_title' => '备份列表',
            'page_subtitle' => '数据库备份文件管理',
            'breadcrumbs' => [
                ['title' => '控制台', 'url' => Url::action('Index')],
                ['title' => '数据库管理', 'url' => Url::action('System@database')],
                ['title' => '备份列表'],
            ],
        ]);
    }

    /**
     * 下载备份文件
     */
    public function backupDownload($filename = null){
        if (empty($filename)) {
            Response::json(['code'=>1,'msg'=>'参数错误']);
            return;
        }
        $backupDir = var_path('backups/sql');
        $filePath = $backupDir . '/' . basename($filename);
        if (!is_file($filePath)) {
            http_response_code(404);
            echo '<h1>文件不存在</h1>';
            exit;
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }

    /**
     * 删除备份文件（同时清理元数据 JSON）
     */
    public function backupDelete(){
        if (!Request::isPost()) {
            Response::json(['code'=>1,'msg'=>'非法请求']);
            return;
        }
        $filename = Request::post('filename');
        if (empty($filename)) {
            Response::json(['code'=>1,'msg'=>'参数错误']);
            return;
        }
        if (Database::deleteBackup(basename($filename))) {
            Response::json(['code'=>0,'msg'=>'删除成功']);
        } else {
            Response::json(['code'=>1,'msg'=>'文件不存在或删除失败']);
        }
    }

    /**
     * 还原数据库（从备份文件）
     */
    public function backupRestore(){
        if (!Request::isPost()) {
            Response::json(['code'=>1,'msg'=>'非法请求']);
            return;
        }
        $filename = Request::post('filename');
        if (empty($filename)) {
            Response::json(['code'=>1,'msg'=>'参数错误']);
            return;
        }
        $filePath = var_path('backups/sql') . '/' . basename($filename);
        if (Database::restore($filePath)) {
            Response::json(['code'=>0,'msg'=>'数据库还原成功']);
        } else {
            Response::json(['code'=>1,'msg'=>'数据库还原失败，请检查备份文件']);
        }
    }

    /**
     * 仅还原数据（跳过建表/删表语句）
     */
    public function backupRestoreData(){
        if (!Request::isPost()) {
            Response::json(['code'=>1,'msg'=>'非法请求']);
            return;
        }
        $filename = Request::post('filename');
        if (empty($filename)) {
            Response::json(['code'=>1,'msg'=>'参数错误']);
            return;
        }
        $filePath = var_path('backups/sql') . '/' . basename($filename);
        if (Database::restoreData($filePath)) {
            Response::json(['code'=>0,'msg'=>'数据还原成功']);
        } else {
            Response::json(['code'=>1,'msg'=>'数据还原失败，请检查备份文件']);
        }
    }

    /**
     * Sitemap 管理页面
     */
    public function sitemap()
    {
        $siteUrl = get_site_base_url();
        $types = ['catalog', 'page', 'article', 'product', 'faq'];

        $sitemaps = [];
        foreach ($types as $type) {
            $count = \zapcms\models\Node::createQuery()
                ->where('node_type', $type)
                ->where('status', 'publish')
                ->count();
            if ($count > 0) {
                $sitemaps[] = [
                    'type'   => $type,
                    'url'    => $siteUrl . '/sitemap-' . $type . '.xml',
                    'count'  => $count,
                ];
            }
        }

        $data = [
            'page_title'    => 'Sitemap',
            'page_subtitle' => 'XML 站点地图管理与查看',
            'breadcrumbs'   => [
                ['title' => '控制台', 'url' => \zap\facades\Url::action('Index')],
                ['title' => '设置'],
                ['title' => 'Sitemap'],
            ],
            'sitemap_index_url' => $siteUrl . '/sitemap.xml',
            'sitemaps' => $sitemaps,
        ];

        \view('system.sitemap', $data);
    }

    /**
     * Slug 生成设置页面
     */
    public function slugSettings()
    {
        if (Request::isPost()) {
            $options = Request::post('options', []);

            $allowedKeys = ['slug.separator', 'slug.style', 'slug.max_length', 'slug.baidu_appid', 'slug.baidu_key'];

            foreach ($options as $key => $value) {
                if (!in_array($key, $allowedKeys, true)) {
                    continue;
                }
                // 基本校验
                if ($key === 'slug.separator') {
                    $value = in_array($value, ['-', '_'], true) ? $value : '-';
                }
                if ($key === 'slug.style') {
                    $value = in_array($value, ['default', 'pinyin', 'translate'], true) ? $value : 'default';
                }
                if ($key === 'slug.max_length') {
                    $value = max(0, (int) $value);
                }
                if ($key === 'slug.baidu_appid' || $key === 'slug.baidu_key') {
                    $value = trim($value);
                }

                $existing = Option::get($key);
                if ($existing !== null) {
                    Option::update($key, (string) $value, 0, 1);
                } else {
                    Option::add($key, (string) $value, 0, 1);
                }
            }

            Response::json(['code' => 0, 'msg' => 'Slug 设置已保存']);
        }

        $data = [
            'page_title'     => 'Slug 生成设置',
            'page_subtitle'  => '配置 URL 别名生成规则，支持拼音和翻译',
            'breadcrumbs'    => [
                ['title' => '控制台', 'url' => Url::action('Index')],
                ['title' => '设置'],
                ['title' => 'Slug 设置'],
            ],
            'slug_separator'    => option('slug.separator', '-'),
            'slug_style'        => option('slug.style', 'default'),
            'slug_max_length'   => option('slug.max_length', '0'),
            'slug_baidu_appid'  => option('slug.baidu_appid', ''),
            'slug_baidu_key'    => option('slug.baidu_key', ''),
        ];

        View::render('system.slug-settings', $data);
    }

    /**
     * AJAX 接口：实时生成 Slug（用于前端表单）
     */
    public function ajaxSlug()
    {
        $title = trim(Request::get('title', Request::post('title', '')));
        if (empty($title)) {
            Response::json(['code' => 1, 'msg' => '请输入标题']);
            return;
        }

        // 支持预览时覆写配置参数
        $style     = Request::get('style', Request::post('style', null));
        $separator = Request::get('separator', Request::post('separator', null));

        // 参数校验
        if ($style !== null && !in_array($style, ['default', 'pinyin', 'translate'], true)) {
            $style = null;
        }
        if ($separator !== null && !in_array($separator, ['-', '_'], true)) {
            $separator = null;
        }

        $slug = SlugHelper::generate($title, null, $style, $separator);

        Response::json(['code' => 0, 'slug' => $slug]);
    }

    /**
     * 固定链接设置页面
     */
    public function permalink()
    {
        if (Request::isPost()) {
            $structure     = Request::post('permalink_structure', '/%postname%/');
            $catalogPrefix = Request::post('catalog_prefix', '');

            // 基本清理（栏目前缀允许留空，表示栏目直接以 /{slug} 访问）
            $structure = '/' . trim(trim($structure), '/') . '/';
            $catalogPrefix = (string) preg_replace('/[^a-zA-Z0-9_-]/', '', $catalogPrefix);

            Option::update('permalink.structure', $structure, 0, 1);
            Option::update('permalink.catalog_prefix', $catalogPrefix, 0, 1);

            // 清除配置缓存
            \zap\facades\Cache::delete('permalink.structure');
            \zap\facades\Cache::delete('permalink.catalog_prefix');

            Response::json(['code' => 0, 'msg' => '固定链接设置已保存']);
        }

        $data = [
            'page_title'    => '固定链接设置',
            'page_subtitle' => '自定义网站链接结构，优化 SEO',
            'breadcrumbs'   => [
                ['title' => '控制台', 'url' => \zap\facades\Url::action('Index')],
                ['title' => '设置'],
                ['title' => '固定链接设置'],
            ],
            'current_structure'     => option('permalink.structure', '/%postname%/'),
            'current_catalog_prefix' => option('permalink.catalog_prefix', ''),
        ];

        \view('system.permalink', $data);
    }

}