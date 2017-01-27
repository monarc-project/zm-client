<?php

use Phinx\Migration\AbstractMigration;

class RecommandationsRebuildPositions extends AbstractMigration
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
            UPDATE recommandations SET position = NULL;

            SET @current_count = NULL;
            SET @current_anr = NULL;

            UPDATE recommandations
            SET position = CASE
                WHEN @current_anr = anr_id THEN @current_count := @current_count +1
                WHEN @current_anr := anr_id THEN @current_count := 1
            END
            WHERE id IN (
                SELECT rr.recommandation_id
                FROM recommandations_risks rr
                LEFT JOIN instances_risks ir
                ON ir.id = rr.instance_risk_id
                LEFT JOIN instances_risks_op iro
                ON iro.id = rr.instance_risk_op_id
                WHERE ir.kind_of_measure IN (1,2,3,4)
                OR iro.kind_of_measure IN (1,2,3,4)
                GROUP BY rr.recommandation_id
            )
            ORDER BY anr_id ASC, importance DESC, code ASC, id ASC;
        ');
    }
}
