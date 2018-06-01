<?php

use Phinx\Migration\AbstractMigration;

class SoasCopy extends AbstractMigration
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
      INSERT INTO Soa (reference, control, measure_id,anr_id) SELECT measures.code, measures.description1, measures.id, measures.anr_id FROM measures, anrs WHERE measures.anr_id=anrs.id AND anrs.language=\'1\';

      INSERT INTO Soa (reference, control, measure_id,anr_id) SELECT measures.code, measures.description2, measures.id, measures.anr_id FROM measures, anrs WHERE measures.anr_id=anrs.id AND anrs.language=\'2\';

      INSERT INTO Soa (reference, control, measure_id,anr_id) SELECT measures.code, measures.description3, measures.id, measures.anr_id FROM measures, anrs WHERE measures.anr_id=anrs.id AND anrs.language=\'3\';

      INSERT INTO Soa (reference, control, measure_id,anr_id) SELECT measures.code, measures.description3, measures.id, measures.anr_id FROM measures, anrs WHERE measures.anr_id=anrs.id AND anrs.language=\'4\';
      ');
      }
}
