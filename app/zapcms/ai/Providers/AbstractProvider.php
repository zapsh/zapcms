<?php

namespace zapcms\ai\Providers;

use zap\exception\CurlException;
use zap\net\Requests;
use zap\net\Response;
use zapcms\ai\AIException;
use zapcms\ai\ChatResponse;
use zapcms\ai\Contracts\Provider;

/**
 * 驱动公共底座：配置、鉴权头、HTTP、错误归一化、日志
 *
 * 配置键：
 *  - base_url       接口根地址，如 https://api.openai.com/v1
 *  - api_key        密钥（本地模型可配合 allow_empty_key 留空）
 *  - auth           bearer（默认）| x-api-key | header | none
 *  - auth_header    auth=header 时使用的头名，如 Authorization 或 api-key
 *  - headers        额外请求头，字符串数组，如 ['anthropic-version: 2023-06-01']
 *  - model          默认模型
 *  - timeout        请求超时秒数，默认 60
 *  - allow_empty_key 为 true 时允许无密钥（Ollama 等本地服务）
 */
abstract class AbstractProvider implements Provider
{
    /** 门面/业务层保留参数，不会透传给厂商接口 */
    protected const RESERVED_OPTIONS = [
        'provider', 'driver', 'timeout', 'system', 'model', 'stream',
        'base_url', 'api_key', 'auth', 'headers', 'name', 'allow_empty_key',
    ];

    public function __construct(protected array $config = [])
    {
    }

    public function getName(): string
    {
        return (string) ($this->config['name'] ?? $this->config['driver'] ?? 'ai');
    }

    public function isConfigured(): bool
    {
        if ((string) ($this->config['base_url'] ?? '') === '') {
            return false;
        }
        if (!empty($this->config['allow_empty_key'])) {
            return true;
        }
        return (string) ($this->config['api_key'] ?? '') !== '';
    }

    /**
     * 默认流式实现：厂商未提供 SSE 时退化为「一次性返回 + 单次回调」
     */
    public function stream(array $messages, callable $onDelta, array $options = []): ChatResponse
    {
        $response = $this->chat($messages, $options);
        if ($response->content !== '') {
            $onDelta($response->content, $response->raw);
        }
        return $response;
    }

    public function embeddings(array|string $input, array $options = []): array
    {
        throw AIException::unsupported($this->getName(), 'embeddings');
    }

    protected function cfg(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /** 支持传绝对 URL（如 Azure 的完整 endpoint），否则拼到 base_url 之后 */
    protected function endpoint(string $path): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        return rtrim((string) $this->cfg('base_url', ''), '/') . '/' . ltrim($path, '/');
    }

    protected function headers(): array
    {
        $headers = array_values((array) $this->cfg('headers', []));
        $key = (string) $this->cfg('api_key', '');

        if ($key !== '') {
            switch ((string) $this->cfg('auth', 'bearer')) {
                case 'none':
                    break;
                case 'x-api-key':
                    $headers[] = 'x-api-key: ' . $key;
                    break;
                case 'header':
                    $headers[] = (string) $this->cfg('auth_header', 'Authorization') . ': ' . $key;
                    break;
                default:
                    $headers[] = 'Authorization: Bearer ' . $key;
            }
        }
        return array_values(array_unique($headers));
    }

    protected function curlOptions(array $options): array
    {
        return [
            'timeout'         => max(1, (int) ($options['timeout'] ?? $this->cfg('timeout', 60))),
            'connect_timeout' => max(1, (int) $this->cfg('connect_timeout', 10)),
            // 自签名证书的内网网关可配置 ssl_verify=false
            'ssl_verify'      => (bool) $this->cfg('ssl_verify', true),
        ];
    }

    /**
     * 发起请求并做错误归一化（不解析业务数据，流式接口可直接拿 body）
     */
    protected function send(string $path, array $payload, array $options = []): Response
    {
        if (!$this->isConfigured()) {
            throw AIException::notConfigured($this->getName());
        }

        try {
            $response = Requests::postJson(
                $this->endpoint($path),
                $payload,
                $this->headers(),
                $this->curlOptions($options)
            );
        } catch (CurlException $e) {
            $this->logError('transport', $e->getMessage());
            throw AIException::transport($this->getName(), $e->getMessage(), $e);
        }

        if (!$response->ok()) {
            $message = $this->errorMessage($response);
            $this->logError('http', $message, $response->status());
            throw AIException::http($this->getName(), $response->status(), $message);
        }
        return $response;
    }

    /** 非流式请求：返回解析后的关联数组 */
    protected function request(string $path, array $payload, array $options = []): array
    {
        $data = $this->send($path, $payload, $options)->json(true);
        if (!is_array($data)) {
            throw AIException::response($this->getName(), '响应不是有效的 JSON');
        }
        return $data;
    }

    /** 尽量读懂各厂商的错误体，且不把完整请求/密钥写进日志 */
    protected function errorMessage(Response $response): string
    {
        $data = $response->json(true);
        if (is_array($data)) {
            $error   = $data['error'] ?? null;
            $message = is_array($error) ? ($error['message'] ?? null) : $error;
            $message = $message ?: ($data['message'] ?? null);
            if (is_string($message) && $message !== '') {
                return substr($message, 0, 300);
            }
        }
        $body = trim($response->body());
        return $body !== '' ? substr($body, 0, 300) : '无响应内容';
    }

    protected function logError(string $type, string $message, int $status = 0): void
    {
        try {
            logger('ai')->error('[AI] ' . $type . ': ' . $message, [
                'provider' => $this->getName(),
                'status'   => $status,
            ]);
        } catch (\Throwable) {
            // 日志组件不可用时不影响主流程
        }
    }

    /** 除保留参数外的选项原样透传，方便支持 temperature / tools / response_format 等 */
    protected function passthrough(array $options): array
    {
        return array_diff_key($options, array_flip(self::RESERVED_OPTIONS));
    }

    protected function model(array $options): string
    {
        return (string) ($options['model'] ?? $this->cfg('model', ''));
    }
}
