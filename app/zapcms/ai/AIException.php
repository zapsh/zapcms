<?php

namespace zapcms\ai;

/**
 * AI 模块统一异常
 *
 * 所有驱动抛出的异常都归一到此类，调用方只需 catch AIException。
 */
class AIException extends \RuntimeException
{
    public function __construct(
        string $message,
        protected string $provider = '',
        int $status = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public static function notConfigured(string $provider): self
    {
        return new self("AI 服务 [{$provider}] 未配置或缺少 api_key", $provider);
    }

    public static function unknownDriver(string $provider, string $driver): self
    {
        return new self("AI 服务 [{$provider}] 的驱动 [{$driver}] 不存在", $provider);
    }

    public static function transport(string $provider, string $message, ?\Throwable $previous = null): self
    {
        return new self("AI 服务 [{$provider}] 请求失败：{$message}", $provider, 0, $previous);
    }

    public static function http(string $provider, int $status, string $message): self
    {
        return new self("AI 服务 [{$provider}] 返回 HTTP {$status}：{$message}", $provider, $status);
    }

    public static function response(string $provider, string $message): self
    {
        return new self("AI 服务 [{$provider}] 响应异常：{$message}", $provider);
    }

    public static function unsupported(string $provider, string $feature): self
    {
        return new self("AI 服务 [{$provider}] 不支持 {$feature}", $provider);
    }
}
