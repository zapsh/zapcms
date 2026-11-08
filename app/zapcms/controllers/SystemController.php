<?php

namespace zapcms\controllers;

use zapcms\helpers\Database;
use zapcms\controllers\AdminController;
use zapcms\models\AdminLog;
use zapcms\services\Crypto;
use zapcms\services\Locale;
use zapcms\services\Mailer;
use zapcms\services\Option;
use zapcms\services\SlugHelper;
use zapcms\services\storage\Secret;
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
                // SMTP 密码改走加密存储（secret.mail.smtp_pass），不落明文 options
                if ($key === 'website.smtp_pass') {
                    continue;
                }
                if(in_array($key,$optionKeys)){
                    Option::update($key,$value,null,1);
                }else{
                    Option::add($key,$value,0,1);
                }
            }
            // 服务器设置（server.*）与缓存设置（cache.*）保存到 options 表
            $this->saveServerOptions((array)Request::post('server', []));
            $this->saveCacheOptions((array)Request::post('cache', []));
            // 敏感值（云密钥 / SMTP 密码 / Redis 密码等）用 app_key 加密后写入 options 表
            $this->saveSecrets((array)Request::post('secrets', []), (array)Request::post('secrets_clear', []));
            // 清除相关配置缓存，使新设置立即生效
            \zap\facades\Cache::delete('_opts_server');
            \zap\facades\Cache::delete('_opts_cache');
            \zap\facades\Cache::delete('_opts_website');
            \zap\facades\Cache::delete('_opts_upload');
            // 存储参数可能变化，丢弃已缓存的驱动实例
            \zapcms\services\storage\StorageManager::forget();
            AdminLog::info(AdminLog::CATEGORY_SYSTEM, '保存系统设置', '站点信息 / 服务器 / 缓存设置已更新');
            Response::json(['code'=>0,'msg'=>'保存成功']);
        }
        $data = [
            'crypto_available' => Crypto::available(),
            'secrets_configured' => self::secretsConfigured(),
            'options'=> Option::getArray($keyPrefix,'REGEXP'),
            // 服务器设置当前值（保存在 options 表 server.*）
            'server' => [
                'log' => (bool)option('server.log', true),
                'debug' => (bool)option('server.debug', false),
                'maintenance' => (bool)option('server.maintenance', false),
                'admin_prefix' => option('server.admin_prefix', 'z-admin'),
            ],
            // 缓存设置当前值（保存在 options 表 cache.*，未设置时取运行时默认值）
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
     * 各敏感字段分组（group => 字段列表，键名即 Secret 存储名）。
     * group 用于后台「清除」勾选；字段名统一加命名空间前缀。
     */
    private static function secretGroups(): array
    {
        return [
            'oss'   => ['storage.oss.access_key_id', 'storage.oss.access_key_secret', 'storage.oss.security_token'],
            'cos'   => ['storage.cos.secret_id', 'storage.cos.secret_key'],
            'r2'    => ['storage.r2.access_key_id', 'storage.r2.secret_access_key'],
            'mail'  => ['mail.smtp_pass'],
            'cache' => ['cache.redis_password'],
            'slug'  => ['slug.baidu_key'],
        ];
    }

    /**
     * 保存敏感值：非空值加密后写入 options（secret.*，autoload=0）；
     * 留空表示保留原值；secrets_clear 指定的分组清空其已存密钥。
     */
    private function saveSecrets(array $secrets, array $clear): void
    {
        if (!Crypto::available()) {
            return;
        }

        $groups = self::secretGroups();
        foreach ($clear as $group) {
            foreach ($groups[$group] ?? [] as $field) {
                Secret::remove($field);
            }
        }

        foreach ($groups as $list) {
            foreach ($list as $field) {
                $value = trim((string) ($secrets[$field] ?? ''));
                if ($value === '') {
                    continue; // 留空 = 保留原值
                }
                Secret::set($field, $value);
            }
        }

        // 存储密钥变化，丢弃已缓存的驱动实例
        \zapcms\services\storage\StorageManager::forget();
    }

    /** 后台显示用的「是否已配置」标记（平铺 field => bool） */
    private static function secretsConfigured(): array
    {
        $result = [];
        foreach (self::secretGroups() as $list) {
            foreach ($list as $field) {
                $result[$field] = Secret::configured($field);
            }
        }
        return $result;
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
     *
     * 保存后必须让启动配置快照失效，否则下一个请求仍按旧配置创建缓存驱动。
     */
    private function saveCacheOptions(array $cache): void
    {
        $map = [
            'status'         => ['enum', ['disabled', 'enabled']],
            'default'        => ['enum', ['file', 'redis']],
            'ttl'            => ['int'],
            'redis_host'     => ['string'],
            'redis_port'     => ['int'],
            // redis_password 改走加密存储（secret.cache.redis_password），不在 cache.* 落明文
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
        // 启动配置快照失效：下次请求重新从 options 表构建缓存驱动配置
        app_cache_config_forget();
    }

    /**
     * 日志查看：系统日志 / 审计日志两个 tab。
     * 点击 tab 会切换 URL（?tab=file|audit）并整页渲染对应内容。
     */
    public function logs()
    {
        $tab = strtolower((string)req()->get('tab', 'audit'));
        if (!in_array($tab, ['file', 'audit'], true)) {
            $tab = 'audit';
        }
        $isAudit = $tab === 'audit';

        $viewer = new \zapcms\services\LogViewer();

        \view('system.logs', [
            'page_title'    => $isAudit ? '审计日志' : '日志查看',
            'page_subtitle' => $isAudit
                ? '后台操作审计记录，可按级别 / 分类 / 关键字筛选'
                : '系统运行日志文件，可按级别 / 关键字筛选',
            'breadcrumbs'   => [
                ['title' => '控制台', 'url' => \zap\facades\Url::action('Index')],
                ['title' => $isAudit ? '审计日志' : '日志查看'],
            ],
            'active_tab' => $tab,
            'tab_urls'   => [
                'file'  => \zap\facades\Url::action('System@logs', ['tab' => 'file']),
                'audit' => \zap\facades\Url::action('System@logs', ['tab' => 'audit']),
            ],
            'log_files'  => $viewer->files(),
            'log_dir'    => $viewer->directory(),
            'categories' => ['auth', 'user', 'content', 'system', 'security'],
        ]);
    }

    /**
     * 日志数据（AJAX：筛选 + 分页）
     *
     * source=file（默认）读系统日志文件；source=db 读审计日志。
     */
    public function logsData()
    {
        if (!Request::isPost()) {
            Response::json(['code' => 1, 'msg' => '非法请求']);
            return;
        }

        $source = strtolower((string)Request::post('source', 'file'));
        $page   = max(1, (int)Request::post('page', 1));
        $limit  = 100;

        try {
            $viewer = new \zapcms\services\LogViewer();

            if ($source === 'db') {
                $result = $viewer->readDb(
                    [
                        'category' => (string)Request::post('category', ''),
                        'level'    => (string)Request::post('level', ''),
                        'keyword'  => (string)Request::post('keyword', ''),
                    ],
                    $limit,
                    ($page - 1) * $limit
                );
            } else {
                $result = $viewer->read(
                    (string)Request::post('file', ''),
                    [
                        'level'   => (string)Request::post('level', ''),
                        'keyword' => (string)Request::post('keyword', ''),
                    ],
                    $limit,
                    ($page - 1) * $limit
                );
            }

            Response::json(['code' => 0, 'data' => $result]);
        } catch (\Throwable $e) {
            Response::json(['code' => 1, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 下载日志（系统日志文件或审计日志）
     */
    public function logsDownload()
    {
        $source = strtolower((string)($_GET['source'] ?? 'file'));

        if ($source === 'db') {
            $rows = \zapcms\models\AdminLog::search([], 5000, 0)['entries'];
            $out  = "时间\t级别\t分类\t用户\t内容\n";
            foreach ($rows as $r) {
                $text = ($r['title'] ?? '')
                    . (($r['content'] ?? '') !== '' ? '：' . $r['content'] : '');
                $out .= sprintf(
                    "%s\t%s\t%s\t%s\t%s\n",
                    date('Y-m-d H:i:s', (int)($r['request_time'] ?? 0)),
                    $r['level'] ?? '',
                    $r['category'] ?? '',
                    $r['username'] ?? 'system',
                    str_replace(["\r", "\n", "\t"], ' ', $text)
                );
            }
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="audit_logs_' . date('Ymd_His') . '.txt"');
            echo $out;
            exit;
        }

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
     * 清空日志（指定系统日志文件或全部；source=db 时清空审计日志）
     */
    public function logsClear()
    {
        if (!Request::isPost()) {
            Response::json(['code' => 1, 'msg' => '非法请求']);
            return;
        }

        $source = strtolower((string)Request::post('source', 'file'));

        try {
            if ($source === 'db') {
                $pdo   = \zap\DB::getPDO();
                $table = $pdo->quoteTable('admin_logs');
                $count = (int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
                $pdo->rawExec("DELETE FROM {$table}");

                AdminLog::warning(AdminLog::CATEGORY_SECURITY, '清空审计日志', "管理员清空了 {$count} 条审计记录");
                Response::json(['code' => 0, 'msg' => "已清空 {$count} 条审计日志"]);
                return;
            }

            $viewer = new \zapcms\services\LogViewer();
            $count  = $viewer->clear((string)Request::post('file', ''));

            AdminLog::info(AdminLog::CATEGORY_SYSTEM, '清空系统日志', "已清空 {$count} 个日志文件");
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

            AdminLog::info(AdminLog::CATEGORY_SYSTEM, '清理缓存', '类型：' . $type);

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
            AdminLog::info(AdminLog::CATEGORY_SYSTEM, '数据库备份', '类型：结构 + 数据，文件：' . basename($result));
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
            AdminLog::info(AdminLog::CATEGORY_SYSTEM, '数据库备份', '类型：仅数据，文件：' . basename($result));
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

            $allowedKeys = ['slug.separator', 'slug.style', 'slug.max_length', 'slug.baidu_appid'];

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
                if ($key === 'slug.baidu_appid') {
                    $value = trim($value);
                }

                $existing = Option::get($key);
                if ($existing !== null) {
                    Option::update($key, (string) $value, 0, 1);
                } else {
                    Option::add($key, (string) $value, 0, 1);
                }
            }

            // 百度翻译密钥改走加密存储（secret.slug.baidu_key）
            $this->saveSecrets((array) Request::post('secrets', []), (array) Request::post('secrets_clear', []));

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
            'crypto_available'  => Crypto::available(),
            'slug_baidu_key_configured' => Secret::configured('slug.baidu_key'),
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
            $structure     = Request::post('permalink_structure', '/%node_slug%/');
            $catalogPrefix = Request::post('catalog_prefix', '');

            // 基本清理（栏目前缀允许留空，表示栏目直接以 /{slug} 访问）
            if (str_contains($structure, '?')) {
                // 基础型（如 /?n=%node_id%）：保留查询串形式，仅去掉多余斜杠
                $structure = '/' . ltrim(rtrim(trim($structure), '/'), '/');
            } else {
                $structure = '/' . trim(trim($structure), '/') . '/';
            }
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
            'current_structure'     => option('permalink.structure', '/%node_slug%/'),
            'current_catalog_prefix' => option('permalink.catalog_prefix', ''),
        ];

        \view('system.permalink', $data);
    }

    /**
     * 多语言设置页面
     */
    public function languages()
    {
        if (Request::isPost()) {
            $locales = Request::post('locales', []);
            if (!is_array($locales) || $locales === []) {
                Response::json(['code' => 1, 'msg' => '请至少选择一个语言']);
                return;
            }

            $default = (string) Request::post('default_locale', '');
            $custom  = trim((string) Request::post('custom_locale', ''));

            $list = [];
            foreach ($locales as $code) {
                $code = Locale::normalize((string) $code);
                if ($code !== '') {
                    $list[] = $code;
                }
            }
            if ($custom !== '') {
                $list[] = Locale::normalize($custom);
            }

            if ($list === []) {
                Response::json(['code' => 1, 'msg' => '请至少选择一个语言']);
                return;
            }

            Locale::save($list, $default !== '' ? $default : null);

            Response::json([
                'code'    => 0,
                'msg'     => '多语言设置已保存',
                'default' => Locale::defaultLocale(),
            ]);
            return;
        }

        $data = [
            'page_title'    => '多语言设置',
            'page_subtitle' => '配置站点支持的语言',
            'breadcrumbs'   => [
                ['title' => '控制台', 'url' => \zap\facades\Url::action('Index')],
                ['title' => '设置'],
                ['title' => '多语言设置'],
            ],
            'locales'        => Locale::all(),
            'default_locale' => Locale::defaultLocale(),
            'known_locales'  => Locale::knownCodes(),
            'enabled'        => Locale::enabled(),
        ];

        \view('system.languages', $data);
    }

}