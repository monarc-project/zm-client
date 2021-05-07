<?php

use Phinx\Migration\AbstractMigration;

class RemoveSettingsTable extends AbstractMigration
{
    public function change()
    {
        $this->execute(
            'DROP TABLE IF EXISTS `settings`'
        );
    }
}
