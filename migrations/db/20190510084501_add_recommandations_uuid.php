<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Util\Literal;
use Ramsey\Uuid\Uuid;

class AddRecommandationsUuid extends AbstractMigration
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
        //uuid for recommandations

        $data = array(
            'Rec 5' => '8b921900-7301-11e9-b475-0800200c9a66',
            'Rec 9' => '8b92190a-7301-11e9-b475-0800200c9a66',
            'Rec 1' => '8b92191d-7301-11e9-b475-0800200c9a66',
            'Rec 88' => '8b924017-7301-11e9-b475-0800200c9a66',
            'Rec 34' => '8b924022-7301-11e9-b475-0800200c9a66',
            'Rec 2' => '8b92402a-7301-11e9-b475-0800200c9a66',
            'Rec 4' => '8b924035-7301-11e9-b475-0800200c9a66',
            'Rec 7' => '8b92403a-7301-11e9-b475-0800200c9a66',
            'Rec 8' => '8b924041-7301-11e9-b475-0800200c9a66',
            'Rec 6' => '8b926729-7301-11e9-b475-0800200c9a66',
            'REC RISQUE OP' => '8b926737-7301-11e9-b475-0800200c9a66'
        );

        // Migration for table recommandations -- Modify the data
        $table = $this->table('recommandations');
        $table
            ->addColumn('uuid', 'uuid', array('after' => 'id'))
            ->addIndex(array('anr_id','code'))
            ->addIndex(array('uuid'))
            ->update();
        foreach ($data as $key => $value) { //fill the uuid only for recommandations created by cases
            $this->execute('UPDATE recommandations SET uuid =' . '"' . $value . '"' . ' WHERE code =' . '"' . $key . '"');
        }
        
        $table = $this->table('recommandations_risks'); //set the stufff for recommandations_risks
        $table->dropForeignKey('recommandation_id')
            ->addColumn('recommandation_uuid', 'uuid', array('after' => 'id'))
            ->update();
        $this->execute('UPDATE recommandations_risks A,recommandations B SET A.recommandation_uuid = B.uuid where B.id=A.recommandation_id');
        $table->removeColumn('recommandation_id')
            ->renameColumn('recommandation_uuid', 'recommandation_id')
            ->update();

        $table = $this->table('recommandations_measures'); //set the stufff for recommandations_measures
        $table->dropForeignKey('recommandation_id')
            ->addColumn('recommandation_uuid', 'uuid', array('after' => 'id'))
            ->update();
        $this->execute('UPDATE recommandations_measures A,recommandations B SET A.recommandation_uuid = B.uuid where B.id=A.recommandation_id');
        $table->removeColumn('recommandation_id')
            ->renameColumn('recommandation_uuid', 'recommandation_id')
            ->update();

        //remove the id
        $table = $this->table('recommandations');
        $table->removeColumn('id')
            ->dropForeignKey('anr_id')
            ->save();
        $this->execute("ALTER TABLE recommandations ADD PRIMARY KEY uuid_anr_id (uuid,anr_id)");

        //manage Foreign keys
        $table = $this->table('recommandations_risks');
        $table->addForeignKey(['recommandation_id', 'anr_id'], 'recommandations', ['uuid', 'anr_id'], ['delete' => 'CASCADE', 'update' => 'RESTRICT'])
            ->update();
        $table = $this->table('recommandations_measures');
        $table->addForeignKey(['recommandation_id', 'anr_id'], 'recommandations', ['uuid', 'anr_id'], ['delete' => 'CASCADE', 'update' => 'RESTRICT'])
            ->update();
    }
}
