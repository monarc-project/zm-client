<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddTableDeliveriesModels extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        $table = $this->table('deliveries_models');
        $table
            ->addColumn('anr_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('category', 'integer', array('default' => '0', 'limit' => MysqlAdapter::INT_TINY))
            ->addColumn('description1', 'string', array('null' => true, 'default' => '', 'limit' => 255))
            ->addColumn('description2', 'string', array('null' => true, 'default' => '', 'limit' => 255))
            ->addColumn('description3', 'string', array('null' => true, 'default' => '', 'limit' => 255))
            ->addColumn('description4', 'string', array('null' => true, 'default' => '', 'limit' => 255))
            ->addColumn('path1', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('path2', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('path3', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('path4', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('editable', 'boolean', array('default' => true))
            ->addColumn('creator', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('created_at', 'datetime', array('null' => true))
            ->addColumn('updater', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('updated_at', 'datetime', array('null' => true))
            ->addIndex(array('anr_id'))
            ->addIndex(array('category'))
            ->create();
        $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();

        $this->query('INSERT INTO deliveries_models (category, description1, path1, creator, created_at, updater, updated_at, description2, description3, description4, path2, path3, path4, editable) VALUES (1, "Validation de contexte", "./data/monarc/models/FR/1.docx", NULL , NULL, NULL, NULL, "Context validation", "Kontextüberprüfung", NULL, "./data/monarc/models/EN/1.docx", "./data/monarc/models/DE/1.docx", "./data/monarc/models/NE/1.docx", false), (2, "Validation du modèle"  , "./data/monarc/models/FR/2.docx", NULL , NULL, NULL, NULL, "Modelling validation", "Modellierungsüberprüfung", NULL, "./data/monarc/models/EN/2.docx", "./data/monarc/models/DE/2.docx", "./data/monarc/models/NE/2.docx", false), (3, "Rapport final", "./data/monarc/models/FR/3.docx", NULL, NULL, NULL, NULL, "Report risk assessment", "Risikobeurteilungsbericht", NULL, "./data/monarc/models/EN/3.docx", "./data/monarc/models/DE/3.docx", "./data/monarc/models/NE/3.docx", false), (4, "Plan dimplémentation" , "./data/monarc/models/FR/4.docx", NULL, NULL, NULL, NULL, "Implementation plan", "Implementierungsplan", "Implementatieplan", "./data/monarc/models/EN/4.docx", "./data/monarc/models/DE/4.docx", "./data/monarc/models/NE/4.docx", false);');
    }
}
