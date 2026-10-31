<?php
/**
 * ZAP CMS 安装向导
 *
 * 支持 4 个步骤：
 *   ?action=index     — 使用协议
 *   ?action=check     — 环境检测
 *   ?action=database  — 数据库配置 & 执行安装
 *   ?action=done      — 完成页
 * 以及三个 API 端点：
 *   ?action=checkDatabaseConnection   — AJAX：测试数据库连接
 *   ?action=createDBSchema           — AJAX：创建数据表
 *   ?action=importBaseData           — AJAX：导入初始数据 & 完成安装
 */

require '../vendor/autoload.php';

/**
 * 输出 JSON 响应并退出。
 * 替代框架 response()->withJson() — 安装脚本不经过路由器，需自己发送。
 */
function json_response(array $data): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── 已是完整安装则直接跳首页 ──────────────────────────────
// 与 app/bootstrap.php 的 app_is_installed() 保持同一判定：
// install.lock 与 config/database.php 同时存在才算安装完成，
// 否则「lock 残留 + 配置缺失」会导致 首页 ⇄ 安装页 反复重定向。
$installRoot = dirname(__DIR__);
if (is_file($installRoot . '/var/install.lock') && is_file($installRoot . '/config/database.php')) {
    header('Location: ../');
    exit();
}
unset($installRoot);

$app = new \zap\App(realpath('../'));

// ── 路由 ─────────────────────────────────────────────────
$action = $_GET['action'] ?? 'index';

// 纯 API 端点不走视图布局
$apiActions = ['checkDatabaseConnection', 'createDBSchema', 'importBaseData', 'installHtaccess'];
$isApi = in_array($action, $apiActions, true);

if (!$isApi) {
    config_set('config.theme', false);
    \zap\view\View::paths(realpath('views/'));
}

$method = $action . 'Action';
if (!function_exists($method)) {
    exit('404 Page Not Found');
}

call_user_func($method);

// ───────────────────────────────────────────────────────────
//  页面方法
// ───────────────────────────────────────────────────────────

function indexAction()
{
    view("index", ['step' => 1]);
}

function checkAction()
{
    view("check", ['step' => 2, 'checks' => buildEnvChecks()]);
}

function databaseAction()
{
    $token = bin2hex(random_bytes(16));
    session()->set('install_token', $token);
    view("database", ['step' => 3, 'token' => $token]);
}

function doneAction()
{
    if (!session()->get('install_done')) {
        header('Location: index.php?action=database');
        exit();
    }

    view("done", [
        'step' => 4,
        'adminUrl' => session()->get('install_admin_url'),
        'username' => session()->get('install_username'),
        'password' => session()->get('install_password'),
    ]);
}

// ───────────────────────────────────────────────────────────
//  API 方法
// ───────────────────────────────────────────────────────────

function checkDatabaseConnectionAction()
{
    $token = \zap\http\Request::post('token', '');
    if (!$token || !hash_equals(session()->get('install_token', ''), $token)) {
        json_response(['code' => 1, 'msg' => '无效的请求令牌，请刷新页面重试']);
    }

    $db       = \zap\http\Request::post('db', []);
    $driver   = $db['driver'] ?? 'mysql';
    $dbname   = $db['dbname'] ?? 'zapcms';
    $host     = $db['host'] ?? 'localhost';
    $username = $db['username'] ?? 'root';
    $password = $db['password'] ?? '';
    $port     = $db['port'] ?? '3306';

    $options = [
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    ];

    try {
        if ($driver === 'sqlite') {
            $dbFile  = var_path("data/{$dbname}.db");
            $dataDir = var_path('data');
            if (!is_dir($dataDir)) {
                @mkdir($dataDir, 0755, true);
            }
            if (!is_writable($dataDir)) {
                json_response(['code' => 1, 'msg' => '数据目录不可写入: ' . $dataDir]);
            }
            new PDO("sqlite:{$dbFile}", null, null, $options);
            json_response(['code' => 0, 'msg' => 'SQLite 数据库连接成功']);
        }

        // MySQL / MariaDB
        $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4";
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";

        try {
            $pdo = new PDO($dsn, $username, $password, $options);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            if ((int) $e->getCode() === 2002) {
                json_response(['code' => 1, 'msg' => "无法连接 {$host}:{$port}", 'detail' => $e->getMessage()]);
            }
            throw $e;
        }

        new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $username, $password, $options);
        json_response(['code' => 0, 'msg' => '数据库连接成功']);
    } catch (PDOException $e) {
        $code = (int) $e->getCode();
        if ($code === 1045 || $code === 1044) {
            json_response(['code' => 1, 'msg' => '用户名或密码错误', 'detail' => $e->getMessage()]);
        } else {
            json_response(['code' => 1, 'msg' => '数据库连接失败', 'detail' => $e->getMessage()]);
        }
    }
}

