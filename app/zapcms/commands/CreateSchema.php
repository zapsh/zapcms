<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS
 */

namespace zapcms\commands;

use zapcms\support\CreateBaseData;
use zapcms\support\CreateTables;
use zap\db\Schema;
use zap\console\Command;
use zap\console\Input;
use zap\console\Output;

/**
 * php console zap:CreateSchema -c sqlite
 */
class CreateSchema extends Command
{
    function execute(Input $input, Output $output): int
    {
        $connection = $this->input->getParam('c','my_test');
        Schema::connection($connection);
        Schema::setOutput($output);
        // CreateTables 没有构造函数，原先传入的 verbose 参数会被静默丢弃
        $schema = new CreateTables();
        $schema->createSchema();

        if($this->input->getParam('b')){
            CreateBaseData::install();
        }

        if($this->input->getParam('d')){
            $schema->installDemoData();
        }

        return self::SUCCESS;
    }

    public function help() : int
    {
        $this->out->writeln("ZapCMS 创建表结构");
        $this->out->writeln("Help:");
        $this->out->writeln("-b\t基础数据");
        $this->out->writeln("-v\t显示创建脚本");
        return self::SUCCESS;
    }

}