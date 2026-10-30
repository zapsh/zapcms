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
$apiActions = ['checkDatabaseConnection', 'createDBSchema', 'importBaseData'];
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

    $items = [
        [
            'label'   => 'PHP 版本 ≥ 8.2',
            'value'   => PHP_VERSION,
            'pass'    => version_compare(PHP_VERSION, '8.2.0') >= 0,
            'failMsg' => '需要 PHP 8.2 或更高版本',
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
