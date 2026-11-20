<?php

namespace zapcms\ai\Contracts;

use zapcms\ai\ChatResponse;

/**
 * AI 服务驱动契约
 *
 * 驱动只负责「把统一的请求结构翻译成厂商协议、再把响应翻译回统一结构」，
 * 不关心配置来自哪里、也不关心业务场景。新增厂商实现本接口后注册即可。
 */
interface Provider
{
    /** 配置名（用于日志与异常定位） */
    public function getName(): string;

    /** 是否具备可用的 base_url / api_key（本地模型可免 key） */
    public function isConfigured(): bool;

    /**
     * 对话补全
     *
     * @param array $messages 统一消息结构 [['role'=>'system|user|assistant|tool','content'=>string|array], ...]
     * @param array $options  模型参数（model/temperature/max_tokens/...），厂商自行取用
     */
    public function chat(array $messages, array $options = []): ChatResponse;

    /**
     * 流式输出：每产生一段文本回调一次
     *
     * @param callable $onDelta fn(string $delta, array $chunk): void
     */
    public function stream(array $messages, callable $onDelta, array $options = []): ChatResponse;

    /**
     * 文本向量化
     *
     * @return array<int, array<int, float>> 每个输入对应一个向量
     */
    public function embeddings(array|string $input, array $options = []): array;
}
