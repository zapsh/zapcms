<?php

namespace zapcms\commands;

use zap\console\Command;
use zap\console\Input;
use zap\console\Output;
use zapcms\models\AdminLog;
use zapcms\services\queue\Job;
use zapcms\services\queue\Queue;

/**
 * 队列 Worker（命令行常驻进程）。
 *
 * 用法：
 *   php console zapcms:QueueWorker                       # 常驻处理 default 队列
 *   php console zapcms:QueueWorker --queue=emails        # 指定队列
 *   php console zapcms:QueueWorker --once                # 只处理一条后退出
 *   php console zapcms:QueueWorker --max-jobs=100        # 处理 100 条后退出
 *   php console zapcms:QueueWorker --stop-after=3600     # 运行 1 小时后退出
 *   php console zapcms:QueueWorker --tries=5 --sleep=5   # 重试/空轮询间隔
 *
 * 生产环境用 supervisor / systemd 守护；本命令负责取出任务、执行、重试与失败记录。
 */
class QueueWorker extends Command
{
    public function execute(Input $input, Output $output): int
    {
        $queue   = (string) $input->getOption('queue', 'default');
        $tries   = (int) $input->getOption('tries', 3);
        $timeout = (int) $input->getOption('timeout', 60);   // 单任务预留超时（秒），回收卡死任务
        $sleep   = (int) $input->getOption('sleep', 3);
        $maxJobs = (int) $input->getOption('max-jobs', 0);
        $once    = (bool) $input->getOption('once', false);
        $overall = (int) $input->getOption('stop-after', 0);  // worker 总运行秒数，0 = 常驻

        $driver = Queue::driver();
        $this->out->writeln(sprintf('队列 worker 启动：queue=%s driver=%s', $queue, get_class($driver)));

        $start = time();
        $done  = 0;

        do {
            if ($overall > 0 && (time() - $start) >= $overall) {
                break;
            }

            $job = $driver->pop($queue, $timeout);
            if ($job === null) {
                if ($once) {
                    break;
                }
                sleep($sleep);
                continue;
            }

            $command = $job['command'] ?? '';
            if (!class_exists($command) || !is_subclass_of($command, Job::class)) {
                $this->out->writeln("<error>未知任务类：{$command}，标记失败</error>");
                $driver->fail($job);
                continue;
            }

            /** @var Job $instance */
            $instance = new $command();
            $instance->data = (array) ($job['data'] ?? []);
            $maxTries      = (int) ($instance->tries ?? $tries);

            try {
                $instance->handle();
                $driver->delete($job);
                $done++;
                $this->out->writeln(sprintf('[OK] %s（已完成 %d）', $command, $done));
            } catch (\Throwable $e) {
                $attempts         = ((int) ($job['attempts'] ?? 0)) + 1;
                $job['attempts']  = $attempts;

                if ($attempts >= $maxTries) {
                    $driver->fail($job);
                    try {
                        AdminLog::error('queue', '队列任务失败：' . $command, $e->getMessage());
                    } catch (\Throwable $ie) {
                        // CLI 环境下日志写入不应中断 worker
                    }
                    $this->out->writeln("<error>[FAIL] {$command}：{$e->getMessage()}</error>");
                } else {
                    $delay = (int) ($instance->retryDelay ?? 5) * $attempts;
                    $driver->release($job, $delay);
                    $this->out->writeln("<warning>[RETRY] {$command}（第 {$attempts} 次，{$delay}s 后重试）</warning>");
                }
            }

            if ($maxJobs > 0 && $done >= $maxJobs) {
                break;
            }
        } while (!$once);

        $this->out->writeln(sprintf('队列 worker 退出：共处理 %d 个任务', $done));

        return self::SUCCESS;
    }

    public function help(): int
    {
        $this->out->writeln('ZapCMS 队列 Worker');
        $this->out->writeln('用法：php console zapcms:QueueWorker [--queue=default] [--tries=3] [--timeout=60] [--sleep=3] [--max-jobs=0] [--stop-after=0] [--once]');
        $this->out->writeln('说明：循环从队列取出任务并执行；生产环境用 supervisor 守护。');
        $this->out->writeln('  --timeout     单任务预留超时（秒），用于回收卡死任务（数据库驱动）');
        $this->out->writeln('  --stop-after  worker 总运行秒数（0=常驻）');
        $this->out->writeln('  --max-jobs    处理 N 条后退出（0=不限）');
        $this->out->writeln('  --once        只处理一条后退出');

        return self::SUCCESS;
    }
}
