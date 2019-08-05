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
        // Migration for table recommandations -- Modify the data
        $table = $this->table('recommandations');
        $table
            ->addColumn('uuid', 'uuid', array('after' => 'id'))
            ->addColumn('status', 'integer', array('after' => 'comment','null' => true, 'default' => '1', 'limit' => MysqlAdapter::INT_TINY))
            ->update();

        //normally it can't have the same code number in the same anr
        $distinctRecoPdo = $this->query('select distinct code, description from recommandations where code !="" and description !="" and code is not null and description is not null');
        $distinctRecoRows = $distinctRecoPdo->fetchAll();

        foreach ($distinctRecoRows as $key => $value) {
          $uniqid = Uuid::uuid4();
          $recosPDO = $this->query('select id,anr_id from recommandations' . ' WHERE code ="'.$value['code']. '" and description ="'.$value['description'].'"');
          $recos = $recosPDO->fetchAll();
          $updatable=true;
          foreach ($recos as $k => $v) {
            foreach ($recos as $j => $d) {
              if($v['anr_id']==$d['anr_id'] && $v['id']!= $d['id']) //if there is a possibility of duplicate don't update the value
                $updatable = false;
            }
            if($updatable)
              $this->execute('UPDATE recommandations SET uuid =' . '"' . $uniqid . '"' . ' WHERE id =' . $v['id']);
            $updatable = true;
          }
        }

        $unUUIDpdo = $this->query('select uuid,id from recommandations' . ' WHERE uuid =' . '"' . '"');
        $unUUIDrows = $unUUIDpdo->fetchAll();

        foreach ($unUUIDrows as $key => $value) {
            $this->execute('UPDATE recommandations SET uuid =' . '"' . Uuid::uuid4() . '"' . ' WHERE id =' . $value['id']); //manage recommandations which are not processed before
        }

        $table = $this->table('recommandations_risks'); //set the stufff for recommandations_risks
        $table->dropForeignKey('recommandation_id')
            ->addColumn('recommandation_uuid', 'uuid', array('after' => 'id'))
            ->update();
        $this->execute('UPDATE recommandations_risks A,recommandations B SET A.recommandation_uuid = B.uuid where B.id=A.recommandation_id');
        $table->removeColumn('recommandation_id')
            ->renameColumn('recommandation_uuid', 'recommandation_id')
            ->update();

        //remove the id
        $table = $this->table('recommandations');
        $table->removeColumn('id')
            ->dropForeignKey('anr_id')
            ->removeIndexByName('anr_id')
            ->addIndex(array('anr_id', 'code'), array('name' => 'anr_id'))
            ->addIndex(array('anr_id'), array('name' => 'anr_id_2'))
            ->addIndex(array('uuid'))
            ->update();
        $this->execute("ALTER TABLE recommandations ADD PRIMARY KEY uuid_anr_id (uuid,anr_id)");

        //manage Foreign keys
        $table = $this->table('recommandations_risks');
        $table->addForeignKey(['recommandation_id', 'anr_id'], 'recommandations', ['uuid', 'anr_id'], ['delete' => 'CASCADE', 'update' => 'RESTRICT'])
            ->update();
    }
}
