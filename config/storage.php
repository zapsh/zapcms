<?php

/*
|--------------------------------------------------------------------------
| 对象存储密钥
|--------------------------------------------------------------------------
| 密钥只放环境变量或本文件，不写入 options 表（后台可见、可能被 autoload）。
| 非敏感项（bucket / endpoint / CDN 域名 / 路径前缀 / 存储模式）在后台
| 「系统设置 → 文件上传」里配置，键名统一为 upload.*。
|
| 阿里云 OSS 自签（Signature V1，HMAC-SHA1），无需安装官方 SDK。
| 若使用 STS 临时凭证，额外设置 OSS_SECURITY_TOKEN，签名时会带上
| x-oss-security-token 头。
*/

return [
    'oss' => [
        'access_key_id'     => getenv('OSS_ACCESS_KEY_ID') ?: '',
        'access_key_secret' => getenv('OSS_ACCESS_KEY_SECRET') ?: '',
        'security_token'    => getenv('OSS_SECURITY_TOKEN') ?: '',
        'timeout'           => 60,
    ],
];