/**
 * AJAX 第 2 步：创建数据表
 */
function createDBSchemaAction()
{
    $token = \zap\http\Request::post('token', '');
    if (!$token || !hash_equals(session()->get('install_token', ''), $token)) {
        json_response(['code' => 1, 'msg' => '无效的请求令牌，请刷新页面重试']);
    }

    $db     = \zap\http\Request::post('db', []);
    $driver = $db['driver'] ?? 'mysql';
    $dbname = $db['dbname'] ?? 'zapcms';
    $host   = $db['host'] ?? 'localhost';
    $username = $db['username'] ?? 'root';
    $password = $db['password'] ?? '';
    $port   = $db['port'] ?? '3306';
    $prefix = $db['prefix'] ?? '';

    // 写临时数据库配置
    writeTempDbConfig($driver, $host, $port, $dbname, $username, $password, $prefix);

    try {
        ob_start();
        $schema = new \zapcms\support\CreateTables();
        $schema->createSchema();
        ob_get_clean();

        json_response(['code' => 0, 'msg' => '数据表创建成功']);
    } catch (PDOException $e) {
        json_response(['code' => 1, 'msg' => '创建数据表失败', 'detail' => $e->getMessage()]);
    } catch (\Throwable $e) {
        json_response(['code' => 1, 'msg' => '创建数据表过程发生错误', 'detail' => $e->getMessage()]);
    }
}

/**
 * AJAX 第 3 步：导入初始数据 & 完成安装
 */
