<?php

use Phinx\Migration\AbstractMigration;

class UpdateCategoriesIdForSoa extends AbstractMigration
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
       $this->execute('

       UPDATE Soa SET category_id=1 WHERE reference LIKE \'5.%\';
       UPDATE Soa SET category_id=2 WHERE reference LIKE \'6.%\';
       UPDATE Soa SET category_id=3 WHERE reference LIKE \'7.%\';
       UPDATE Soa SET category_id=4 WHERE reference LIKE \'8.%\';
       UPDATE Soa SET category_id=5 WHERE reference LIKE \'9.%\';
       UPDATE Soa SET category_id=6 WHERE reference LIKE \'10.%\';
       UPDATE Soa SET category_id=7 WHERE reference LIKE \'11.%\';
       UPDATE Soa SET category_id=8 WHERE reference LIKE \'12.%\';
       UPDATE Soa SET category_id=9 WHERE reference LIKE \'13.%\';
       UPDATE Soa SET category_id=10 WHERE reference LIKE \'14.%\';
       UPDATE Soa SET category_id=11 WHERE reference LIKE \'15.%\';
       UPDATE Soa SET category_id=12 WHERE reference LIKE \'16.%\';
       UPDATE Soa SET category_id=13 WHERE reference LIKE \'17.%\';
       UPDATE Soa SET category_id=14 WHERE reference LIKE \'18.%\';

       ');

       }
 }
