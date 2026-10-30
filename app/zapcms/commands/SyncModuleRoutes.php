<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS
 */

namespace zapcms\commands;

use zap\console\Command;
use zap\console\Input;
use zap\console\Output;
use zapcms\support\ZapPackageManager;

/**
 * 手动重新生成 config/module.php（模块路由配置）。
 *
 * 正常情况下该文件会在模块安装 / 卸载 / 启用 / 禁用 / 更新时自动生成，
 * 此命令用于手动触发一次重建（例如手工新增了模块目录、或配置文件被误改后恢复）。
 *
 * Usage:
 *   php console zapcms:SyncModuleRoutes
 */
class SyncModuleRoutes extends Command
{
    public function execute(Input $input, Output $output): int
    {
        $pm       = new ZapPackageManager();
        $prefixes = $pm->syncModuleRoutes();

        $this->out->writeln('已重新生成 config/module.php');
        $this->out->writeln('当前模块路由前缀映射：');

        if (empty($prefixes)) {
            $this->out->writeln('  （无已启用且声明 route 的模块）');
        }
        foreach ($prefixes as $prefix => $module) {
            $this->out->writeln("  /{$prefix}  =>  mods/{$module}");
        }

        return self::SUCCESS;
    }

    public function help(): int
    {
        $this->out->writeln('Regenerate config/module.php from enabled modules\' mod.json "route" declarations.');
        $this->out->writeln('');
        $this->out->writeln('Usage:');
        $this->out->writeln('  php console zapcms:SyncModuleRoutes');
        return self::SUCCESS;
    }
}