function importBaseDataAction()
{
    $token = \zap\http\Request::post('token', '');
    if (!$token || !hash_equals(session()->get('install_token', ''), $token)) {
        json_response(['code' => 1, 'msg' => '无效的请求令牌，请刷新页面重试']);
    }

    $db       = \zap\http\Request::post('db', []);
    $website  = \zap\http\Request::post('website', []);
    $driver   = $db['driver'] ?? 'mysql';
    $dbname   = $db['dbname'] ?? 'zapcms';
    $host     = $db['host'] ?? 'localhost';
    $username = $db['username'] ?? 'root';
    $password = $db['password'] ?? '';
    $port     = $db['port'] ?? '3306';
    $prefix   = $db['prefix'] ?? '';

    $websiteTitle    = $website['title'] ?? 'ZAP CMS';
    $websiteSlogan   = $website['slogan'] ?? 'OpenSource CMS';
    $websiteEmail    = $website['email'] ?? 'admin@localhost';
    $websiteUsername = $website['username'] ?? 'admin';
    $websitePassword = $website['password'] ?? '';

    $errors = [];
    if (empty($websiteUsername)) $errors[] = '管理员用户名不能为空';
    if (empty($websitePassword)) $errors[] = '管理员密码不能为空';
    if (!empty($errors)) {
        json_response(['code' => 1, 'msg' => implode('<br>', $errors)]);
    }

    // 写临时数据库配置（第二个请求中 config 可能已丢失，需重新写入）
    writeTempDbConfig($driver, $host, $port, $dbname, $username, $password, $prefix);

    try {
        ob_start();
        $schema = new \zapcms\support\CreateTables();
        $schema->installBaseData();
        $schema->installDemoData();
        // 新装站点已按最新结构建表，把现有迁移标记为已执行，升级时只跑新增迁移
        (new \zapcms\database\Migrator())->markAllAsRun();
        ob_get_clean();

        // 写入最终数据库配置文件
        if (is_file(config_path('database.php'))) {
            rename(config_path('database.php'), config_path('backup.database.php'));
        }
        \zapcms\support\ZapConfig::createConfig(config('database'), config_path('database.php'));

        \zapcms\services\Option::update('website.title', $websiteTitle);
        \zapcms\services\Option::update('website.slogan', $websiteSlogan);
        \zapcms\services\Option::update('website.email', $websiteEmail);

        \zap\DB::update('admin', [
            'username' => $websiteUsername,
            'password' => \zap\util\Password::hash($websitePassword),
        ], ['id' => 1]);

        // 安装包不包含 var/（运行时目录），这里一次性建好所需子目录
        $runtimeDirs = [
            var_path(''),
            var_path('cache'),
            var_path('logs'),
            var_path('tmp'),
            var_path('data'),
            var_path('backups/sql'),
            var_path('storage/avatar'),
        ];
        foreach ($runtimeDirs as $runtimeDir) {
            if (!is_dir($runtimeDir)) {
                @mkdir($runtimeDir, 0755, true);
            }
        }
        file_put_contents(var_path('install.lock'), date('Y-m-d H:i:s'));

        // 后台前缀取自 options 表（server.admin_prefix），默认 z-admin
        if (!defined('Z_ADMIN_PREFIX')) {
            define('Z_ADMIN_PREFIX', '/' . ltrim((string)\zapcms\services\Option::get('server.admin_prefix', 'z-admin'), '/'));
        }

        $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $baseUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        session()->set('install_done', true);
        session()->set('install_admin_url', $baseUrl . Z_ADMIN_PREFIX);
        session()->set('install_username', $websiteUsername);
        session()->set('install_password', $websitePassword);

        session()->forget('install_token');

        json_response([
            'code' => 0,
            'msg'  => '安装完成！',
            'data' => [
                'admin_url' => $baseUrl . Z_ADMIN_PREFIX,
                'home_url'  => $baseUrl,
                'username'  => $websiteUsername,
                'password'  => $websitePassword,
            ],
        ]);
    } catch (PDOException $e) {
        json_response(['code' => 1, 'msg' => '导入初始数据失败', 'detail' => $e->getMessage()]);
    } catch (\Throwable $e) {
        json_response(['code' => 1, 'msg' => '安装过程发生错误', 'detail' => $e->getMessage()]);
    }
}

/**
 * 一键生成根目录 .htaccess（仅 Apache 且尚未存在时）。
 * 用于「伪静态未开启」时当场补齐必要条件；Nginx 需在 vhost 配置 try_files，无法自动生成。
 */
function installHtaccessAction()
{
    if (install_is_complete()) {
        json_response(['code' => 1, 'msg' => '系统已安装完成，无需生成']);
    }

    $path = base_path('.htaccess');
    if (is_file($path)) {
        json_response(['code' => 1, 'msg' => '根目录已存在 .htaccess，请检查其内容或虚拟主机 AllowOverride 配置']);
    }

    $parts       = parse_url(installBaseUrl());
    $rewriteBase = rtrim((string) ($parts['path'] ?? ''), '/') . '/';
    if ($rewriteBase === '' || $rewriteBase === '//') {
        $rewriteBase = '/';
    }

    $content = "Options +FollowSymLinks -Indexes\n"
        . "RewriteEngine On\n"
        . "RewriteBase {$rewriteBase}\n\n"
        . "# 禁止访问敏感目录\n"
        . "RewriteRule ^(app|config|vendor|var|\\.git|\\.idea)/ - [R=403,L]\n\n"
        . "# 禁止下载敏感文件\n"
        . "RewriteRule ^(composer\\.(json|lock)|Dockerfile|console|README\\.md|LICENSE|CHANGELOG\\.md|\\.env|\\.env\\..*)$ - [R=403,L]\n\n"
        . "# 禁止执行 storage/ 下的 PHP\n"
        . "RewriteCond %{REQUEST_URI} ^/storage/\n"
        . "RewriteRule \\.php$ - [R=403,L]\n\n"
        . "# 主路由规则\n"
        . "RewriteCond %{REQUEST_FILENAME} !-d\n"
        . "RewriteCond %{REQUEST_FILENAME} !-f\n"
        . "RewriteRule ^ index.php [QSA,PT,L]\n";

    if (@file_put_contents($path, $content) === false) {
        json_response(['code' => 1, 'msg' => '写入 .htaccess 失败，请检查根目录写权限']);
    }

    json_response(['code' => 0, 'msg' => '已生成 .htaccess，请刷新本页重新检测']);
}

