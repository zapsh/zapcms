<?php

return [

    // Theme
    "theme" => "basic",

    //url
    'suffix'=>".html",

    // i18N
    "fallback_locale"=>"zh-CN",
    "available_languages" => [],

    //secure
    // 应用加密密钥：用于云存储密钥等敏感项的加密存储（AES-256-GCM）。
    // 请设置为随机长字符串（可用 php -r "echo bin2hex(random_bytes(32));" 生成），
    // 也可用环境变量 ZAP_APP_KEY 覆盖。留空则无法在后台加密保存密钥。
    "app_key" => "17d963a3d1393dd60808c6414d616256678f6516b33a5295893b8d1a294cbaa1",

];
