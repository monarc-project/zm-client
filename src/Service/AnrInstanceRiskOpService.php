<?php

namespace Monarc\FrontOffice\Service;

use Doctrine\DBAL\Connection;
use Monarc\Core\Model\Entity\InstanceRiskOpSuperClass;
use Monarc\Core\Model\Entity\InstanceRiskSuperClass;
use Monarc\Core\Service\InstanceRiskOpService;
use Monarc\FrontOffice\Model\Table\RecommandationTable;

class AnrInstanceRiskOpService extends InstanceRiskOpService
{
    /**
     * Updates recommendation operational risks positions.
     */
    public function updateRecoRisksOp(InstanceRiskOpSuperClass $instanceRiskOp): void
    {
        /** @var RecommandationTable $recommendationTable */
        $recommendationTable = $this->get('recommandationTable');
        /** @var Connection $instanceRiskOpTableConnection */
        $instanceRiskOpTableConnection = $this->get('table')->getDb()->getEntityManager()->getConnection();

        switch ($instanceRiskOp->get('kindOfMeasure')) {
            case InstanceRiskOpSuperClass::KIND_REDUCTION:
            case InstanceRiskOpSuperClass::KIND_REFUS:
            case InstanceRiskOpSuperClass::KIND_ACCEPTATION:
            case InstanceRiskOpSuperClass::KIND_PARTAGE:
                $sql = 'SELECT recommandation_id
                        FROM recommandations_risks
                        WHERE instance_risk_op_id = :id
                        GROUP BY recommandation_id';
                $res = $instanceRiskOpTableConnection->fetchAll($sql, [':id' => $instanceRiskOp->get('id')]);
                $ids = [];
                foreach ($res as $r) {
                    $ids[$r['recommandation_id']] = $r['recommandation_id'];
                }
                $recos = $recommendationTable->getEntityByFields(
                    ['anr' => $instanceRiskOp->get('anr')->get('id')],
                    ['position' => 'ASC', 'importance' => 'DESC', 'code' => 'ASC']
                );
                $i = 0;
                $hasSave = false;
                foreach ($recos as $r) {
                    if ((int)$r->get('position') <= 0 && isset($ids[$r->get('uuid')])) {
                        $i++;
                        $r->set('position', $i);
                        $recommendationTable->save($r, false);
                        $hasSave = true;
                    } elseif ($i > 0 && $r->get('position') > 0) {
                        $r->set('position', $r->get('position') + $i);
                        $recommendationTable->save($r, false);
                        $hasSave = true;
                    }
                }
                if ($hasSave && !empty($r)) {
                    $recommendationTable->save($r);
                }
                break;

            case InstanceRiskOpSuperClass::KIND_NOT_TREATED:
            default:
                $sql = 'SELECT rr.recommandation_id
                        FROM recommandations_risks rr
                        LEFT JOIN instances_risks ir
                        ON ir.id = rr.instance_risk_id
                        LEFT JOIN instances_risks_op iro
                        ON iro.id = rr.instance_risk_op_id
                        AND rr.instance_risk_op_id != :id
                        WHERE ((ir.kind_of_measure IS NOT NULL AND ir.kind_of_measure < ' . InstanceRiskSuperClass::KIND_NOT_TREATED . ')
                            OR (iro.kind_of_measure IS NOT NULL AND iro.kind_of_measure < ' . InstanceRiskOpSuperClass::KIND_NOT_TREATED . '))
                        AND (rr.instance_risk_op_id IS NOT NULL OR rr.instance_risk_id IS NOT NULL)
                        AND rr.anr_id = :anr
                        GROUP BY rr.recommandation_id';
                $res = $instanceRiskOpTableConnection->fetchAll($sql, [
                    ':anr' => $instanceRiskOp->get('anr')->get('id'),
                    ':id' => $instanceRiskOp->get('id')
                ]);
                $ids = [];
                foreach ($res as $r) {
                    $ids[$r['recommandation_id']] = $r['recommandation_id'];
                }
                $recos = $recommendationTable->getEntityByFields([
                    'anr' => $instanceRiskOp->get('anr')->get('id'),
                    'position' => ['op' => 'IS NOT', 'value' => null]
                ], ['position' => 'ASC']);
                $i = 0;
                $hasSave = false;
                foreach ($recos as $r) {
                    if ($r->get('position') > 0 && !isset($ids[$r->get('uuid')])) {
                        $i++;
                        $r->set('position', null);
                        $recommendationTable->save($r, false);
                        $hasSave = true;
                    } elseif ($i > 0 && $r->get('position') > 0) {
                        $r->set('position', $r->get('position') - $i);
                        $recommendationTable->save($r, false);
                        $hasSave = true;
                    }
                }
                if ($hasSave && !empty($r)) {
                    $recommendationTable->save($r);
                }
                break;
        }
    }
}
