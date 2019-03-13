<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Util\Literal;

class AddSoaDeliverable extends AbstractMigration
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
      $singleRow = [
            'category'  => 5,
            'description1' => "Déclaration d'applicabilité",
            'description2' => 'Statement of applicability',
            	'description3' => 'Anwendbarkeitserklärung',
              'description4' => 'Verklaring van toepasselijkheid',
              'path1' => './deliveries/cases/FR/5.docx',
              'path2' => './deliveries/cases/EN/5.docx',
              'path3'	=> './deliveries/cases/DE/5.docx',
              'path4' => './deliveries/cases/NE/5.docx',
              'editable' => 0,
        ];
      $table = $this->table('deliveries_models');
        $table->insert($singleRow);
        $table->saveData();


    }
}
