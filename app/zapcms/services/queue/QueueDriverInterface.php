<?php

namespace zapcms\services\queue;

/**
 * 队列驱动接口（数据库 / Redis 等可切换实现）。
 */
interface QueueDriverInterface
{
    /**
     * 入队。
     *
     * @param array  $payload ['command' => 类名, 'data' => 数组]
     * @param int    $delay   延迟秒数（0 表示立即可用）
     * @param string $queue   队列名
     * @return mixed 任务标识（数据库为自增 id；Redis 可忽略）
     */
    public function push(array $payload, int $delay, string $queue);

    /**
     * 取出并预留一个可执行的任务（数据库驱动会用 reserved_at 占位，避免多 worker 重复领取）。
     *
     * @param string $queue   队列名
     * @param int    $timeout 单任务预留超时（秒）：超过该时长仍 reserved 的任务可被重新领取
     * @return array|null ['id' => mixed, 'command' => string, 'data' => array, 'attempts' => int]
     */
    public function pop(string $queue, int $timeout): ?array;

    /**
     * 任务成功完成后移除。
     */
    public function delete(array $job): void;

    /**
     * 任务执行失败后放回队列（延迟重试）。
     */
    public function release(array $job, int $delay): void;

    /**
     * 任务达到最大重试次数后标记失败。
     */
    public function fail(array $job): void;
}
