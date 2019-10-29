<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Util\Literal;
use Ramsey\Uuid\Uuid;

class AddRecommandationsSets extends AbstractMigration
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
        $table = $this->table('recommandations_sets');
        $table
            ->addColumn('anr_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('uuid', 'uuid')
            ->addColumn('label1', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('label2', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('label3', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('label4', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('creator', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('created_at', 'datetime', array('null' => true))
            ->addColumn('updater', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('updated_at', 'datetime', array('null' => true))
            ->addIndex(array('uuid'))
            ->addIndex(['uuid', 'anr_id'], ['unique' => true])
            ->create();

        $table->changeColumn('id', 'integer', array('identity' => true, 'signed' => false))->update();

        $table
            ->addForeignKey('anr_id', 'anrs', 'id', array('delete' => 'CASCADE', 'update' => 'RESTRICT'))
            ->update();

        // Create a defaut existing recommendations set for each analysis
        $recommandations_sets = [];
        $anr_ids = $this->fetchAll('SELECT id FROM anrs');
        foreach ($anr_ids as $anr_id) {
            $recommandations_sets[] = [
                'anr_id' => $anr_id['id'],
                'uuid' => 'b1c26f12-7ba3-11e9-8f9e-2a86e4085a59',
                'label1' => 'Recommandations existantes',
                'label2' => 'Existing Recommendations',
                'label3' => 'Bestehenden Empfehlungen',
                'label4' => 'Bestaande Aanbevelingen',
                'creator' => 'Migration script',
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        if (count($recommandations_sets) > 0)
            $this->insert("recommandations_sets", $recommandations_sets);

        //remove the id
        $table->removeColumn('id')
            ->dropForeignKey('anr_id')
            ->save();
        $this->execute("ALTER TABLE recommandations_sets ADD PRIMARY KEY uuid_anr_id (uuid, anr_id)");
        $table->addForeignKey('anr_id', 'anrs', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->update();

        //add foreign key for recommendations
        $table = $this->table('recommandations');
        $table
            ->addColumn('recommandation_set_uuid', 'uuid', ['after' => 'anr_id'])
            ->dropForeignKey('anr_id')
            ->update();

        $unUUIDpdo = $this->query('select uuid, code from recommandations' . ' WHERE recommandation_set_uuid =' . '"' . '"');
        $unUUIDrows = $unUUIDpdo->fetchAll();

        foreach ($unUUIDrows as $key => $value) {
            $quotedCode = $this->quote($value['code']);
            $this->execute('UPDATE recommandations SET recommandation_set_uuid =' . '"' . 'b1c26f12-7ba3-11e9-8f9e-2a86e4085a59' . '"' . ' WHERE code =' . '"' . $quotedCode . '"'); //seta default set
        }

          //select duplicates[code,anr,set]
          $duplicates = $this->query('select *
                                      from recommandations t
                                        WHERE code in
                                          (select code from recommandations r
                                          where t.anr_id = r.anr_id
                                          and STRCMP(r.recommandation_set_uuid,t.recommandation_set_uuid)=0
                                          group by code having count(*)>1)')
                              ->fetchAll();
          foreach ($duplicates as $key => $value) {
              $quotedCode = $this->quote($value['code']);
              $this->execute('UPDATE recommandations SET code ="' .$quotedCode.' #'. substr(uniqid(),-5) .
                '" WHERE code =' . '"' . $quotedCode . '" and uuid="'.$value['uuid'].'" and recommandation_set_uuid="'.$value['recommandation_set_uuid'] .'" and anr_id='.$value['anr_id']);
          }

        $table
            ->addIndex(['recommandation_set_uuid', 'code', 'anr_id'], ['unique' => true]) // we can't have 2 times the same code for the same set
            ->addForeignKey(['recommandation_set_uuid', 'anr_id'], 'recommandations_sets', ['uuid', 'anr_id'], array('delete' => 'CASCADE', 'update' => 'RESTRICT'))
            ->update();
    }
}
