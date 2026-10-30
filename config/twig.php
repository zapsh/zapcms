<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS
 */

return [
    'options' => [
        // 编译缓存放在 var/cache 下，避免每次请求都重新编译模板
        'cache'         => dirname(__DIR__) . '/var/cache/twig',
        // 模板改动后自动重新编译（生产环境也可安全开启）
        'auto_reload'   => true,
        // 未定义变量不抛异常，模板更容错
        'strict_variables' => false,
        // 字符集
        'charset'       => 'UTF-8',
    ],
    'template_paths'=>[
    ],
    'extensions'=>[
        \zapcms\extensions\twig\PageTwigExtension::class
    ]
];
