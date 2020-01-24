<?php

namespace Monarc\FrontOffice\Service;

use Doctrine\DBAL\Connection;
use Monarc\Core\Model\Entity\InstanceRiskOpSuperClass;
use Monarc\Core\Model\Entity\InstanceRiskSuperClass;
use Monarc\Core\Service\InstanceRiskService;
use Monarc\FrontOffice\Model\Table\InstanceRiskTable;
use Monarc\FrontOffice\Model\Table\RecommandationTable;

class AnrInstanceRiskService extends InstanceRiskService
{
    /**
     * TODO: Check how we can call the method only once instead of multi calls during the instance risk update.
     *
     * Update recommendation risk positions.
     */
    public function updateRecoRisks(InstanceRiskSuperClass $instanceRisk): void
    {
        /** @var RecommandationTable $recommendationTable */
        $recommendationTable = $this->get('recommandationTable');
        /** @var Connection $instanceRiskOpTableConnection */
        $instanceRiskTableConnection = $this->get('table')->getDb()->getEntityManager()->getConnection();

        switch ($instanceRisk->get('kindOfMeasure')) {
            case InstanceRiskSuperClass::KIND_REDUCTION:
            case InstanceRiskSuperClass::KIND_REFUS:
            case InstanceRiskSuperClass::KIND_ACCEPTATION:
            case InstanceRiskSuperClass::KIND_PARTAGE:
                $sql = 'SELECT recommandation_id
                        FROM recommandations_risks
                        WHERE instance_risk_id = :id
                        GROUP BY recommandation_id';
                $res = $instanceRiskTableConnection->fetchAll($sql, [':id' => $instanceRisk->get('id')]);
                $ids = [];
                foreach ($res as $r) {
                    $ids[$r['recommandation_id']] = $r['recommandation_id'];
                }
                $recos = $recommendationTable->getEntityByFields(
                    ['anr' => $instanceRisk->get('anr')->get('id')],
                    ['position' => 'ASC', 'importance' => 'DESC', 'code' => 'ASC']
                );
                $i = 0;
                $hasSave = false;
                foreach ($recos as $r) {
                    if ((int)$r->get('position') <= 0 && isset($ids[(string)$r->get('uuid')])) {
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

            case InstanceRiskSuperClass::KIND_NOT_TREATED:
            default:
                $sql = 'SELECT rr.recommandation_id
                        FROM recommandations_risks rr
                        LEFT JOIN instances_risks ir
                        ON ir.id = rr.instance_risk_id
                        AND rr.instance_risk_id != :id
                        LEFT JOIN instances_risks_op iro
                        ON iro.id = rr.instance_risk_op_id
                        WHERE ((ir.kind_of_measure IS NOT NULL AND ir.kind_of_measure < ' . InstanceRiskSuperClass::KIND_NOT_TREATED . ')
                            OR (iro.kind_of_measure IS NOT NULL AND iro.kind_of_measure < ' . InstanceRiskOpSuperClass::KIND_NOT_TREATED . '))
                        AND (rr.instance_risk_op_id IS NOT NULL OR rr.instance_risk_id IS NOT NULL)
                        AND rr.anr_id = :anr
                        GROUP BY rr.recommandation_id';
                $res = $instanceRiskTableConnection->fetchAll($sql, [
                    ':anr' => $instanceRisk->get('anr')->get('id'),
                    ':id' => $instanceRisk->get('id')
                ]);
                $ids = [];
                foreach ($res as $r) {
                    $ids[$r['recommandation_id']] = $r['recommandation_id'];
                }
                $recos = $recommendationTable->getEntityByFields([
                    'anr' => $instanceRisk->getAnr()->getId(),
                    'position' => ['op' => 'IS NOT', 'value' => null]
                ], ['position' => 'ASC']);
                $i = 0;
                $hasSave = false;
                foreach ($recos as $r) {
                    if ($r->get('position') > 0 && !isset($ids[(string)$r->get('uuid')])) {
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

    public function updateFromRiskTable(int $instanceRiskId, array $data)
    {
        /** @var InstanceRiskTable $instanceRiskTable */
        $instanceRiskTable = $this->get('table');
        $instanceRisk = $instanceRiskTable->getEntity($instanceRiskId);

        //security
        $data['specific'] = $instanceRisk->get('specific');

        if ($instanceRisk->threatRate != $data['threatRate']) {
            $data['mh'] = 0;
        }

        return $this->update($instanceRiskId, $data);
    }
}