// ───────────────────────────────────────────────────────────
//  辅助函数
// ───────────────────────────────────────────────────────────

/**
 * 写入临时数据库配置，供建表 / 导入数据使用。
 */
function writeTempDbConfig(
    string $driver,
    string $host,
    string $port,
    string $dbname,
    string $username,
    string $password,
    string $prefix
): void {
    config_set('database', ['default' => 'default', 'connections' => []]);

    if ($driver === 'sqlite') {
        config_set('database.connections.default', [
            'driver'  => 'sqlite',
            'dsn'     => sprintf("sqlite:%s", var_path("data/{$dbname}.db")),
            'prefix'  => $prefix,
            'charset' => 'utf8',
            'collate' => 'utf8_general_ci',
        ]);
        config_set('db.dbpath', "data/{$dbname}.db");
    } else {
        config_set('database.connections.default', [
            'driver'   => 'mysql',
            'host'     => $host,
            'port'     => $port,
            'dbname'   => $dbname,
            'user'     => $username,
            'password' => $password,
            'prefix'   => $prefix,
            'charset'  => 'utf8mb4',
            'collate'  => 'utf8mb4_unicode_ci',
        ]);
    }
}

/**
 * 是否已完成安装（与 app/bootstrap.php 的 app_is_installed() 同判定，安装脚本不加载 bootstrap）
 */
function install_is_complete(): bool
{
    return is_file(base_path('var/install.lock')) && is_file(base_path('config/database.php'));
}

/**
 * 当前站点基础 URL（用于伪静态探测），兼容子目录部署。
 */
function installBaseUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/install/index.php'));
    $base   = dirname(dirname($script)); // /cms/install/index.php → /cms
    if ($base === '/' || $base === '\\' || $base === '.' || $base === '') {
        $base = '';
    }
    return $scheme . '://' . $host . $base;
}

/**
 * 探测一条「干净 URL」的 HTTP 状态码，用于判断伪静态是否生效。
 * 返回：0=无法探测；404=未生效；其它（含 200/302 重定向）= 已生效。
 */
function probeHttpStatus(string $url): int
{
    // 1) cURL
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_NOBODY         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        @curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code;
    }

    // 2) file_get_contents（HEAD）
    if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
        $ctx = stream_context_create([
            'http'  => ['method' => 'HEAD', 'timeout' => 5, 'ignore_errors' => true],
            'https' => ['method' => 'HEAD', 'timeout' => 5, 'ignore_errors' => true],
        ]);
        $headers = @get_headers($url, 1, $ctx);
        if (is_array($headers) && isset($headers[0])
            && preg_match('#HTTP/\d\.\d\s+(\d{3})#i', (string) $headers[0], $m)) {
            return (int) $m[1];
        }
    }

    // 3) fsockopen 兜底
    $parts = parse_url($url);
    if ($parts === false || empty($parts['host'])) {
        return 0;
    }
    $scheme = (($parts['scheme'] ?? 'http') === 'https') ? 'ssl' : 'tcp';
    $host   = $parts['host'];
    $port   = (int) ($parts['port'] ?? ($scheme === 'ssl' ? 443 : 80));
    $path   = ($parts['path'] ?? '/') . (!empty($parts['query']) ? '?' . $parts['query'] : '');
    $fp     = @fsockopen(($scheme === 'ssl' ? 'ssl://' : '') . $host, $port, $errno, $errstr, 5);
    if ($fp === false) {
        return 0;
    }
    fwrite($fp, "GET {$path} HTTP/1.1\r\nHost: {$host}\r\nConnection: close\r\n\r\n");
    $line = fgets($fp, 1024);
    fclose($fp);
    if (is_string($line) && preg_match('#HTTP/\d\.\d\s+(\d{3})#i', $line, $m)) {
        return (int) $m[1];
    }
    return 0;
}

