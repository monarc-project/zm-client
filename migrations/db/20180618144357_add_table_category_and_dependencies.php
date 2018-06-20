<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;


class AddTableCategoryAndDependencies extends AbstractMigration
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

       // Migration for table category
       $table = $this->table('category');
       $table
       //  ->addColumn('id', 'integer', array('null' => true, 'signed' => false))

           ->addColumn('label1', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_LONG))
           ->addColumn('label2', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_LONG))
           ->addColumn('label3', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_LONG))
           ->addColumn('label4', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_LONG))
           ->create();
       $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();





       $this->table('Soa')
       ->addColumn('category_id', 'integer', array('null' => true, 'signed' => false))
       ->save();


             $this->query('INSERT INTO category (label1, label2, label3,label4)
             VALUES ("Politiques de sécurité de l\'information","Information security policies","",""),
             ("Organisation de la sécurité de l\'information","Organization of information security ","",""),
             ("La sécurité des ressources humaines","Human resource security","",""),
             ("Gestion des actifs","Asset management","",""),
             ("Contrôle d\'accès","Access control","",""),
             ("Cryptographie","Cryptography","",""),
             ("Sécurité physique et environnementale","Physical and environmental security","",""),
             ("Sécurité liée à l\'exploitation","Operations security","",""),
             ("Sécurité des communications","Communications security","",""),
             ("Acquisition, développement et maintenance des systèmes d\'information","System acquisition, development and maintenance","",""),
             ("Relations avec le fournisseurs","Supplier relationships","",""),
             ("Gestion des incidents liés à la sécurité de l\'information","information security incident management","",""),
             ("Aspects de la sécurité de l\'information dans la gestion de la continuité de l\'activité","Aspects de la sécurité de l\'information dans la gestion de la continuité de l\'activité","",""),
              ("Conformité","Compliance","","");');





     }
}
