<?php

/*
|--------------------------------------------------------------------------
| 对象存储密钥
|--------------------------------------------------------------------------
| 密钥只放环境变量或本文件，不写入 options 表（后台可见、可能被 autoload）。
| 非敏感项（bucket / endpoint / CDN 域名 / 路径前缀 / 存储模式）在后台
| 「系统设置 → 文件上传」里配置，键名统一为 upload.*。
|
| 各厂商均采用自签（零 composer 依赖）：
|  - 阿里云 OSS  ：Signature V1（HMAC-SHA1）
|  - 腾讯云 COS  ：经典签名（q-sign-algorithm=sha1，HMAC-SHA1）
|  - Cloudflare R2：AWS Signature V4（HMAC-SHA256，S3 兼容）
|
| 均要求 bucket 为公共读（私有桶签名 URL 会过期，不能写入 DB）。
*/

return [
    'oss' => [
        'access_key_id'     => getenv('OSS_ACCESS_KEY_ID') ?: '',
        'access_key_secret' => getenv('OSS_ACCESS_KEY_SECRET') ?: '',
        'security_token'    => getenv('OSS_SECURITY_TOKEN') ?: '',
        'timeout'           => 60,
    ],

    'cos' => [
        'secret_id'  => getenv('COS_ID') ?: '',
        'secret_key' => getenv('COS_KEY') ?: '',
        'timeout'    => 60,
    ],

    'r2' => [
        'access_key_id'     => getenv('R2_ACCESS_KEY_ID') ?: '',
        'secret_access_key' => getenv('R2_SECRET_ACCESS_KEY') ?: '',
        'timeout'           => 60,
    ],
];
