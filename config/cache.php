<?php
// 缓存配置默认值，可在后台“基础设置 > 缓存”中修改并保存到 options 表（cache.*），
// 运行时以 options 表为准（见 app/bootstrap.php）。
return [
    'default'=>'file',
    'status'=>'disabled',
    'ttl'=>0,
    'file'=>[
        'path'=>var_path('cache')
    ],
    'redis'=>[
        'client'=>'redis',
        'params'=>['127.0.0.1',6379,5.0],
        // 键前缀：与同库其它应用隔离，清理缓存时只删该前缀下的键
        'prefix'=>'zapcms:'
    ]
];
