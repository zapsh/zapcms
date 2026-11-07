<?php

namespace zapcms\ai\Providers;

use zapcms\ai\AIException;
use zapcms\ai\ChatResponse;

/**
 * OpenAI 兼容协议驱动（/chat/completions、/embeddings）
 *
 * 适用于 OpenAI、DeepSeek、Moonshot、通义千问兼容模式、Ollama、vLLM、
 * 以及各类自建网关。只要各家差异在 base_url / model / 鉴权头上即可复用。
 *
 * 可通过配置覆盖路径：chat_path、embeddings_path、embeddings_model。
 *
 * 注意：框架 Requests 会先收完整响应再返回，因此 stream() 是「收完后逐块
 * 回放 delta」的兼容实现；需要真正实时逐字输出时需另接 curl write callback。
 */
class OpenAICompatible extends AbstractProvider
{
    protected const CHAT_PATH       = 'chat/completions';
    protected const EMBEDDINGS_PATH = 'embeddings';

    public function chat(array $messages, array $options = []): ChatResponse
    {
        $payload = array_merge($this->passthrough($options), [
            'model'    => $this->model($options),
            'messages' => $messages,
        ]);

        $data    = $this->request($this->chatPath(), $payload, $options);
        $choice  = $data['choices'][0] ?? [];
        $message = is_array($choice) ? ($choice['message'] ?? []) : [];
        $content = is_array($message) ? ($message['content'] ?? '') : '';

        return new ChatResponse(
            $this->stringifyContent($content),
            (string) ($data['model'] ?? $this->model($options)),
            (array) ($data['usage'] ?? []),
            is_array($choice) && isset($choice['finish_reason']) ? (string) $choice['finish_reason'] : null,
            $data
        );
    }

    public function stream(array $messages, callable $onDelta, array $options = []): ChatResponse
    {
        $payload = array_merge($this->passthrough($options), [
            'model'    => $this->model($options),
            'messages' => $messages,
            'stream'   => true,
        ]);

        $response     = $this->send($this->chatPath(), $payload, $options);
        $text         = '';
        $finishReason = null;
        $last         = [];

        foreach (preg_split('/\R/', $response->body()) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || stripos($line, 'data:') !== 0) {
                continue;
            }
            $chunk = trim(substr($line, 5));
            if ($chunk === '' || $chunk === '[DONE]') {
                continue;
            }
            $json = json_decode($chunk, true);
            if (!is_array($json)) {
                continue;
            }
            $last   = $json;
            $choice = $json['choices'][0] ?? [];
            if (is_array($choice) && !empty($choice['finish_reason'])) {
                $finishReason = (string) $choice['finish_reason'];
            }
            $delta = is_array($choice) ? ($choice['delta']['content'] ?? '') : '';
            $delta = $this->stringifyContent($delta);
            if ($delta !== '') {
                $text .= $delta;
                $onDelta($delta, $json);
            }
        }

        if ($text === '' && $finishReason === null) {
            throw AIException::response($this->getName(), '流式响应没有内容，该模型可能不支持 stream');
        }

        return new ChatResponse(
            $text,
            (string) ($last['model'] ?? $this->model($options)),
            [],
            $finishReason,
            ['stream' => true, 'last' => $last]
        );
    }

    public function embeddings(array|string $input, array $options = []): array
    {
        $payload = array_merge($this->passthrough($options), [
            'model' => (string) ($options['model'] ?? $this->cfg('embeddings_model', $this->cfg('model', ''))),
            'input' => $input,
        ]);

        $data = $this->request($this->embeddingsPath(), $payload, $options);
        $rows = $data['data'] ?? null;
        if (!is_array($rows)) {
            throw AIException::response($this->getName(), 'embeddings 响应缺少 data 字段');
        }

        return array_values(array_map(
            fn ($row) => array_values((array) (is_array($row) ? ($row['embedding'] ?? []) : [])),
            $rows
        ));
    }

    /** content 可能是字符串，也可能是多模态分段数组 */
    protected function stringifyContent(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
        }
        if (!is_array($content)) {
            return '';
        }
        $text = '';
        foreach ($content as $part) {
            if (is_string($part)) {
                $text .= $part;
            } elseif (is_array($part)) {
                $text .= (string) ($part['text'] ?? '');
            }
        }
        return $text;
    }

    protected function chatPath(): string
    {
        return (string) $this->cfg('chat_path', self::CHAT_PATH);
    }

    protected function embeddingsPath(): string
    {
        return (string) $this->cfg('embeddings_path', self::EMBEDDINGS_PATH);
    }
}
