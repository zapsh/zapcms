<?php
/**
 * 安全相关配置示例
 *
 * 使用方式：复制为 config/security.php 后按需修改。
 * 说明：security.php 属于本地配置，已加入 .gitignore，不会随代码分发。
 */

return [

    /*
     * CSRF 校验模式：
     *   enforce — 校验失败直接拒绝请求（403），生产环境请始终使用该值
     *   log     — 仅记录到 PHP error_log，用于上线前的灰度观察，
     *             可通过日志快速发现没有携带 Token 的表单/接口
     */
    'csrf_mode' => 'enforce',

    /*
     * CSRF 豁免路由：这些后台路由不做 CSRF 校验（不推荐配置）。
     * 格式：controller@method，均使用 URL 中的 kebab-case 写法。
     * 例如 'update@download' 对应 /z-admin/update/download
     */
    'csrf_exempt' => [
        // 'update@download',
    ],

];
