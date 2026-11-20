<?php

namespace zapcms\services\queue;

use zap\DB;

/**
 * 数据库队列驱动（默认）。
 *
 * 任务存于 jobs 表；pop 时用 reserved_at 占位（配合 timeout 回收卡死任务），
 * 避免多 worker 重复领取。适合无 Redis 环境，零额外依赖。
 */
class DatabaseQueue implements QueueDriverInterface
{
    protected string $table = 'jobs';

    public function push(array $payload, int $delay, string $queue)
    {
        $now = time();

        return (int) DB::table($this->table)->insertGetId([
            'queue'        => $queue,
            'payload'      => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'attempts'     => 0,
            'reserved_at'  => 0,
            'available_at' => $now + $delay,
            'status'       => 'pending',
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);
    }

    public function pop(string $queue, int $timeout): ?array
    {
        $now = time();

        // 优先取 pending；其次回收超时仍 reserved 的卡死任务
        $candidate = DB::table($this->table)
            ->where('queue', $queue)
            ->where('available_at', '<=', $now)
            ->where('status', 'pending')
            ->orderBy('id', 'ASC')
            ->limit(1)
            ->fetch();

        if (empty($candidate)) {
            $candidate = DB::table($this->table)
                ->where('queue', $queue)
                ->where('available_at', '<=', $now)
                ->where('status', 'reserved')
                ->where('reserved_at', '<', $now - max(1, $timeout))
                ->orderBy('id', 'ASC')
                ->limit(1)
                ->fetch();
        }

        if (empty($candidate)) {
            return null;
        }

        // 仅在状态未变时占位，防止与并发 worker 重复领取
        $affected = DB::table($this->table)
            ->where('id', $candidate['id'])
            ->where('status', $candidate['status'])
            ->update([
                'status'      => 'reserved',
                'reserved_at' => $now,
                'updated_at'  => $now,
            ]);

        if ($affected === 0) {
            return null;
        }

        $payload = json_decode((string) $candidate['payload'], true) ?: [];

        return [
            'id'       => (int) $candidate['id'],
            'command'  => $payload['command'] ?? '',
            'data'     => $payload['data'] ?? [],
            'attempts' => (int) $candidate['attempts'],
        ];
    }

    public function delete(array $job): void
    {
        if (isset($job['id'])) {
            DB::table($this->table)->where('id', $job['id'])->delete();
        }
    }

    public function release(array $job, int $delay): void
    {
        if (!isset($job['id'])) {
            return;
        }
        DB::table($this->table)->where('id', $job['id'])->update([
            'status'       => 'pending',
            'reserved_at'  => 0,
            'attempts'     => ((int) ($job['attempts'] ?? 0)) + 1,
            'available_at' => time() + $delay,
            'updated_at'   => time(),
        ]);
    }

    public function fail(array $job): void
    {
        if (!isset($job['id'])) {
            return;
        }
        DB::table($this->table)->where('id', $job['id'])->update([
            'status'      => 'failed',
            'reserved_at' => 0,
            'updated_at'  => time(),
        ]);
    }
}
