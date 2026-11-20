<?php

namespace zapcms\services\queue;

/**
 * 队列任务基类。
 *
 * 任务即一个实现 handle() 的类；入队时只序列化类名与 data 数组，
 * worker 取出后以 new $class() 实例化并调用 handle()。
 *
 * 子类可覆盖：
 *  - $tries      最大尝试次数（默认 3）
 *  - $retryDelay 失败后重试延迟基数（秒），实际延迟 = retryDelay × 已尝试次数
 */
abstract class Job
{
    /** @var array 任务数据（入队时写入，worker 取出后回填） */
    public array $data = [];

    /** 最大尝试次数 */
    public int $tries = 3;

    /** 失败后重试延迟基数（秒） */
    public int $retryDelay = 5;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * 执行任务。
     *
     * @return void
     * @throws \Throwable 任意异常都会触发重试 / 标记失败
     */
    abstract public function handle(): void;
}
