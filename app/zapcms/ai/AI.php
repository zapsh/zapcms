<?php

namespace zapcms\ai;

use zapcms\ai\Contracts\Provider;
use zapcms\ai\Providers\Anthropic;
use zapcms\ai\Providers\OpenAICompatible;
use zapcms\Hook;

/**
 * AI 统一入口（门面 + 驱动工厂）
 *
 * 最简用法：
 *   use zapcms\ai\AI;
 *   $text = AI::ask('用一句话介绍 ZAP CMS');
 *
 * 多轮对话：
 *   $answer = AI::chat([
 *       ['role' => 'system', 'content' => '你是资深编辑'],
 *       ['role' => 'user',   'content' => '帮我润色：...'],
 *   ], ['temperature' => 0.3], 'deepseek');
 *   echo $answer->text();
 *
 * 配置优先级：config/ai.php < 后台 options 表的 ai.providers（JSON）< 调用时 $options。
 * 扩展新厂商：AI::register('my-driver', MyProvider::class) 或用 Hook::filter('ai.drivers', ...)。
 *
 * @see \zapcms\ai\Contracts\Provider
 */
final class AI
{
    /** @var array<string, Provider> 已实例化的连接 */
    private static array $instances = [];

    /** @var array<string, class-string<Provider>> 运行时注册的驱动类型 */
    private static array $customDrivers = [];

    /**
     * 注册驱动类型（类必须实现 Provider，构造函数接收配置数组）
     */
    public static function register(string $driver, string $class): void
    {
        if (!is_subclass_of($class, Provider::class)) {
            throw AIException::response($driver, '驱动类必须实现 ' . Provider::class);
        }
        self::$customDrivers[$driver] = $class;
        self::$instances = [];
    }

    /** 驱动类型表，可被插件的 ai.drivers 过滤器扩展 */
    public static function drivers(): array
    {
        return (array) Hook::applyFilters('ai.drivers', array_merge([
            'openai-compatible' => OpenAICompatible::class,
            'anthropic'         => Anthropic::class,
        ], self::$customDrivers));
    }

    /** 已配置的连接表 [名称 => 配置]，可被 ai.providers 过滤器扩展 */
    public static function providers(): array
    {
        $file = (array) config('ai.providers', []);
        // 安装流程/CLI/数据库暂不可用时，仅用配置文件，不让 option 查询打断 AI 调用
        try {
            $db = option_get_json('ai.providers', [], true);
            $db = is_array($db) ? $db : [];
        } catch (\Throwable) {
            $db = [];
        }

        return (array) Hook::applyFilters('ai.providers', array_replace_recursive($file, $db));
    }

    public static function defaultProvider(): string
    {
        try {
            $fromDb = option('ai.default');
        } catch (\Throwable) {
            $fromDb = null;
        }
        return (string) ($fromDb ?: config('ai.default', 'openai'));
    }

    /** 取一个可用连接；同名+同配置的实例在一次请求内复用 */
    public static function driver(?string $name = null): Provider
    {
        $name      = $name ?: self::defaultProvider();
        $providers = self::providers();

        if (!isset($providers[$name]) || !is_array($providers[$name])) {
            throw AIException::notConfigured($name);
        }

        $config = $providers[$name];
        $config['name'] = $name;
        $type  = (string) ($config['driver'] ?? 'openai-compatible');
        $class = $config['class'] ?? (self::drivers()[$type] ?? null);

        if (!is_string($class) || !class_exists($class) || !is_subclass_of($class, Provider::class)) {
            throw AIException::unknownDriver($name, $type);
        }

        $key = $name . '|' . md5(serialize($config));
        return self::$instances[$key] ??= new $class($config);
    }

    /** 切换配置后清掉实例缓存 */
    public static function forget(): void
    {
        self::$instances = [];
    }

    public static function isConfigured(?string $name = null): bool
    {
        try {
            return self::driver($name)->isConfigured();
        } catch (AIException) {
            return false;
        }
    }

    /**
     * 对话补全
     *
     * @param string|array $messages 纯文本 / 单条消息 / 消息列表 / ['system'=>'..','user'=>'..']
     * @param array        $options  model、temperature、max_tokens、system、timeout 及厂商原生参数
     * @param string|null  $provider 连接名，null 用默认连接
     */
    public static function chat(string|array $messages, array $options = [], ?string $provider = null): ChatResponse
    {
        $instance = self::driver($provider);
        if (!$instance->isConfigured()) {
            throw AIException::notConfigured($instance->getName());
        }
        return $instance->chat(self::messages($messages, $options), $options);
    }

    /** 只要文本的最简写法 */
    public static function ask(string|array $messages, array $options = [], ?string $provider = null): string
    {
        return self::chat($messages, $options, $provider)->text();
    }

    /** 流式输出：fn(string $delta, array $chunk) */
    public static function stream(
        string|array $messages,
        callable $onDelta,
        array $options = [],
        ?string $provider = null
    ): ChatResponse {
        $instance = self::driver($provider);
        if (!$instance->isConfigured()) {
            throw AIException::notConfigured($instance->getName());
        }
        return $instance->stream(self::messages($messages, $options), $onDelta, $options);
    }

    /** @return array<int, array<int, float>> */
    public static function embeddings(array|string $input, array $options = [], ?string $provider = null): array
    {
        $instance = self::driver($provider);
        if (!$instance->isConfigured()) {
            throw AIException::notConfigured($instance->getName());
        }
        return $instance->embeddings($input, $options);
    }

    /** 单条文本的向量 */
    public static function embed(string $text, array $options = [], ?string $provider = null): array
    {
        return self::embeddings($text, $options, $provider)[0] ?? [];
    }

    /**
     * 把各种简写归一成统一消息结构，并按需插入 system 消息
     *
     * @return array<int, array{role:string, content:mixed}>
     */
    protected static function messages(string|array $messages, array $options): array
    {
        if (is_string($messages)) {
            $messages = [['role' => 'user', 'content' => $messages]];
        }
        // 单条消息 ['role'=>..., 'content'=>...]
        if (isset($messages['role']) || isset($messages['content'])) {
            $messages = [$messages];
        }

        $roles = ['system', 'user', 'assistant', 'tool'];
        $out   = [];
        foreach ($messages as $key => $value) {
            if (is_int($key) && is_array($value) && isset($value['role'])) {
                $out[] = ['role' => (string) $value['role'], 'content' => $value['content'] ?? ''];
            } elseif (is_string($key) && in_array($key, $roles, true) && is_string($value)) {
                $out[] = ['role' => $key, 'content' => $value];
            }
        }

        if ($out === []) {
            throw AIException::response('ai', '消息格式无效');
        }

        $system = (string) ($options['system'] ?? '');
        if ($system !== '') {
            $hasSystem = false;
            foreach ($out as $message) {
                if ($message['role'] === 'system') {
                    $hasSystem = true;
                    break;
                }
            }
            if (!$hasSystem) {
                array_unshift($out, ['role' => 'system', 'content' => $system]);
            }
        }
        return $out;
    }
}
