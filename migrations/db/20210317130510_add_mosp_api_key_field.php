<?php

use Phinx\Migration\AbstractMigration;

class AddMospApiKeyField extends AbstractMigration
{
    public function change()
    {
        $this->execute(
            'ALTER TABLE `users` ADD `mosp_api_key` VARCHAR(255) DEFAULT NULL;'
        );
    }
}
