<?php

use Phinx\Migration\AbstractMigration;

class AddUniqueCodeConstraint extends AbstractMigration
{
    public function change()
    {
        $this->execute(
            'ALTER TABLE `assets` ADD CONSTRAINT `assets_anr_code_unq` UNIQUE (`anr_id`, `code`);
            ALTER TABLE `threats` ADD CONSTRAINT `threats_anr_code_unq` UNIQUE (`anr_id`, `code`);
            ALTER TABLE `vulnerabilities` ADD CONSTRAINT `vulnerabilities_anr_code_unq` UNIQUE (`anr_id`, `code`);'
        );
    }
}
