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
        $this->out->writeln("ZapCMS CreateSchema Command");
        $this->out->writeln("Help:");
        $this->out->writeln("-b\tBase data installation");
        $this->out->writeln("-d\tDemo data installation");
        $this->out->writeln("-c\tConnection name, default: my_test");
        $this->out->writeln("-v\tVerbose output");
        return self::SUCCESS;
    }

}