<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddSoaObjects extends AbstractMigration
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
            // Creation for table Soa
            $table = $this->table('Soa');
            $table
            //  ->addColumn('id', 'integer', array('null' => true, 'signed' => false))
                ->addColumn('justification', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_LONG))
                ->addColumn('evidences', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_LONG))
                ->addColumn('actions', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_LONG))
                ->addColumn('compliance', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('measure_id', 'integer', array('null' => true, 'signed' => false))
                ->addIndex(array('measure_id'))
                ->addColumn('anr_id', 'integer', array('null' => true, 'signed' => false))
                ->addIndex(array('anr_id'))
                ->addColumn('EX', 'integer', array('null' => true, 'default' => '0', 'limit' => MysqlAdapter::INT_TINY))
                ->addColumn('LR', 'integer', array('null' => true, 'default' => '0', 'limit' => MysqlAdapter::INT_TINY))
                ->addColumn('CO', 'integer', array('null' => true, 'default' => '0', 'limit' => MysqlAdapter::INT_TINY))
                ->addColumn('BR', 'integer', array('null' => true, 'default' => '0', 'limit' => MysqlAdapter::INT_TINY))
                ->addColumn('BP', 'integer', array('null' => true, 'default' => '0', 'limit' => MysqlAdapter::INT_TINY))
                ->addColumn('RRA', 'integer', array('null' => true, 'default' => '0', 'limit' => MysqlAdapter::INT_TINY))
                ->create();
            $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();
            $this->execute('
            INSERT INTO Soa ( measure_id,anr_id) SELECT  measures.id, measures.anr_id FROM measures ;');

            // Creation for table category
            $table = $this->table('category');
            $table
            //  ->addColumn('id', 'integer', array('null' => true, 'signed' => false))
                ->addColumn('label1', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_LONG))
                ->addColumn('label2', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_LONG))
                ->addColumn('label3', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_LONG))
                ->addColumn('label4', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_LONG))
                ->addColumn('reference', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('anr_id', 'integer', array('null' => true, 'signed' => false))
                ->addIndex(array('anr_id'))
                ->addColumn('status', 'integer', array('null' => true, 'default' => '1', 'limit' => 11))
                ->create();
            $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();
            $this->table('measures')
            ->addColumn('category_id', 'integer',  array('null' => true, 'default' => '15',  'signed' => false))
            ->save();
            //set the default iso27002 categories
            $this->query('INSERT INTO category (reference,label1, label2, label3,label4, anr_id)
            SELECT "5","Politiques de sécurité de l\'information","Information security policies","","" ,anrs.id from anrs union all
            SELECT "6","Organisation de la sécurité de l\'information","Organization of information security","","" ,anrs.id from anrs union all
            SELECT "7","La sécurité des ressources humaines","Human resource security","","",anrs.id from anrs union all
            SELECT "8","Gestion des actifs","Asset management","","",anrs.id from anrs union all
            SELECT "9","Contrôle d\'accès","Access control","","",anrs.id from anrs union all
            SELECT "10","Cryptographie","Cryptography","","",anrs.id from anrs union all
            SELECT "11","Sécurité physique et environnementale","Physical and environmental security","","",anrs.id from anrs union all
            SELECT "12","Sécurité liée à l\'exploitation","Operations security","","",anrs.id from anrs union all
            SELECT "13","Sécurité des communications","Communications security","","",anrs.id from anrs union all
            SELECT "14","Acquisition, développement et maintenance des systèmes d\'information","System acquisition, development and maintenance","","",anrs.id from anrs union all
            SELECT "15","Relations avec le fournisseurs","Supplier relationships","","",anrs.id from anrs union all
            SELECT "16","Gestion des incidents liés à la sécurité de l\'information","information security incident management","","",anrs.id from anrs union all
            SELECT "17","Aspects de la sécurité de l\'information dans la gestion de la continuité de l\'activité","Information security aspects of business continuity management","","",anrs.id from anrs union all
            SELECT "18","Conformité","Compliance","","",anrs.id from anrs;');
            // update categories / measures for all anrs
            $this->execute('UPDATE measures m SET m.category_id= (SELECT id FROM category c WHERE m.anr_id=c.anr_id AND m.code LIKE concat(c.reference ,".","%"));');
    }
}
