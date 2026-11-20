<?php
/**
 * Basic 主题 · Admin 配置
 * 后台登录后自动加载，使用 AdminHook 向后端页面注入 UI
 *
 * 其他主题可直接：
 *   1. 复制本文件到 themes/{你的主题}/admin/functions.php
 *   2. 只写你需要的 hook，不需要的清掉即可
 */

defined('IN_ZAPCMS_ADMIN') or exit;

// use \zapcms\Hook;

// ──── 注入后台额外 CSS ─────────────────────────────────────
// Hook::on('admin_head', function () {
//     return '<link rel="stylesheet" href="' . themes_url_basic('css/admin.css') . '">';
// });

// ──── 在用户下拉菜单增加入口 ────────────────────────────────
// Hook::on('admin_user_dropdown', function () {
//     return '<div class="dropdown-divider"></div>
//             <a class="dropdown-item" href="' . url_action('Theme@settings') . '">
//                 <i class="fa fa-palette me-2"></i>主题设置
//             </a>';
// });

// ──── 注入后台底部 JS ──────────────────────────────────────
// Hook::on('admin_foot', function () {
//     return '<script src="' . themes_url_basic('js/admin.js') . '"></script>';
// });

// ──── 在内容区前后插入自定义区块 ─────────────────────────────
// Hook::on('admin_content_before', function () {
//     return '<div class="alert alert-info mb-3"><i class="fa fa-info-circle me-1"></i>欢迎使用 Basic 主题</div>';
// });

// Hook::on('admin_content_after', function () {
//     return ''; // 可放置自定义 HTML
// });
