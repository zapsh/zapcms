<?php

namespace zapcms\services\message\Support;

/**
 * 发送结果统一结构。
 */
class Result
{
    public bool $success;
    public string $provider;
    public string $message;
    public ?array $raw;

    private function __construct(bool $success, string $provider, string $message, ?array $raw)
    {
        $this->success  = $success;
        $this->provider = $provider;
        $this->message  = $message;
        $this->raw      = $raw;
    }

    public static function ok(string $provider, string $message = '', ?array $raw = null): self
    {
        return new self(true, $provider, $message, $raw);
    }

    public static function fail(string $provider, string $message = '', ?array $raw = null): self
    {
        return new self(false, $provider, $message, $raw);
    }
}
