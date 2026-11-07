<?php

namespace zapcms\ai;

/**
 * 统一的对话响应对象
 *
 * 屏蔽各厂商响应差异；业务代码只需用 text() 取文本。
 */
final class ChatResponse
{
    public function __construct(
        public readonly string $content = '',
        public readonly string $model = '',
        public readonly array $usage = [],
        public readonly ?string $finishReason = null,
        public readonly array $raw = [],
    ) {
    }

    /** 回复文本 */
    public function text(): string
    {
        return $this->content;
    }

    public function __toString(): string
    {
        return $this->content;
    }

    /** 消耗的总 token（厂商未返回时为 0） */
    public function tokens(): int
    {
        return (int) ($this->usage['total_tokens'] ?? 0);
    }

    public function isEmpty(): bool
    {
        return trim($this->content) === '';
    }

    /** 原始响应，便于取 reasoning_content / tool_calls 等厂商扩展字段 */
    public function raw(): array
    {
        return $this->raw;
    }

    public function toArray(): array
    {
        return [
            'content'       => $this->content,
            'model'         => $this->model,
            'usage'         => $this->usage,
            'finish_reason' => $this->finishReason,
        ];
    }
}
