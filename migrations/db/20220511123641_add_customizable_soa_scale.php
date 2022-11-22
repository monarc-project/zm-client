<?php

use Phinx\Migration\AbstractMigration;
use Ramsey\Uuid\Uuid;
use Monarc\Core\Model\Entity\Translation;

class AddCustomizableSoaScale extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    addCustomColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Any other destructive changes will result in an error when trying to
     * rollback the migration.
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        //create the comments, one comment by scale_index
        $table = $this->table('soa_scale_comments');
        $table
            ->addColumn('anr_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('scale_index', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('colour', 'string', array('null' => true, 'limit' => 7))
            ->addColumn('comment_translation_key', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('is_hidden', 'boolean', array('default' => false, 'null' => false))
            ->addColumn('creator', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('created_at', 'datetime', array('null' => true))
            ->addColumn('updater', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('updated_at', 'datetime', array('null' => true))
            ->addIndex(array('anr_id'))
            ->create();
        $table->changeColumn('id', 'integer', array('identity'=>true, 'signed'=>false))->update();
        $table->addForeignKey('anr_id', 'anrs', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->update();

        //migrate current scale to the new format
        $datas = [
            'levelA' => ['key' =>'' ,'scaleIndex' => 0, 'colour' => '#FFFFFF',
             'comment1' => 'Inexistant', 'comment2' => 'Non-existent', 'comment3' => 'Nicht vorhanden', 'comment4' => 'Onbestaand'],
            'levelB' => ['key' =>'' ,'scaleIndex' => 1, 'colour' => '#FD661F',
             'comment1' => 'Initialisé', 'comment2' => 'Initial', 'comment3' => 'Initial', 'comment4' => 'Initieel'],
            'levelC' => ['key' =>'' ,'scaleIndex' => 2, 'colour' => '#FD661F',
             'comment1' => 'Reproductible', 'comment2' => 'Managed', 'comment3' => 'Reproduzierbar', 'comment4' => 'Beheerst'],
            'levelD' => ['key' =>'' ,'scaleIndex' => 3, 'colour' => '#FFBC1C',
             'comment1' => 'Défini', 'comment2' => 'Defined', 'comment3' => 'Definiert', 'comment4' => 'Gedefinieerd'],
            'levelE' => ['key' =>'' ,'scaleIndex' => 4, 'colour' => '#FFBC1C',
             'comment1' => 'Géré quantitativement', 'comment2' => 'Quantitatively managed', 'comment3' => 'Quantitativ verwaltet', 'comment4' => 'Kwantitatief beheerst' ],
            'levelF' => ['key' =>'' ,'scaleIndex' => 5, 'colour' => '#D6F107',
             'comment1' => 'Optimisé', 'comment2' => 'Optimized', 'comment3' => 'Optimiert', 'comment4' => 'Optimaliserend']
        ];

        $anrQuery = $this->query(
            'SELECT a.id
            FROM anrs a'
        );

        $soaScaleCommentTable = $this->table('soa_scale_comments');

        foreach ($anrQuery->fetchAll() as $anr) {
            foreach ($datas as $data) {
                $data['key'] = (string)Uuid::uuid4();
                $soaScaleCommentTable->insert([
                    'anr_id' => $anr['id'],
                    'scale_index' => $data['scaleIndex'],
                    'colour' => $data['colour'],
                    'comment_translation_key' => $data['key'],
                    'is_hidden' => 0,
                    'creator' => 'Migration script',
                ])->save();
            $this->createTranslations($data, Translation::SOA_SCALE_COMMENT, 'comment', $anr['id']);
            }
        }

        //manage the soa table
        $soaTable = $this->table('soa');
        $soaTable
            ->addColumn('soa_scale_comment_id', 'integer', array('null' => true, 'signed' => false))
            ->update();
        $soaTable->addIndex(array('soa_scale_comment_id'))->update();
        $soaTable
            ->addForeignKey('soa_scale_comment_id', 'soa_scale_comments', 'id', array('delete' => 'SET NULL','update' => 'RESTRICT'))
            ->update();

        $this->execute('UPDATE soa SET soa_scale_comment_id = NULL');

        $this->execute('UPDATE soa s SET s.soa_scale_comment_id =
            (SELECT id FROM soa_scale_comments ssc WHERE s.anr_id = ssc.anr_id AND ssc.scale_index = s.compliance);');
        // TO DO : uncomment the following line 
        // $this->execute('ALTER TABLE soa DROP COLUMN compliance');
    }

    private function createTranslations(array $data, string $type, string $fieldName, int $anr): void
    {
        $translations = [];
        foreach ([1 => 'fr', 2 => 'en', 3 => 'de', 4 => 'nl'] as $langKey => $langLabel) {
            if (!empty($data[$fieldName . $langKey])) {
                $translations[] = [
                    'anr_id' => $anr,
                    'type' => $type,
                    'translation_key' => $data['key'],
                    'lang' => $langLabel,
                    'value' => $data[$fieldName . $langKey],
                    'creator' => 'Migration script',
                ];
            }
        }
        $this->table('translations')->insert($translations)->save();
    }
}
