<?php

namespace zapcms\services\queue;

/**
 * 队列门面（统一入口）。
 *
 * 驱动由 option('queue.driver') 切换（默认 database，可设 redis）。
 * 业务侧只需：Queue::push(new XxxJob($data)) 或 Queue::later(60, new XxxJob($data))。
 */
class Queue
{
    protected static ?QueueDriverInterface $driver = null;

    /**
     * 获取当前驱动（按配置懒加载并缓存）。
     */
    public static function driver(): QueueDriverInterface
    {
        if (self::$driver === null) {
            $name = (string) option('queue.driver', 'database');
            self::$driver = $name === 'redis'
                ? new RedisQueue()
                : new DatabaseQueue();
        }

        return self::$driver;
    }

    /**
     * 测试 / 注入用：显式设置驱动。
     */
    public static function setDriver(QueueDriverInterface $driver): void
    {
        self::$driver = $driver;
    }

    /**
     * 立即入队。
     *
     * @param Job|array $job Job 实例，或 ['command' => 类名, 'data' => 数组]
     * @param string    $queue
     * @return mixed
     */
    public static function push($job, string $queue = 'default')
    {
        return self::driver()->push(self::serialize($job), 0, $queue);
    }

    /**
     * 延迟入队。
     *
     * @param int       $delay 延迟秒数
     * @param Job|array $job
     * @param string    $queue
     * @return mixed
     */
    public static function later(int $delay, $job, string $queue = 'default')
    {
        return self::driver()->push(self::serialize($job), $delay, $queue);
    }

    /**
     * 序列化任务为 payload。
     */
    protected static function serialize($job): array
    {
        if ($job instanceof Job) {
            return ['command' => get_class($job), 'data' => $job->data];
        }

        if (is_array($job) && isset($job['command'])) {
            $job['data'] = $job['data'] ?? [];
            return $job;
        }

        throw new \InvalidArgumentException('Queue::push 需要 Job 实例或含 command 的数组');
    }
}
