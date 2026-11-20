<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS
 */

namespace zapcms\commands;

use zap\console\Command;
use zap\console\Input;
use zap\console\Output;
use zapcms\database\Migrator;

/**
 * 数据库迁移
 *
 * php console zapcms:Migrate        执行未执行的迁移
 * php console zapcms:Migrate -s     查看迁移状态
 * php console zapcms:Migrate -m     把当前所有迁移标记为已执行（不执行）
 */
class Migrate extends Command
{
    public function execute(Input $input, Output $output): int
    {
        $migrator = new Migrator();

        if (self::flag($input, ['s', 'status'])) {
            return $this->showStatus($migrator);
        }

        if (self::flag($input, ['m', 'mark'])) {
            $count = $migrator->markAllAsRun();
            $this->out->writeln("已标记 {$count} 个迁移为已执行");

            return self::SUCCESS;
        }

        if (count($migrator->pending()) === 0) {
            $this->out->writeln('没有需要执行的迁移');

            return self::SUCCESS;
        }

        $result = $migrator->run(function (string $line) {
            $this->out->writeln($line);
        });

        $this->out->writeln(sprintf(
            '执行完成：%d 个迁移（batch %d）',
            count($result['ran']),
            $result['batch']
        ));

        if ($result['error'] !== null) {
            $this->out->writeln("<error>迁移失败：{$result['error']}</error>");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * 输出迁移状态。
     */
    protected function showStatus(Migrator $migrator): int
    {
        $status = $migrator->status();

        $this->out->writeln('迁移目录：' . $migrator->path());
        $this->out->writeln(sprintf(
            '文件 %d 个，已执行 %d 个，待执行 %d 个',
            count($status['files']),
            count($status['executed']),
            count($status['pending'])
        ));

        foreach ($status['files'] as $name) {
            $mark = in_array($name, $status['executed'], true) ? '[x]' : '[ ]';
            $this->out->writeln("  {$mark} {$name}");
        }

        return self::SUCCESS;
    }

    /**
     * 判断短选项 / 长选项 / 位置参数是否存在。
     */
    protected static function flag(Input $input, array $names): bool
    {
        foreach ($names as $name) {
            if ($input->getOption($name) || $input->hasParam($name)) {
                return true;
            }
        }

        return false;
    }

    public function help(): int
    {
        $this->out->writeln('ZapCMS 数据库迁移');
        $this->out->writeln('Help:');
        $this->out->writeln("-s\t查看迁移状态");
        $this->out->writeln("-m\t把当前所有迁移标记为已执行（不执行，安装后使用）");

        return self::SUCCESS;
    }
}
