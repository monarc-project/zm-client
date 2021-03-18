<?php

use Phinx\Migration\AbstractMigration;

class AddIsStatsCollectedField extends AbstractMigration
{
    public function change()
    {
        $this->execute(
            'ALTER TABLE `anrs` ADD `is_stats_collected` TINYINT(1) NOT NULL default 1;'
        );
    }
}
