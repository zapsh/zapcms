<?php
// 注意：维护模式、调试、日志、后台前缀等运行参数已迁移到数据库 options 表（server.*），
// 请勿在此文件中写入这些配置，以免与后台“基础设置 > 服务器”冲突。
return [

    // Theme
    "theme" => "basic",

    //url
    'suffix'=>".html",

    // i18N
    "fallback_locale"=>"zh-CN",
    "available_languages" => [],

    //secure
    // 应用加密密钥：用于云存储密钥、SMTP 密码、Redis 密码等敏感项的加密存储。
    // 安装向导会自动生成随机值回填；也可手动设为随机长字符串
    // （php -r "echo bin2hex(random_bytes(32));"），或通过环境变量 ZAP_APP_KEY 覆盖。
    "app_key" => ""
];
