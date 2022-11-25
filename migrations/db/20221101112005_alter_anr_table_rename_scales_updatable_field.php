<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class AlterAnrTableRenameScalesUpdatableField extends AbstractMigration
{
    public function change()
    {
        $this->table('anrs')
            ->renameColumn('cache_model_is_scales_updatable', "cache_model_are_scales_updatable")
            ->update();

        $this->execute('ALTER TABLE amvs MODIFY updated_at datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;');

        // Fix nullable recovery_codes of users.
        $this->execute('update users set recovery_codes = "' . serialize([]) . '" where recovery_codes IS NULL');

        $this->execute('ALTER TABLE clients DROP COLUMN model_id');
    }
}