/**
 * 伪静态（URL 重写）检测。
 *
 * 通过向一条「不存在的干净 URL」发起请求来判断：
 *   - 若被 Web 服务器路由到 index.php（未安装站点会 302 跳转到安装页），说明伪静态生效；
 *   - 若返回 404，说明未配置 try_files / mod_rewrite，安装后前台路由将无法工作。
 * 同时辅以 .htaccess 存在性与重写规则检查（仅 Apache）。
 *
 * @return array{pass:bool,value:string,failMsg:string,autofix:string}
 */
function checkRewrite(): array
{
    $server   = strtolower((string) ($_SERVER['SERVER_SOFTWARE'] ?? ''));
    $isApache = str_contains($server, 'apache');
    $isNginx  = str_contains($server, 'nginx');
    $name     = $isNginx ? 'Nginx' : ($isApache ? 'Apache' : '未知');

    // .htaccess（仅 Apache 相关）
    $htPath           = base_path('.htaccess');
    $htExists         = is_file($htPath);
    $htHasRewrite     = false;
    if ($htExists) {
        $c = (string) @file_get_contents($htPath);
        $htHasRewrite = stripos($c, 'RewriteEngine') !== false
            || stripos($c, 'try_files') !== false;
    }

    // 现场探测干净 URL 是否抵达 index.php
    $status  = probeHttpStatus(installBaseUrl() . '/zapcms-rewrite-probe-' . bin2hex(random_bytes(4)));
    $probeOk = ($status !== 0) && ($status !== 404);
    $pass    = $probeOk;

    $value = $name;
    if ($status === 0) {
        $value .= '（无法探测，请确认 allow_url_fopen / curl 可用）';
    } else {
        $value .= '（探测 HTTP ' . $status . '）';
    }
    if ($isApache) {
        $value .= $htExists
            ? ($htHasRewrite ? ' · .htaccess 已配置' : ' · .htaccess 缺少重写规则')
            : ' · 缺少 .htaccess';
    }

    if ($pass) {
        $failMsg = '';
    } elseif ($status === 0) {
        $failMsg = '无法自动探测伪静态，请确认服务器已配置 URL 重写（Nginx try_files / Apache mod_rewrite + .htaccess）后再继续。';
    } elseif ($isApache && !$htExists) {
        $failMsg = 'Apache 环境缺少 .htaccess（或虚拟主机 AllowOverride 未开启），伪静态未生效。可点击下方「生成 .htaccess」后刷新本页。';
    } elseif ($isApache && !$htHasRewrite) {
        $failMsg = '.htaccess 未包含重写规则，或虚拟主机 AllowOverride 未开启，伪静态未生效。';
    } else {
        $failMsg = '伪静态未开启：Nginx 请在 server 段配置 try_files $uri $uri/ /index.php?$query_string; Apache 请开启 mod_rewrite 并在根目录放置 .htaccess。';
    }

    // 仅当 Apache 且根目录无 .htaccess 时才提供一键生成
    $autofix = ($isApache && !$htExists) ? 'index.php?action=installHtaccess' : '';

    return ['pass' => $pass, 'value' => $value, 'failMsg' => $failMsg, 'autofix' => $autofix];
}

