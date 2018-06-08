<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class OperationalRisksAreNotUpdated extends AbstractMigration
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
        $table = $this->table('instances_risks_op');
        $this->query('update instances_risks_op set instances_risks_op.rolf_risk_id = (
                        select distinct(rolf_risks.id) from rolf_risks_tags, rolf_risks, objects
                        where (rolf_risks.label1 = instances_risks_op.risk_cache_label1 OR rolf_risks.label2 = instances_risks_op.risk_cache_label2
                        OR rolf_risks.label3 = instances_risks_op.risk_cache_label3 OR rolf_risks.label4 = instances_risks_op.risk_cache_label4  )
                        and objects.rolf_tag_id = rolf_risks_tags.rolf_tag_id
                        and rolf_risks_tags.rolf_risk_id = rolf_risks.id
                        and objects.id = instances_risks_op.object_id
                        and instances_risks_op.anr_id = rolf_risks.anr_id
                        )');
        $this->query('update instances_risks_op set instances_risks_op.rolf_risk_id = (
                        select distinct(rolf_risks.id) from  rolf_risks
                        where (rolf_risks.label1 = instances_risks_op.risk_cache_label1 OR rolf_risks.label2 = instances_risks_op.risk_cache_label2
                        OR rolf_risks.label3 = instances_risks_op.risk_cache_label3 OR rolf_risks.label4 = instances_risks_op.risk_cache_label4  )
                        and instances_risks_op.anr_id = rolf_risks.anr_id
                        )
                        ');
    }
}
