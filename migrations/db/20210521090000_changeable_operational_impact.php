<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Util\Literal;
use Ramsey\Uuid\Uuid;

class ChangeableOperationalImpact extends AbstractMigration
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
        $conn = $this->getAdapter()->getConnection();

        // Creation of table scales_op
        $table = $this->table('scales_op');
        $table
            ->addColumn('anr_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('type', 'integer', array('null' => true, 'default' => '0', 'limit' => MysqlAdapter::INT_TINY))
            ->addColumn('min', 'integer', array('null' => true, 'default' => '0'))
            ->addColumn('max', 'integer', array('null' => true, 'default' => '0'))
            ->addColumn('label1', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('label2', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('label3', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('label4', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('creator', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('created_at', 'datetime', array('null' => true))
            ->addColumn('updater', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('updated_at', 'datetime', array('null' => true))
            ->addColumn('old_scale_impact_type_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('old_impact_type', 'integer', array('null' => true, 'signed' => false))
            ->addIndex(array('anr_id'))
            ->addIndex(array('type'))
            ->create();
        $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();
        $table->addForeignKey('anr_id', 'anrs', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))->update();

        //creation of tabels scales_comments_op
        $table = $this->table('scales_comments_op');
        $table
            ->addColumn('anr_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('scale_op_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('scale_index', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('val', 'integer', array('null' => true, 'default' => '0'))
            ->addColumn('comment1', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_LONG))
            ->addColumn('comment2', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_LONG))
            ->addColumn('comment3', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_LONG))
            ->addColumn('comment4', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_LONG))
            ->addColumn('creator', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('created_at', 'datetime', array('null' => true))
            ->addColumn('updater', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('updated_at', 'datetime', array('null' => true))
            ->addIndex(array('anr_id'))
            ->addIndex(array('val'))
            ->addIndex(array('scale_index'))
            ->addIndex(array('scale_op_id'))
            ->create();
        $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();
        $table->addForeignKey('anr_id', 'anrs', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
              ->addForeignKey('scale_op_id', 'scales_op', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
              ->update();


        //migration of impact
        // migration of scales
        $scales_op_array = [];
        $scalePdo = $this->query('select scales.id as scale_id, scales.anr_id as anr, scales_impact_types.label1 as label1, scales_impact_types.label2 as label2, scales_impact_types.label3 as label3, scales_impact_types.label4 as label4,
                                  scales.min as min, scales.max as max, scales_impact_types.id as impact_type,  scales_impact_types.type as old_type
                                  from scales_impact_types, scales
                                  where scales.id = scales_impact_types.scale_id
                                  and scales.type = 1
                                  and scales_impact_types.type > 3');
        $scaleRows = $scalePdo->fetchAll();

        foreach ($scaleRows as $scale) {
          $scales_op_array[] = [
              'anr_id' => $scale['anr'],
              'type' => 1,
              'min' => $scale['min'],
              'max' => $scale['max'],
              'label1' => $scale['label1'],
              'label2' => $scale['label2'],
              'label3' => $scale['label3'],
              'label4' => $scale['label4'],
              'creator' => 'Migration script',
              'created_at' => date('Y-m-d H:i:s'),
              'old_scale_impact_type_id' => $scale['impact_type'], //kind of pivot for the next migration
              'old_impact_type' => $scale['old_type'] // 3:likelihood 4:R 5:O 6:L 7:F 8:P -- easier to migrate instances_risks_op >8 = custom
          ];
        }
        $this->table('scales_op')->insert($scales_op_array)->save();

        //migration of comment
        $scales_comment_op_array = [];
        $scaleCommentPdo = $this->query('select scales_op.id as new_scale_id, scales_comments.id as iid, scales_comments.anr_id as anr, scales_comments.scale_type_impact_id as impact_type, scales_comments.val as val,
                                        scales_comments.comment1 as comment1,  scales_comments.comment2 as comment2, scales_comments.comment3 as comment3, scales_comments.comment4 as comment4
                                        from scales_comments, scales_op
                                        where scales_op.old_scale_impact_type_id = scales_comments.scale_type_impact_id');
        $scaleCommentRows = $scaleCommentPdo->fetchAll();

        foreach ($scaleCommentRows as $scale) {
          $scales_comment_op_array[] = [
            'anr_id' => $scale['anr'],
            'scale_op_id'	 => $scale['new_scale_id'],
            'scale_index'	 => $scale['val'],
            'val'	 => $scale['val'],
            'comment1' => $scale['comment1'],
            'comment2' => $scale['comment2'],
            'comment3'	 => $scale['comment3'],
            'comment4' => $scale['comment4'],
            'creator'	 => 'Migration script',
            'created_at' => date('Y-m-d H:i:s')

          ];
        }
        $this->table('scales_comments_op')->insert($scales_comment_op_array)->save();

        $this->table('scales_op')->removeColumn('old_scale_impact_type_id')->update();

        //migration of likelihood
        //migration of scales
        $likelihood_op_array = [];
        $likelihoodPdo = $this->query('select scales.id as scale_id, scales.anr_id as anr, scales.min as min, scales.max as max
                                      from  scales
                                      where  scales.type = 2');
        $likelihoodRows = $likelihoodPdo->fetchAll();

        foreach ($likelihoodRows as $scale) {
          $likelihood_op_array[] = [
              'anr_id' => $scale['anr'],
              'type' => 2,
              'min' => $scale['min'],
              'max' => $scale['max'],
              'label1' => NULL,
              'label2' => NULL,
              'label3' => NULL,
              'label4' => NULL,
              'old_impact_type' => 3,
              'creator' => 'Migration script',
              'created_at' => date('Y-m-d H:i:s'),
          ];
        }
        $this->table('scales_op')->insert($likelihood_op_array)->save();

        // migartion of comment
        $likelihood_comment_op_array = [];
        $likelihoodCommentPdo = $this->query('select scales_op.id as new_scale_id, scales.id as old_scale_id, scales.anr_id as anr,
                                              scales_comments.val as val, scales_comments.comment1 as comment1, scales_comments.comment2 as comment2, scales_comments.comment3 as comment3, scales_comments.comment4 as comment4
                                              from  scales, scales_op, scales_comments
                                              where  scales.type = 2 and scales_op.type = 2
                                              and scales.anr_id = scales_op.anr_id
                                              and scales_comments.scale_id = scales.id');
        $likelihoodCommentRows = $likelihoodCommentPdo->fetchAll();

        foreach ($likelihoodCommentRows as $scale) {
          $likelihood_comment_op_array[] = [
            'anr_id' => $scale['anr'],
            'scale_op_id'	 => $scale['new_scale_id'],
            'scale_index'	 => $scale['val'],
            'val'	 => $scale['val'],
            'comment1' => $scale['comment1'],
            'comment2' => $scale['comment2'],
            'comment3'	 => $scale['comment3'],
            'comment4' => $scale['comment4'],
            'creator'	 => 'Migration script',
            'created_at' => date('Y-m-d H:i:s')

          ];
        }
        $this->table('scales_comments_op')->insert($likelihood_comment_op_array)->save();

        //migration of instances_risks_op
        // creation of a new n-n relation
        // Creation of table intances_risks_scales_op
        $table = $this->table('intances_risks_scales_op');
        $table
            ->addColumn('intance_risk_op_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('scale_op_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('brut_value', 'integer', array('null' => true, 'default' => '0'))
            ->addColumn('net_value', 'integer', array('null' => true, 'default' => '0'))
            ->addColumn('targeted_value', 'integer', array('null' => true, 'default' => '0'))
            ->addColumn('creator', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('created_at', 'datetime', array('null' => true))
            ->addColumn('updater', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('updated_at', 'datetime', array('null' => true))
            ->addIndex(array('intance_risk_op_id','scale_op_id'),array('unique'=>true))
            ->addIndex(array('intance_risk_op_id'))
            ->create();
        $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();
        $table->addForeignKey('intance_risk_op_id', 'instances_risks_op', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))->update();
        $table->addForeignKey('scale_op_id', 'scales_op', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))->update();

        //fill the table
        $intances_risks_scales_op_array = [];
        $instancesRisksOpPdo = $this->query('select id as instances_risks_op_id,anr_id as anr, brut_prob as brut_prob, brut_r as brut_r, brut_o as brut_o, brut_l as brut_l, brut_f as brut_f, brut_p as brut_p,
                                  net_prob as net_prob, net_r as net_r, net_o as net_o, net_l as  net_l, net_f as net_f, net_p as net_p,
                                  targeted_prob as targeted_prob, targeted_r as targeted_r, targeted_o as targeted_o, targeted_l as targeted_l, targeted_f as targeted_f, targeted_p as targeted_p
                                  from instances_risks_op');
        $instancesRisksOpRows = $instancesRisksOpPdo->fetchAll();


        $scalesOPPdo = $this->query('select id as scale_id, anr_id as anr, old_impact_type as impact_type
                                            from scales_op');
        $scaleRows = $scalesOPPdo->fetchAll();
        $impactType = array(3 => '_prob', 4 => '_r', 5 => '_o', 6 => '_l', 7 => '_f', 8 => '_p');
        $tempID = 0;

        foreach ($instancesRisksOpRows as $instancesRisksOp) {
          for ($i=3; $i <9 ; $i++) {
            foreach ($scaleRows as  $scale) {
              if($i==3 && $scale['anr']==$instancesRisksOp['anr'] && $scale['impact_type']==3)
                $tempID = $scale['scale_id'];
              else if($scale['anr']==$instancesRisksOp['anr'] && $scale['impact_type']==$i && $i !=3)
                $tempID = $scale['scale_id'];
            }
            $intances_risks_scales_op_array[] = [
              'intance_risk_op_id' => $instancesRisksOp['instances_risks_op_id'],
              'scale_op_id'	 => $tempID,
              'brut_value'	 => $instancesRisksOp['brut'.$impactType[$i]],
              'net_value'	 => $instancesRisksOp['net'.$impactType[$i]],
              'targeted_value' => $instancesRisksOp['targeted'.$impactType[$i]],
              'creator'	 => 'Migration script',
              'created_at' => date('Y-m-d H:i:s')

            ];
          }
        }
        //manage custom scales type > 8
        foreach ($scaleRows as  $scale) {
          if($scale['impact_type']>8){
            foreach ($instancesRisksOpRows as $instancesRisksOp) {
              $intances_risks_scales_op_array[] = [
                'intance_risk_op_id' => $instancesRisksOp['instances_risks_op_id'],
                'scale_op_id'	 => $scale['scale_id'],
                'brut_value'	 => -1,
                'net_value'	 => -1,
                'targeted_value' => -1,
                'creator'	 => 'Migration script',
                'created_at' => date('Y-m-d H:i:s')

              ];

            }
          }

        }

        $this->table('intances_risks_scales_op')->insert($intances_risks_scales_op_array)->save();

        // remove the temp column
        $this->table('scales_op')->removeColumn('old_impact_type')->update();

        //remove the now useless column of instances_risks_op
        $this->table('instances_risks_op')->removeColumn('brut_prob')
                                          ->removeColumn('brut_r')
                                          ->removeColumn('brut_o')
                                          ->removeColumn('brut_l')
                                          ->removeColumn('brut_f')
                                          ->removeColumn('brut_p')
                                          ->removeColumn('net_prob')
                                          ->removeColumn('net_r')
                                          ->removeColumn('net_o')
                                          ->removeColumn('net_l')
                                          ->removeColumn('net_f')
                                          ->removeColumn('net_p')
                                          ->removeColumn('targeted_prob')
                                          ->removeColumn('targeted_r')
                                          ->removeColumn('targeted_o')
                                          ->removeColumn('targeted_l')
                                          ->removeColumn('targeted_f')
                                          ->removeColumn('targeted_p')
                                          ->update();

    }
}
