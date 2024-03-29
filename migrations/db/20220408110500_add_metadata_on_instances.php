<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddMetadataOnInstances extends AbstractMigration
{
    /**
     * Performs validation and adding of different languages missing translations of the op scales values.
     */
    public function change()
    {
        //create the table which contains the metadata by anr
        $table = $this->table('anr_metadatas_on_instances');
        $table
            ->addColumn('anr_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('label_translation_key', 'string', array('null' => true, 'limit' => 255))
            ->addColumn(
                'is_deletable',
                'integer',
                array('null' => true, 'default' => '1', 'limit' => MysqlAdapter::INT_TINY)
            )
            ->addColumn('creator', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('created_at', 'datetime', array('null' => true))
            ->addColumn('updater', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('updated_at', 'datetime', array('null' => true))
            ->addIndex(array('anr_id'))
            ->create();
        $table->changeColumn('id', 'integer', array('identity'=>true,'signed'=>false))->update();
        $table->addForeignKey('anr_id', 'anrs', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))->update();

        //create the link between instances and metadata
        $table = $this->table('instances_metadatas');
        $table
            ->addColumn('instance_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('metadata_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('comment_translation_key', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('creator', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('created_at', 'datetime', array('null' => true))
            ->addColumn('updater', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('updated_at', 'datetime', array('null' => true))
            ->addIndex(array('instance_id'))
            ->addIndex(array('metadata_id'))
            ->create();
        $table->changeColumn('id', 'integer', array('identity'=>true,'signed'=>false))->update();
        $table
            ->addForeignKey('instance_id', 'instances', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->addForeignKey(
                'metadata_id',
                'anr_metadatas_on_instances',
                'id',
                array('delete' => 'CASCADE','update' => 'RESTRICT')
            )
            ->addIndex(array('instance_id', 'metadata_id'), array('unique'=>true))
            ->update();
    }
}
