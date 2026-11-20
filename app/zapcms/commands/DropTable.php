<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS
 */

namespace zapcms\commands;

use zap\db\Schema;
use zap\console\Command;
use zap\console\Output; 
use zap\console\Input;

/**
 * php console zap:DropTable -c sqlite
 */
class DropTable extends Command
{
    function execute(Input $input, Output $output): int
    {
        Schema::setOutput($output);
        $connection = $this->input->getParam('c');
        Schema::connection($connection);
        $this->out->writeln('Dropping table...');
        $this->out->writeln(Schema::dropIfExists('user') ? 'Done.' : 'Table not found.');
        return self::SUCCESS;
    }

    public function help(): int
    {
        $this->out->writeln("Help:");
        $this->out->writeln("-c \tdatabase.php connection name, If not set, defaults to database.default");
        return self::SUCCESS;
    }

}