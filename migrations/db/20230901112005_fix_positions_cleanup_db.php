<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

class FixPositionsCleanupDb extends AbstractMigration
{
    public function change()
    {
        // Fix nullable recovery_codes of users.
        $this->execute('update users set recovery_codes = "' . serialize([]) . '" where recovery_codes IS NULL');
        $this->execute('ALTER TABLE `amvs` MODIFY updated_at datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;');

        /* Fix the amvs positions. */
        $amvsQuery = $this->query(
            'SELECT uuid, anr_id, asset_id, position FROM `amvs` ORDER BY anr_id, asset_id, position'
        );
        $previousAssetUuid = null;
        $previousAnrId = null;
        $expectedAmvPosition = 1;
        foreach ($amvsQuery->fetchAll() as $amvData) {
            if ($previousAssetUuid === null) {
                $previousAssetUuid = $amvData['asset_id'];
                $previousAnrId = $amvData['anr_id'];
            }
            if ($amvData['asset_id'] !== $previousAssetUuid
                || $previousAnrId !== $amvData['anr_id']
            ) {
                $expectedAmvPosition = 1;
            }

            if ($expectedAmvPosition !== $amvData['position']) {
                $this->execute(
                    sprintf(
                        'UPDATE amvs SET position = %d WHERE uuid = "%s"',
                        $expectedAmvPosition,
                        $amvData['uuid']
                    )
                );
            }

            $expectedAmvPosition++;
            $previousAssetUuid = $amvData['asset_id'];
            $previousAnrId = $amvData['anr_id'];
        }

        /* Fix the objects positions. */
        $objectsQuery = $this->query(
            'SELECT uuid, anr_id, object_category_id, position
            FROM objects
            ORDER BY anr_id, object_category_id, position'
        );
        $previousObjectCategoryId = null;
        $previousAnrId = null;
        $expectedObjectPosition = 1;
        foreach ($objectsQuery->fetchAll() as $objectData) {
            if ($previousObjectCategoryId === null) {
                $previousObjectCategoryId = $objectData['object_category_id'];
                $previousAnrId = $objectData['anr_id'];
            }
            if ($objectData['object_category_id'] !== $previousObjectCategoryId
                || $previousAnrId !== $objectData['anr_id']
            ) {
                $expectedObjectPosition = 1;
            }

            if ($expectedObjectPosition !== $objectData['position']) {
                $this->execute(
                    sprintf(
                        'UPDATE objects SET position = %d WHERE uuid = "%s"',
                        $expectedObjectPosition,
                        $objectData['uuid']
                    )
                );
            }

            $expectedObjectPosition++;
            $previousObjectCategoryId = $objectData['object_category_id'];
            $previousAnrId = $objectData['anr_id'];
        }

        /* Fix the objects compositions positions. */
        $objectsQuery = $this->query(
            'SELECT id, anr_id, father_id, position FROM objects_objects ORDER BY anr_id, father_id, position'
        );
        $previousParentObjectId = null;
        $previousAnrId = null;
        $expectedCompositionLinkPosition = 1;
        foreach ($objectsQuery->fetchAll() as $compositionObjectsData) {
            if ($previousParentObjectId === null) {
                $previousParentObjectId = $compositionObjectsData['father_id'];
                $previousAnrId = $compositionObjectsData['anr_id'];
            }
            if ($compositionObjectsData['father_id'] !== $previousParentObjectId
                || $previousAnrId !== $compositionObjectsData['anr_id']
            ) {
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
            $previousAnrId = $compositionObjectsData['anr_id'];
        }

        /* Fix the objects categories positions. */
        $objectsCategoriesQuery = $this->query(
            'SELECT id, anr_id, parent_id, position FROM objects_categories ORDER BY anr_id, parent_id, position'
        );
        $previousParentCategoryId = -1;
        $previousAnrId = null;
        $expectedCategoryPosition = 1;
        foreach ($objectsCategoriesQuery->fetchAll() as $objectCategoryData) {
            if ($previousParentCategoryId === -1) {
                $previousParentCategoryId = $objectCategoryData['parent_id'];
                $previousAnrId = $objectCategoryData['anr_id'];
            }
            if ($objectCategoryData['parent_id'] !== $previousParentCategoryId
                || $previousAnrId !== $objectCategoryData['anr_id']
            ) {
                $expectedCategoryPosition = 1;
            }

            if ($expectedCategoryPosition !== $objectCategoryData['position']) {
                $this->execute(
                    sprintf(
                        'UPDATE objects_categories SET position = %d WHERE id = %d',
                        $expectedCategoryPosition,
                        $objectCategoryData['id']
                    )
                );
            }

            $expectedCategoryPosition++;
            $previousParentCategoryId = $objectCategoryData['parent_id'];
            $previousAnrId = $objectCategoryData['anr_id'];
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

        /* Clean up unused columns. */
        $this->table('clients')->removeColumn('model_id')->update();
        $this->table('instances')->removeColumn('disponibility')->update();
        $this->table('objects')
            ->removeColumn('disponibility')
            ->removeColumn('token_import')
            ->removeColumn('original_name')
            ->update();
        $this->table('instances_consequences')->removeColumn('object_id')->removeColumn('locally_touched')->update();

        /* Fix possibly missing soacategory. */
        $measuresQuery = $this->query(
            'SELECT uuid, referential_uuid, anr_id FROM measures WHERE soacategory_id IS NULL;'
        );
        $soaCategoryIds = [];
        $soaCategoryTable = $this->table('soacategory');
        foreach ($measuresQuery->fetchAll() as $measureData) {
            $anrId = (int)$measureData['anr_id'];
            if (!isset($soaCategoryIds[$anrId])) {
                $soaCategoryTable->insert([
                    'label1' => 'catégorie manquante',
                    'label2' => 'missing category',
                    'label3' => 'fehlende Kategorie',
                    'label4' => 'ontbrekende categorie',
                    'anr_id' => $anrId,
                    'referential_uuid' => $measureData['referential_uuid'],
                ])->saveData();
                $soaCategoryIds[$anrId] = (int)$this->getAdapter()->getConnection()->lastInsertId();
            }

            $this->execute('UPDATE measures SET soacategory_id = ' . $soaCategoryIds[$anrId]
                . ' WHERE uuid = "' . $measureData['uuid'] . '" and anr_id = ' . $anrId);
        }

        /* Rename column of owner_id to risk_owner_id. */
        $this->table('instances_risks')->renameColumn('owner_id', 'risk_owner_id')->update();
        $this->table('instances_risks_op')
            ->renameColumn('owner_id', 'risk_owner_id')
            ->removeColumn('brut_r')
            ->removeColumn('brut_o')
            ->removeColumn('brut_l')
            ->removeColumn('brut_f')
            ->removeColumn('brut_p')
            ->removeColumn('net_r')
            ->removeColumn('net_o')
            ->removeColumn('net_l')
            ->removeColumn('net_f')
            ->removeColumn('net_p')
            ->removeColumn('targeted_r')
            ->removeColumn('targeted_o')
            ->removeColumn('targeted_l')
            ->removeColumn('targeted_f')
            ->removeColumn('targeted_p')
            ->update();

        /* The tables are not needed. */
        $this->table('anrs_objects')->drop()->update();
        $this->table('anrs_objects_categories')->drop()->update();

        /* Rename table `anr_metadatas_on_instances` to `anr_instance_metadata_fields`. */
        $this->table('anr_metadatas_on_instances')->rename('anr_instance_metadata_fields')->update();
        /* Rename table `instances_metadatas` to `instances_metadata`. */
        $this->table('instances_metadatas')->rename('instances_metadata')->update();

        /*
         * Migrations for to move the data from translations table and remove it.
         * 1. Create the fields to insert the data.
         * 2. Copy the data from translations
         * 3. Remove the translation keys' columns and the translations table.
         */
        $this->table('anr_instance_metadata_fields')
            ->changeColumn('anr_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('label', 'string', ['null' => false, 'limit' => 255, 'default' => ''])
            ->update();
        $this->table('instances_metadata')
            ->addColumn('comment', 'text', ['null' => true, 'limit' => MysqlAdapter::TEXT_REGULAR])
            ->update();
        $this->table('operational_risks_scales_types')
            ->addColumn('label', 'string', ['null' => false, 'limit' => 255, 'default' => ''])
            ->update();
        $this->table('operational_risks_scales_comments')
            ->addColumn('comment', 'text', ['null' => true, 'limit' => MysqlAdapter::TEXT_REGULAR])
            ->update();
        $this->table('soa_scale_comments')
            ->addColumn('comment', 'text', ['null' => true, 'limit' => MysqlAdapter::TEXT_REGULAR])
            ->update();

        $this->execute(
            'UPDATE anr_metadatas_on_instances aim
            INNER JOIN translations t ON aim.label_translation_key = t.translation_key
                AND t.anr_id = aim.anr_id
                AND t.type = "anr-metadatas-on-instances"
            SET aim.label = t.value;'
        );
        $this->execute(
            'UPDATE instances_metadata im
            INNER JOIN translations t ON im.comment_translation_key = t.translation_key
                AND t.anr_id = im.anr_id
                AND t.type = "instance-metadata"
            SET im.comment = t.value;'
        );
        $this->execute(
            'UPDATE operational_risks_scales_types orst
            INNER JOIN translations t ON orst.label_translation_key = t.translation_key
                AND t.anr_id = orst.anr_id
                AND t.type = "operational-risk-scale-type"
            SET orst.label = t.value;'
        );
        $this->execute(
            'UPDATE operational_risks_scales_comments orsc
            INNER JOIN translations t ON orsc.comment_translation_key = t.translation_key
                AND t.anr_id = orsc.anr_id
                AND t.type = "operational-risk-scale-comment"
            SET orsc.comment = t.value;'
        );
        $this->execute(
            'UPDATE soa_scale_comments ssc
            INNER JOIN translations t ON ssc.comment_translation_key = t.translation_key
                AND t.anr_id = ssc.anr_id
                AND t.type = "soa-scale-comment"
            SET ssc.comment = t.value;'
        );

        /* Add label, name, description, comment columns to replace all the language specific fields (1, 2, 3, 4). */
        $this->table('anrs')
            ->renameColumn('cache_model_is_scales_updatable', 'cache_model_are_scales_updatable')
            ->addColumn('label', 'string', ['null' => false, 'limit' => 255, 'default' => ''])
            ->addColumn('description', 'text', ['null' => true, 'limit' => MysqlAdapter::TEXT_REGULAR])
            ->addColumn('language_code', 'string', ['null' => false, 'limit' => 255, 'default' => 'fr'])
            ->update();
        $anrsQuery = $this->query('SELECT id, language FROM anrs');
        $languageCodes = [1 => 'fr', 2 => 'en', 3 => 'de', 4 => 'nl'];
        foreach ($anrsQuery->fetchAll() as $anrData) {
            $labelName = 'label' . $anrData['language'];
            $descriptionName = 'description' . $anrData['language'];
            $languageCode = $languageCodes[$anrData['language']];
            $this->execute('UPDATE anrs SET label = ' . $labelName . ', description = ' . $descriptionName
                . ', language_code = "' . $languageCode . '" WHERE id = ' . (int)$anrData['id']);
        }

        /* TODO: Should be added to the next release migration, to perform this release in a safe mode.
        $this->table('anr_instance_metadata_fields')->removeColumn('label_translation_key')->update();
        $this->table('instances_metadata')->removeColumn('comment_translation_key')->update();
        $this->table('operational_risks_scales_types')->removeColumn('label_translation_key')->update();
        $this->table('operational_risks_scales_comments')->removeColumn('comment_translation_key')->update();
        $this->table('soa_scale_comments')->removeColumn('comment_translation_key')->update();
        $this->table('anrs')
            ->removeColumn('label1')
            ->removeColumn('label2')
            ->removeColumn('label3')
            ->removeColumn('label4')
            ->removeColumn('description1')
            ->removeColumn('description2')
            ->removeColumn('description3')
            ->removeColumn('description4')
            ->update();

        $this->table('translations')->drop();
        */
    }
}
