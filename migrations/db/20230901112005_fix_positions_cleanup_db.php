<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
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
        /* Correct MeasuresMeasures table structure. */
        $this->table('measures_measures')
            ->addColumn('id', 'integer', ['signed' => false, 'after' => MysqlAdapter::FIRST])
            ->renameColumn('father_id', 'master_measure_id')
            ->renameColumn('child_id', 'linked_measure_id')
            ->dropForeignKey(['master_measure_id', 'linked_measure_id', 'anr_id'])
            ->removeColumn('creator')
            ->removeColumn('created_at')
            ->removeColumn('updater')
            ->removeColumn('updated_at')
            ->update();
        $this->execute('SET @a = 0; UPDATE measures_measures SET id = @a := @a + 1 ORDER BY anr_id;');
        $this->table('measures_measures')
            ->changePrimaryKey(['id'])
            ->addIndex(['master_measure_id', 'linked_measure_id', 'anr_id'], ['unique' => true])
            ->update();
        $this->table('measures_measures')
            ->changeColumn('id', 'integer', ['identity' => true, 'signed' => false])
            ->update();
        /* Remove unlinked measures links to measures. */
        $this->execute('DELETE FROM measures_measures WHERE anr_id NOT IN (SELECT id FROM anrs);');
        $voidMeasuresQuery = $this->query('
            SELECT id FROM measures_measures WHERE CONCAT(master_measure_id, anr_id) NOT IN
            (SELECT CONCAT(uuid, anr_id) FROM measures) OR CONCAT(linked_measure_id, anr_id)
            NOT IN (SELECT CONCAT(uuid, anr_id) FROM measures)
        ');
        $voidMeasuresIds = [];
        foreach ($voidMeasuresQuery->fetchAll() as $voidMeasureData) {
            $voidMeasuresIds[] = $voidMeasureData['id'];
        }
        $this->execute('DELETE FROM measures_measures WHERE id IN (' . implode(',', $voidMeasuresIds) . ');');
        $this->table('measures_measures')
            ->addForeignKey(['anr_id'], 'anrs', ['id'], ['delete' => 'CASCADE'])
            ->addForeignKey(
                ['master_measure_id', 'anr_id'],
                'measures',
                ['uuid', 'anr_id'],
                ['delete' => 'CASCADE', 'update' => 'RESTRICT']
            )->addForeignKey(
                ['linked_measure_id', 'anr_id'],
                'measures',
                ['uuid', 'anr_id'],
                ['delete' => 'CASCADE', 'update' => 'RESTRICT']
            )->update();
        $this->table('measures')->addForeignKey(['anr_id'], 'anrs', ['id'], ['delete' => 'CASCADE'])->update();

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
            'UPDATE anr_instance_metadata_fields aim
            INNER JOIN translations t ON aim.label_translation_key = t.translation_key
                AND t.anr_id = aim.anr_id
                AND t.type = "anr-metadatas-on-instances"
            SET aim.label = t.value;'
        );
        $this->execute(
            'UPDATE instances_metadata im
            INNER JOIN anr_instance_metadata_fields aimf ON im.metadata_id = aimf.id
            INNER JOIN translations t ON im.comment_translation_key = t.translation_key
                AND t.anr_id = aimf.anr_id
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
            /* Make Anr name (label) unique. */
            //->addIndex(['label'], ['unique' => true])
            ->update();
        $anrsQuery = $this->query('SELECT id, language, label1, label2, label3, label4,
            description1, description2, description3, description4, created_at FROM anrs'
        );
        $languageCodes = [1 => 'fr', 2 => 'en', 3 => 'de', 4 => 'nl'];
        $uniqueLabels = [];
        foreach ($anrsQuery->fetchAll() as $anrData) {
            $labelName = 'label' . $anrData['language'];
            if (isset($uniqueLabels[$anrData[$labelName]])) {
                $uniqueLabels[$anrData[$labelName]] = $anrData[$labelName] . ' ['
                    . (!empty($anrData['created_at']) ? $anrData['created_at'] : date('Y-m-d H:i:s')) . ']';
            } else {
                $uniqueLabels[$anrData[$labelName]] = $anrData[$labelName];
            }
            $descriptionName = 'description' . $anrData['language'];
            $languageCode = $languageCodes[$anrData['language']];
            $this->execute('UPDATE anrs SET label = "' . $uniqueLabels[$anrData[$labelName]] .
                '", description = "' . $anrData[$descriptionName] .
                '", language_code = "' . $languageCode . '" WHERE id = ' . (int)$anrData['id']);
        }

        /* Replace in recommandations_sets label1,2,3,4 by a single label field. */
        $this->table('recommandations_sets')
            ->addColumn('label', 'string', ['null' => false, 'limit' => 255, 'default' => ''])
            ->update();
        $recSetsQuery = $this->query('SELECT rs.uuid, rs.anr_id, a.language, rs.label1, rs.label2, rs.label3, rs.label4
            FROM recommandations_sets rs INNER JOIN anrs a ON a.id = rs.anr_id'
        );
        foreach ($recSetsQuery->fetchAll() as $recSetData) {
            $labelName = 'label' . $recSetData['language'];
            $this->execute('UPDATE recommandations_sets SET label = "' . $recSetData[$labelName] . '"' .
                ' WHERE uuid = "' . $recSetData['uuid'] . '" AND anr_id = ' . (int)$recSetData['anr_id']);
        }
        /* Make anr_id and label unique. */
        $this->table('recommandations_sets')->addIndex(['anr_id', 'label'], ['unique' => true]);

        $this->table('recommandations')
            ->removeColumn('token_import')
            ->removeColumn('original_code')
            ->update();

        $this->table('deliveries')
            ->renameColumn('resp_smile', 'responsible_manager')
            ->update();

        $this->table('scales_impact_types')
            ->removeColumn('position')
            ->update();

        $this->table('objects_categories')
            ->changeColumn('label1', 'string', ['null' => false, 'default' => '', 'limit' => 2048])
            ->changeColumn('label2', 'string', ['null' => false, 'default' => '', 'limit' => 2048])
            ->changeColumn('label3', 'string', ['null' => false, 'default' => '', 'limit' => 2048])
            ->changeColumn('label4', 'string', ['null' => false, 'default' => '', 'limit' => 2048])
            ->update();

        /* The unique relation is not correct as it should be possible to instantiate the same operational risk. */
        $this->table('operational_instance_risks_scales')
             ->removeIndex(['anr_id', 'instance_risk_op_id', 'operational_risk_scale_type_id'])
             ->addIndex(['anr_id', 'instance_risk_op_id', 'operational_risk_scale_type_id'], ['unique' => false])
             ->update();

        /* Note: Temporary change fields types to avoid setting values from the code. Later will be dropped. */
        $this->table('operational_risks_scales_types')
            ->changeColumn('label_translation_key', 'string', ['null' => false, 'default' => '', 'limit' => 255])
            ->update();
        $this->table('operational_risks_scales_comments')
            ->changeColumn('comment_translation_key', 'string', ['null' => false, 'default' => '', 'limit' => 255])
            ->update();

        /* Cleanup the table. */
        $this->table('rolf_risks_tags')
            ->removeColumn('creator')
            ->removeColumn('created_at')
            ->removeColumn('updater')
            ->removeColumn('updated_at')
            ->update();

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
