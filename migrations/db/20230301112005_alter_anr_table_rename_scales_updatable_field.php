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


        /* Fix the objects compositions positions. */
        $objectsQuery = $this->query(
            'SELECT id, anr_id, father_id, position FROM objects_objects ORDER BY anr_id, father_id, position'
        );
        $previousParentObjectId = null;
        $expectedCompositionLinkPosition = 1;
        foreach ($objectsQuery->fetchAll() as $compositionObjectsData) {
            if ($previousParentObjectId === null) {
                $previousParentObjectId = $compositionObjectsData['father_id'];
            }
            if ($compositionObjectsData['father_id'] !== $previousParentObjectId) {
                $expectedCompositionLinkPosition = 1;
            }
            if ($expectedCompositionLinkPosition !== $compositionObjectsData['position']) {
                $this->execute(sprintf(
                    'UPDATE objects_objects SET position = %d WHERE id = %d',
                    $expectedCompositionLinkPosition,
                    $compositionObjectsData['id']
                ));
            }

            $expectedCompositionLinkPosition++;
            $previousParentObjectId = $compositionObjectsData['father_id'];
        }

        /* Fix instances positions to have them in a correct sequence (1, 2, 3, ...). */
        $instancesQuery = $this->query(
            'SELECT id, anr_id, parent_id, position FROM instances ORDER BY anr_id, parent_id, position'
        );
        $previousParentInstanceId = null;
        $expectedInstancePosition = 1;
        foreach ($instancesQuery->fetchAll() as $instanceData) {
            if ($previousParentInstanceId === null) {
                $previousParentInstanceId = (int)$instanceData['parent_id'];
            }
            if ((int)$instanceData['parent_id'] !== $previousParentInstanceId) {
                $expectedInstancePosition = 1;
            }
            if ($expectedInstancePosition !== $instanceData['position']) {
                $this->execute(sprintf(
                    'UPDATE instances SET position = %d WHERE id = %d',
                    $expectedInstancePosition,
                    $instanceData['id']
                ));
            }

            $expectedInstancePosition++;
            $previousParentInstanceId = $instanceData['parent_id'];
        }

        $this->execute('ALTER TABLE clients DROP COLUMN model_id');

        $this->table('instances')->removeColumn('disponibility')->update();
        $this->table('objects')->removeColumn('disponibility')->update();

        $this->table('instances_consequences')->removeColumn('object_id')->removeColumn('locally_touched')->update();

        /* The position of the category will be used based on object_category table (root_id = null). */
        $this->table('anrs_objects_categories')
            ->removeColumn('position')
            ->removeColumn('creator')
            ->removeColumn('created_at')
            ->removeColumn('updater')
            ->removeColumn('updated_at')
            ->update();
    }
}
