<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2023/12/27 上午11:09
 * @lastModified 2023/12/22 下午12:02
 *
 */

const ZAP_CMS_VERSION = '1.0.2';
const ZAP_CMS_RELEASE_DATE = '2026-4-20';

define('APP_ROOT', dirname(__DIR__));

if (PHP_VERSION_ID < 80200) {
    exit('ZAP CMS requires PHP 8.2.0+.');
}

/**
 * 检测 PDO 异常是否为"数据库表不存在"引起。
 * 若检测到表不存在，跳转到安装页面；否则不做处理，由上层继续抛出。
 *
 * 此函数独立于 Option.php，在 bootstrap 层统一拦截数据库异常，
 * 避免在 Option 等业务类中耦合安装跳转逻辑。
 *
 * @param \PDOException $e
 */
function check_database_error(\PDOException $e): void
{
    $message = $e->getMessage();

    // SQLite:  "no such table: xxx"
    // MySQL:   "Table 'xxx' doesn't exist" / "Base table or view not found: xxx"
    $tableMissing = preg_match('/no such table/i', $message)
        || preg_match("/Table\s+['`][^'`]+['`]\s+doesn't exist/i", $message)
        || preg_match('/Base table or view not found/i', $message);

    if ($tableMissing) {
        if (!headers_sent()) {
            header('Location: /install/index.php');
        } else {
            echo '<script>location.href="/install/index.php";</script>';
        }
        exit();
    }
}

// Install — 未安装时进入安装流程
if (!is_file('config/database.php') || !is_file('var/install.lock')) {
    header('Location: /install/index.php');
    exit();
}

require "vendor/autoload.php";
$app = new \zap\App(dirname(__DIR__));

// ──────────────────── 运行参数（server.* / cache.*）从 options 表读取 ────────────────────
// 维护模式、调试、日志、后台前缀等不再写入 config/config.php，统一保存在数据库 options 表，
// 后台“基础设置 > 服务器 / 缓存”中修改后立即生效。
// 读取优先走缓存（get_options），缓存驱动异常（如 Redis 不可用）时退化为直连数据库，保证站点可用。
try {
    $serverOptions = get_options('server', 'REGEXP');
    $cacheOptions  = get_options('cache', 'REGEXP');
} catch (\PDOException $e) {
    check_database_error($e);
    throw $e;
} catch (\Throwable $e) {
    $serverOptions = \zapcms\services\Option::getArray('server', 'REGEXP');
    $cacheOptions  = \zapcms\services\Option::getArray('cache', 'REGEXP');
}

$maintenance = (bool)($serverOptions['server.maintenance'] ?? false);
$adminPrefix = '/' . trim((string)($serverOptions['server.admin_prefix'] ?? 'z-admin'), '/');
define('Z_ADMIN_PREFIX', $adminPrefix);
config_set('admin.prefix', Z_ADMIN_PREFIX);

// 同步调试 / 日志开关到运行时配置，使其立即生效
config_set('config.debug', (bool)($serverOptions['server.debug'] ?? false));
config_set('config.log', (bool)($serverOptions['server.log'] ?? true));
if (config('config.debug', false)) {
    error_reporting(E_ALL ^ E_NOTICE);
} else {
    error_reporting(0);
}

// 缓存设置（cache.*）覆盖 config/cache.php 默认值
foreach ($cacheOptions as $name => $value) {
    config_set('cache.' . substr($name, 6), $value);
}
config_set('cache.redis', [
    'client'   => 'redis',
    'params'   => [
        config('cache.redis_host', '127.0.0.1'),
        (int)config('cache.redis_port', 6379),
        5.0,
    ],
    'password' => (string)config('cache.redis_password', ''),
    'database' => (int)config('cache.redis_database', 0),
]);

// Maintenance Mode 开关（server.maintenance，保存在 options 表）：
// 开启时仅放行后台请求，前台访问返回 503 维护页面
if ($maintenance) {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $isAdminUri = $adminPrefix !== '/' && stripos($requestUri, $adminPrefix) !== false;
    if (!$isAdminUri) {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>系统维护中</title></head><body style="margin:0;display:flex;align-items:center;justify-content:center;'
            . 'min-height:100vh;background:#f5f6fa;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;">'
            . '<div style="text-align:center;padding:24px;"><div style="font-size:64px;">🛠️</div>'
            . '<h1 style="margin:16px 0 8px;color:#333;font-size:24px;">系统维护中</h1>'
            . '<p style="color:#888;margin:0;font-size:14px;">网站正在进行维护，请稍后再访问。</p></div></body></html>';
        exit();
    }
}

try {
    $app->run();
} catch (\PDOException $e) {
    check_database_error($e);
    // 非表缺失错误，继续抛出给 ErrorHandler 渲染错误页
    throw $e;
}
