<?php

use Phinx\Migration\AbstractMigration;

class DeleteModels extends AbstractMigration
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
    public function up()
    {
        $this->table('assets_models')
            ->dropForeignKey('asset_id')
            ->dropForeignKey('model_id')
            ->update();
        $this->dropTable('assets_models');

        $this->table('deliveries_models');
        $this->dropTable('deliveries_models');

        $this->table('threats_models')
            ->dropForeignKey('threat_id')
            ->dropForeignKey('model_id')
            ->update();
        $this->dropTable('threats_models');

        $this->table('vulnerabilities_models')
            ->dropForeignKey('vulnerability_id')
            ->dropForeignKey('model_id')
            ->update();
        $this->dropTable('vulnerabilities_models');

        $this->table('clients')
            ->dropForeignKey('model_id')
            ->update();

        $this->table('objects')
            ->dropForeignKey('model_id')
            ->update();

        $this->table('models')
            ->dropForeignKey('anr_id')
            ->update();
        $this->dropTable('models');

    }

    public function down()
    {
        // Migration for table models
        $table = $this->table('models');
        $table
            ->addColumn('anr_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('label1', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('label2', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('label3', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('label4', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('description1', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_LONG))
            ->addColumn('description2', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_LONG))
            ->addColumn('description3', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_LONG))
            ->addColumn('description4', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_LONG))
            ->addColumn('status', 'integer', array('null' => true, 'default' => '1', 'limit' => MysqlAdapter::INT_TINY))
            ->addColumn('is_scales_updatable', 'integer', array('null' => true, 'default' => '1', 'limit' => MysqlAdapter::INT_TINY))
            ->addColumn('is_default', 'integer', array('null' => true, 'default' => '0', 'limit' => MysqlAdapter::INT_TINY))
            ->addColumn('is_deleted', 'integer', array('null' => true, 'default' => '0', 'limit' => MysqlAdapter::INT_TINY))
            ->addColumn('is_generic', 'integer', array('null' => true, 'default' => '0', 'limit' => MysqlAdapter::INT_TINY))
            ->addColumn('is_regulator', 'integer', array('null' => true, 'default' => '0', 'limit' => MysqlAdapter::INT_TINY))
            ->addColumn('show_rolf_brut', 'integer', array('null' => true, 'default' => '0', 'limit' => MysqlAdapter::INT_TINY))
            ->addColumn('creator', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('created_at', 'datetime', array('null' => true))
            ->addColumn('updater', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('updated_at', 'datetime', array('null' => true))
            ->addIndex(array('anr_id'))
            ->create();
        $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();


        // Migration for table assets_models
        $table = $this->table('assets_models', array('id' => false, 'primary_key' => array('asset_id', 'model_id')));
        $table
            ->addColumn('asset_id', 'integer', array('signed' => false))
            ->addColumn('model_id', 'integer', array('signed' => false))
            ->addIndex(array('asset_id'))
            ->addIndex(array('model_id'))
            ->create();

        // Migration for table deliveries_models
        $table = $this->table('deliveries_models');
        $table
            ->addColumn('anr_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('typedoc', 'integer', array('default' => '0', 'limit' => MysqlAdapter::INT_TINY))
            ->addColumn('name1', 'string', array('limit' => 255))
            ->addColumn('name2', 'string', array('limit' => 255))
            ->addColumn('name3', 'string', array('limit' => 255))
            ->addColumn('name4', 'string', array('limit' => 255))
            ->addColumn('content', 'blob', array('default' => '', 'limit'=>MysqlAdapter::BLOB_LONG))
            ->addColumn('description1', 'string', array('default' => '', 'limit' => 255))
            ->addColumn('description2', 'string', array('default' => '', 'limit' => 255))
            ->addColumn('description3', 'string', array('default' => '', 'limit' => 255))
            ->addColumn('description4', 'string', array('default' => '', 'limit' => 255))
            ->addColumn('creator', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('created_at', 'datetime', array('null' => true))
            ->addColumn('updater', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('updated_at', 'datetime', array('null' => true))
            ->addIndex(array('anr_id'))
            ->addIndex(array('typedoc'))
            ->create();
        $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();

        // Migration for table threats_models
        $table = $this->table('threats_models', array('id' => false, 'primary_key' => array('threat_id', 'model_id')));
        $table
            ->addColumn('threat_id', 'integer', array('signed' => false))
            ->addColumn('model_id', 'integer', array('signed' => false))
            ->addIndex(array('threat_id'))
            ->addIndex(array('model_id'))
            ->create();

        // Migration for table vulnerabilities_models
        $table = $this->table('vulnerabilities_models', array('id' => false, 'primary_key' => array('vulnerability_id', 'model_id')));
        $table
            ->addColumn('vulnerability_id', 'integer', array('signed' => false))
            ->addColumn('model_id', 'integer', array('signed' => false))
            ->addIndex(array('vulnerability_id'))
            ->addIndex(array('model_id'))
            ->create();


        $table = $this->table('models');
        $table
            ->addForeignKey('anr_id', 'anrs', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->update();

        $table = $this->table('assets_models', array('id' => false, 'primary_key' => array('asset_id', 'model_id')));
        $table
            ->addForeignKey('asset_id', 'assets', 'id', array('delete' => 'RESTRICT','update' => 'RESTRICT'))
            ->addForeignKey('model_id', 'models', 'id', array('delete' => 'RESTRICT','update' => 'RESTRICT'))
            ->update();

        $table = $this->table('deliveries_models');
        $table
            ->addForeignKey('anr_id', 'anrs', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->update();

        $table = $this->table('threats_models');
        $table
            ->addForeignKey('threat_id', 'threats', 'id', array('delete' => 'RESTRICT','update' => 'RESTRICT'))
            ->addForeignKey('model_id', 'models', 'id', array('delete' => 'RESTRICT','update' => 'RESTRICT'))
            ->update();

        $table = $this->table('vulnerabilities_models');
        $table
            ->addForeignKey('vulnerability_id', 'vulnerabilities', 'id', array('delete' => 'RESTRICT','update' => 'RESTRICT'))
            ->addForeignKey('model_id', 'models', 'id', array('delete' => 'RESTRICT','update' => 'RESTRICT'))
            ->update();

        $table = $this->table('clients');
        $table
            ->addForeignKey('model_id', 'models', 'id', array('delete' => 'RESTRICT','update' => 'RESTRICT'))
            ->update();

        $table = $this->table('objects');
        $table
            ->addForeignKey('model_id', 'models', 'id', array('delete' => 'RESTRICT','update' => 'RESTRICT'))
            ->update();
    }
}
