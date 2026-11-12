<?php

namespace zapcms\services\queue;

/**
 * Redis 队列驱动。
 *
 * 任务存于有序集合 key = "zapcms:queue:{queue}"，分值 = 可用时间戳（支持延迟），
 * 成员 = JSON 化的 payload。pop 时取出分数 <= now 的首条并移除，失败再重新入队。
 *
 * 连接参数（均可通过 option 配置，缺省指向本地 Redis）：
 *  - queue.redis_client   redis | predis
 *  - queue.redis_host     默认 127.0.0.1
 *  - queue.redis_port     默认 6379
 *  - queue.redis_password
 *  - queue.redis_database 默认 0
 *
 * 说明：pop 取出即移除，不做 reserved 占位（与框架 RedisCache 连接风格一致）；
 *      多 worker 下偶发重复领取风险极低，生产建议配合 tries 重试兜底。
 */
class RedisQueue implements QueueDriverInterface
{
    /** @var \Redis|\Predis\Client|null */
    protected $redis;

    protected string $prefix = 'zapcms:queue:';

    public function __construct()
    {
        $options = [
            'client'   => (string) option('queue.redis_client', 'redis'),
            'params'   => [(string) option('queue.redis_host', '127.0.0.1'), (int) option('queue.redis_port', 6379)],
            'password' => (string) option('queue.redis_password', ''),
            'database' => (int) option('queue.redis_database', 0),
        ];

        $redisClass = $options['client'] === 'predis' ? '\Predis\Client' : '\Redis';
        if ($options['client'] === 'predis') {
            $this->redis = (new \ReflectionClass($redisClass))->newInstanceArgs([$options['params']]);
        } else {
            $this->redis = new $redisClass();
            call_user_func_array([$this->redis, 'connect'], $options['params']);
        }

        if ($options['password'] !== '') {
            $this->redis->auth($options['password']);
        }
        if ($options['database'] > 0) {
            $this->redis->select($options['database']);
        }
    }

    protected function key(string $queue): string
    {
        return $this->prefix . $queue;
    }

    public function push(array $payload, int $delay, string $queue)
    {
        $this->redis->zAdd($this->key($queue), time() + $delay, json_encode($payload, JSON_UNESCAPED_UNICODE));

        return 1;
    }

    public function pop(string $queue, int $timeout): ?array
    {
        $now = time();
        $key = $this->key($queue);

        $members = $this->redis->zRangeByScore($key, 0, $now, ['limit' => [0, 1]]);
        if (empty($members)) {
            return null;
        }

        $this->redis->zRem($key, $members[0]);

        $payload = json_decode($members[0], true) ?: [];

        return [
            'id'       => null,
            'command'  => $payload['command'] ?? '',
            'data'     => $payload['data'] ?? [],
            'attempts' => (int) ($payload['attempts'] ?? 0),
        ];
    }

    public function delete(array $job): void
    {
        // Redis 驱动 pop 时已移除，无需操作
    }

    public function release(array $job, int $delay): void
    {
        $payload = [
            'command'  => $job['command'] ?? '',
            'data'     => $job['data'] ?? [],
            'attempts' => ((int) ($job['attempts'] ?? 0)) + 1,
        ];
        $this->push($payload, $delay, $job['queue'] ?? 'default');
    }

    public function fail(array $job): void
    {
        $this->redis->rPush($this->prefix . 'failed', json_encode([
            'command'   => $job['command'] ?? '',
            'data'      => $job['data'] ?? [],
            'failed_at' => time(),
        ], JSON_UNESCAPED_UNICODE));
    }
}
