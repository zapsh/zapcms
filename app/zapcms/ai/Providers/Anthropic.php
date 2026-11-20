<?php

namespace zapcms\ai\Providers;

use zapcms\ai\ChatResponse;

/**
 * Anthropic Claude 驱动（/v1/messages）
 *
 * 与 OpenAI 协议不同：system 是顶层字段、鉴权用 x-api-key、
 * 且必须传 max_tokens。放在这里用于演示抽象层可接非 OpenAI 兼容厂商。
 * 该驱动不支持 embeddings；stream() 使用基类的一次性兼容实现。
 */
class Anthropic extends AbstractProvider
{
    protected const MESSAGES_PATH = 'messages';

    public function chat(array $messages, array $options = []): ChatResponse
    {
        [$system, $dialog] = $this->splitSystem($messages);

        $payload = array_merge($this->passthrough($options), [
            'model'      => $this->model($options),
            'max_tokens' => (int) ($options['max_tokens'] ?? $this->cfg('max_tokens', 1024)),
            'messages'   => $dialog,
        ]);
        if ($system !== '') {
            $payload['system'] = $system;
        }

        $data = $this->request((string) $this->cfg('messages_path', self::MESSAGES_PATH), $payload, $options);

        $text = '';
        foreach ((array) ($data['content'] ?? []) as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                $text .= (string) ($block['text'] ?? '');
            }
        }

        $usage = (array) ($data['usage'] ?? []);
        $in    = (int) ($usage['input_tokens'] ?? 0);
        $out   = (int) ($usage['output_tokens'] ?? 0);
        $usage += [
            'prompt_tokens'     => $in,
            'completion_tokens' => $out,
            'total_tokens'      => $in + $out,
        ];

        return new ChatResponse(
            $text,
            (string) ($data['model'] ?? $this->model($options)),
            $usage,
            isset($data['stop_reason']) ? (string) $data['stop_reason'] : null,
            $data
        );
    }

    /** @return array{0:string,1:array} [system 文本, 对话消息] */
    protected function splitSystem(array $messages): array
    {
        $system = [];
        $dialog = [];
        foreach ($messages as $message) {
            if (($message['role'] ?? '') === 'system') {
                $content  = $message['content'] ?? '';
                $system[] = is_string($content) ? $content : (string) json_encode($content, JSON_UNESCAPED_UNICODE);
            } else {
                $dialog[] = $message;
            }
        }
        if ($dialog === []) {
            $dialog[] = ['role' => 'user', 'content' => ''];
        }
        return [implode("\n", array_filter($system, 'strlen')), $dialog];
    }
}
