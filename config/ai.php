<?php

/*
|--------------------------------------------------------------------------
| AI 接入配置
|--------------------------------------------------------------------------
| 密钥请用环境变量，不要把真实 key 写进版本库。
|
| 也可以在后台 options 表写两条记录覆盖这里，无需改文件：
|   ai.default     = "deepseek"                     （字符串，默认连接名）
|   ai.providers   = {"openai":{"api_key":"sk-..."}}（JSON，按连接名逐字段覆盖）
|
| 自定义厂商：在 provider 里加 'class' => \Your\Provider::class，
| 或用 \zapcms\ai\AI::register('type', \Your\Provider::class) 注册驱动类型。
*/

return [
    'default' => getenv('AI_PROVIDER') ?: 'openai',

    'providers' => [

        'openai' => [
            'driver'   => 'openai-compatible',
            'base_url' => getenv('AI_OPENAI_BASE_URL') ?: 'https://api.openai.com/v1',
            'api_key'  => getenv('AI_OPENAI_API_KEY') ?: '',
            'model'    => getenv('AI_OPENAI_MODEL') ?: 'gpt-4o-mini',
            'timeout'  => 60,
        ],

        'deepseek' => [
            'driver'   => 'openai-compatible',
            'base_url' => getenv('AI_DEEPSEEK_BASE_URL') ?: 'https://api.deepseek.com/v1',
            'api_key'  => getenv('AI_DEEPSEEK_API_KEY') ?: '',
            'model'    => getenv('AI_DEEPSEEK_MODEL') ?: 'deepseek-chat',
            'timeout'  => 90,
        ],

        // 本地模型，无需密钥
        'ollama' => [
            'driver'          => 'openai-compatible',
            'base_url'        => getenv('AI_OLLAMA_BASE_URL') ?: 'http://127.0.0.1:11434/v1',
            'api_key'         => '',
            'allow_empty_key' => true,
            'model'           => getenv('AI_OLLAMA_MODEL') ?: 'qwen2.5:7b',
            'timeout'         => 180,
        ],

        'anthropic' => [
            'driver'   => 'anthropic',
            'base_url' => getenv('AI_ANTHROPIC_BASE_URL') ?: 'https://api.anthropic.com/v1',
            'api_key'  => getenv('AI_ANTHROPIC_API_KEY') ?: '',
            'auth'     => 'x-api-key',
            'headers'  => ['anthropic-version: 2023-06-01'],
            'model'    => getenv('AI_ANTHROPIC_MODEL') ?: 'claude-3-5-sonnet-latest',
            'max_tokens' => 2048,
            'timeout'  => 90,
        ],
    ],
];
