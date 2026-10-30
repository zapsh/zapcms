<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2023/12/27 上午11:09
 * @lastModified 2023/12/22 下午12:02
 *
 */

const ZAP_CMS_VERSION = '1.0.18';
const ZAP_CMS_RELEASE_DATE = '2026-10-30';

define('APP_ROOT', dirname(__DIR__));

if (PHP_VERSION_ID < 80200) {
    exit('ZAP CMS requires PHP 8.2.0+.');
}

/**
 * 是否已完成安装。
 *
 * 判定依据（两者缺一不可）：
 *   - var/install.lock   安装向导最后一步写入，是「安装完成」的唯一凭据；
 *   - config/database.php 数据库连接配置。
 *
 * 只判断 lock 会把「lock 残留但配置被删」误判为已安装；
 * 只判断 database.php 会把「配置已写入但建表没跑完」误判为已安装。
 * 一律使用 APP_ROOT 绝对路径，避免依赖进程工作目录（CLI / 子目录部署）。
 */
function app_is_installed(): bool
{
    static $installed = null;

    if ($installed === null) {
        $installed = is_file(APP_ROOT . '/var/install.lock')
            && is_file(APP_ROOT . '/config/database.php');
    }

    return $installed;
}

/**
 * 安装向导地址，兼容子目录部署（如 /cms/install/index.php）。
 */
function app_install_url(): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $base = rtrim(dirname($script), '/');

    if ($base === '/' || $base === '.' || $base === '\\') {
        $base = '';
    }

    return $base . '/install/index.php';
}

/**
 * 输出极简提示页并结束请求（用于无法发送重定向头的场景）。
 *
 * @param array{url: string, text: string}|null $link
 */
function app_notice(int $status, string $title, string $message, ?array $link = null): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
    }

    $linkHtml = '';
    if ($link !== null) {
        $linkHtml = '<p style="margin:0;"><a href="' . htmlspecialchars($link['url'], ENT_QUOTES)
            . '" style="color:#0d6efd;">' . htmlspecialchars($link['text'], ENT_QUOTES) . '</a></p>';
    }

    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . htmlspecialchars($title, ENT_QUOTES) . '</title></head>'
        . '<body style="margin:0;display:flex;align-items:center;justify-content:center;min-height:100vh;'
        . 'background:#f5f6fa;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:#333;">'
        . '<div style="max-width:560px;padding:24px;text-align:center;">'
        . '<h1 style="margin:0 0 12px;font-size:22px;">' . htmlspecialchars($title, ENT_QUOTES) . '</h1>'
        . '<p style="margin:0 0 16px;font-size:14px;line-height:1.7;color:#666;">'
        . htmlspecialchars($message, ENT_QUOTES) . '</p>'
        . $linkHtml
        . '</div></body></html>';
    exit;
}

/**
 * 跳转到安装向导：有 header 权限用 302，已经输出过内容时退化为提示页，
 * 避免 header() 警告或白屏。
 */
function app_redirect_install(): void
{
    $url = app_install_url();

    if (!headers_sent()) {
        header('Location: ' . $url, true, 302);
        exit;
    }

    app_notice(200, 'ZAP CMS 尚未安装', '检测到系统还未完成安装，请先运行安装向导。', [
        'url'  => $url,
        'text' => '进入安装向导',
    ]);
}

/**
 * 未安装时引导到安装向导。
 *
 * 跳过判定：
 *   - 已安装；
 *   - 显式声明 ZAP_SKIP_INSTALL_CHECK（特殊场景，比如自检脚本）；
 *   - CLI 运行（console / 队列），改为 stderr 提示并以非 0 退出；
 *   - 当前请求已在 install 目录内，避免「首页 ⇄ 安装页」死循环。
 */
function app_require_installed(): void
{
    if (app_is_installed() || defined('ZAP_SKIP_INSTALL_CHECK')) {
        return;
    }

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'ZAP CMS is not installed yet: ' . APP_ROOT . PHP_EOL);
        exit(1);
    }

    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (str_contains($script, '/install/')) {
        return;
    }

    app_redirect_install();
}

/**
 * 检测 PDO 异常是否为"数据库表不存在"引起。
 * 未安装时跳转安装向导；已安装则给出明确提示（避免重定向死循环）；
 * 非表缺失错误不做处理，由上层继续抛出，交给 ErrorHandler 渲染。
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

    if (!$tableMissing) {
        return;
    }

    // 已安装却查不到表：多半是连到了空库 / 表被误删。
    // 此时再跳安装页只会「安装页 → 首页」来回重定向，直接给出可诊断的提示。
    if (app_is_installed()) {
        app_notice(500, '数据库结构缺失', '系统已安装，但当前数据库中找不到所需的数据表。请检查 config/database.php 是否指向了正确的数据库。');
    }

    app_redirect_install();
}

// Install — 未安装时进入安装流程
app_require_installed();

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
    // 键前缀：避免清理缓存时 flushDB 误删同库其它应用的数据
    'prefix'   => (string)config('cache.redis_prefix', 'zapcms:'),
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