function buildEnvChecks(): array
{
    $allDrivers  = PDO::getAvailableDrivers();
    $hasPdoDriver = in_array('mysql', $allDrivers)
        || in_array('pgsql', $allDrivers)
        || in_array('sqlite', $allDrivers);

    // 运行时目录（如 var/）不在安装包内，缺失时先尝试自动创建再判断可写，
    // 避免环境检测因“目录不存在”而误判为不可写。
    $checkWritable = function (string $path): bool {
        $full = base_path($path);
        if (!is_dir($full)) {
            @mkdir($full, 0755, true);
        }
        return is_dir($full) && is_writable($full);
    };

    // 配置文件（config/*.php）在安装前可能尚不存在，由安装程序负责生成。
    // 因此：文件已存在则判断其自身可写；不存在则判断所在目录可写（能被创建即可）。
    $checkFileWritable = function (string $path): bool {
        $full = base_path($path);
        if (is_file($full)) {
            return is_writable($full);
        }
        $dir = dirname($full);
        return is_dir($dir) && is_writable($dir);
    };

    $fileWritableLabel = function (string $path) use ($checkFileWritable): string {
        if (!$checkFileWritable($path)) {
            return '不可写';
        }
        return is_file(base_path($path)) ? '可写' : '不存在（将自动生成）';
    };

    $rewrite = checkRewrite();
    $items = [
        [
            'label'   => 'PHP 版本 ≥ 8.2',
            'value'   => PHP_VERSION,
            'pass'    => version_compare(PHP_VERSION, '8.2.0') >= 0,
            'failMsg' => '需要 PHP 8.2 或更高版本',
        ],
        [
            'label'   => '伪静态（URL 重写）已开启',
            'value'   => $rewrite['value'],
            'pass'    => $rewrite['pass'],
            'failMsg' => $rewrite['failMsg'],
            'autofix' => $rewrite['autofix'],
        ],
        [
            'label'   => 'PDO 扩展',
            'value'   => implode(', ', $allDrivers) ?: '无',
            'pass'    => $hasPdoDriver,
            'failMsg' => '至少需要 PDO MySQL / SQLite / PostgreSQL 之一',
        ],
        [
            'label'   => 'GD 图像扩展',
            'value'   => function_exists('gd_info') ? current(gd_info()) : '不支持',
            'pass'    => function_exists('gd_info'),
            'failMsg' => '不支持 GD 扩展，无法处理图片',
        ],
        [
            'label'   => 'storage/ 目录可写',
            'value'   => $checkWritable('storage') ? '可写' : '不可写',
            'pass'    => $checkWritable('storage'),
            'failMsg' => 'storage/ 目录不可写入',
        ],
        [
            'label'   => 'var/ 目录可写',
            'value'   => $checkWritable('var') ? '可写' : '不可写',
            'pass'    => $checkWritable('var'),
            'failMsg' => 'var/ 目录不可写入',
        ],
        [
            'label'   => 'themes/ 目录可写',
            'value'   => $checkWritable('themes') ? '可写' : '不可写',
            'pass'    => $checkWritable('themes'),
            'failMsg' => 'themes/ 目录不可写入',
        ],
        [
            'label'   => 'config/database.php 可写',
            'value'   => $fileWritableLabel('config/database.php'),
            'pass'    => $checkFileWritable('config/database.php'),
            'failMsg' => 'config/ 目录不可写入，无法生成 config/database.php',
        ],
        [
            'label'   => 'config/config.php 可写',
            'value'   => $fileWritableLabel('config/config.php'),
            'pass'    => $checkFileWritable('config/config.php'),
            'failMsg' => 'config/ 目录不可写入，无法生成 config/config.php',
        ],
    ];

    $allPass = true;
    foreach ($items as $item) {
        if (!$item['pass']) $allPass = false;
    }

    return ['items' => $items, 'allPass' => $allPass];
}
