<?php

/*
|-----------------------------------------
| ZAP CMS 路由配置
|-----------------------------------------
| $router 由 App::dispatchRoutes() 在 require 之前创建并传入作用域
|
| 路由注册顺序很重要：
|   1. 后台管理路由（优先匹配）
|   2. 前台 CMS 路由（fallback，兜底所有未被后台匹配的请求）
*/
/**
 * @var \zap\http\Router $router
 */
// 后台前缀保存在 options 表 server.admin_prefix（后台“基础设置 > 服务器”可修改）
// 定义常量兼容旧代码直接引用；同时写入 config('admin.prefix')，框架 UrlHelper 在常量缺失时兜底读取


// ──────────────────── 后台路由 ────────────────────
$adminBootstrap = new \zapcms\Bootstrap();
$adminBootstrap->handle($router);

// ──────────────────── 前台 CMS 路由 ────────────────────
// 作为最后的 fallback 处理器，匹配所有未被后台拦截的请求
$startup = new \app\Startup();
$startup->handle($router);
