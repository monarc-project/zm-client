<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\InputFormatter\FormattedInputParams;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Soa;
use Monarc\FrontOffice\Entity\SoaScaleComment;
use Monarc\FrontOffice\Table\SoaScaleCommentTable;
use Monarc\FrontOffice\Table\SoaTable;

class SoaService
{
    public function __construct(
        private SoaTable $soaTable,
        private SoaScaleCommentTable $soaScaleCommentTable,
        private AnrInstanceRiskService $anrInstanceRiskService,
        private AnrInstanceRiskOpService $anrInstanceRiskOpService
    ) {
    }

    public function getList(FormattedInputParams $params): array
    {
        $result = [];
        /** @var Soa $soa */
        foreach ($this->soaTable->findByParams($params) as $soa) {
            $measure = $soa->getMeasure();
            $linkedMeasuresUuids = [];
            foreach ($measure->getLinkedMeasures() as $linkedMeasure) {
                $linkedMeasuresUuids[] = $linkedMeasure->getUuid();
            }
            $amvsData = [];
            $amvsUuids = [];
            foreach ($measure->getAmvs() as $amv) {
                $amvsUuids[] = $amv->getUuid();
            }
            if (!empty($amvsUuids)) {
                $amvsData = $this->anrInstanceRiskService->getInstanceRisks($soa->getAnr(), null, [
                    'amvs' => $amvsUuids,
                    'limit' => -1,
                    'order' => 'maxRisk',
                    'order_direction' => 'desc',
                ]);
            }
            $rolfRisksData = [];
            $rolfRisksIds = [];
            foreach ($measure->getRolfRisks() as $rolfRisk) {
                $rolfRisksIds[] = $rolfRisk->getId();
            }
            if (!empty($amvsUuids)) {
                $rolfRisksData = $this->anrInstanceRiskOpService->getOperationalRisks($measure->getAnr(), null, [
                    'rolfRisks' => $rolfRisksIds,
                    'limit' => -1,
                    'order' => 'cacheNetRisk',
                    'order_direction' => 'desc',
                ]);
            }

            $result[] = [
                'id' => $soa->getId(),
                'remarks' => $soa->getRemarks(),
                'evidences' => $soa->getEvidences(),
                'actions' => $soa->getActions(),
                'compliance' => $soa->getCompliance(),
                'EX' => $soa->getEx(),
                'LR' => $soa->getLr(),
                'CO' => $soa->getCo(),
                'BR' => $soa->getBr(),
                'BP' => $soa->getBp(),
                'RRA' => $soa->getRra(),
                'soaScaleComment' => $soa->getSoaScaleComment() === null ? null : [
                    'id' => $soa->getSoaScaleComment()->getId(),
                    'colour' => $soa->getSoaScaleComment()->getColour(),
                    'comment' => $soa->getSoaScaleComment()->getComment(),
                    'scaleIndex' => $soa->getSoaScaleComment()->getScaleIndex(),
                    'isHidden' => $soa->getSoaScaleComment()->isHidden(),
                ],
                'measure' => array_merge([
                    'id' => $measure->getId(),
                    'uuid' => $measure->getUuid(),
                    'referential' => array_merge([
                        'uuid' => $measure->getReferential()->getUuid(),
                    ], $measure->getReferential()->getLabels()),
                    'code' => $measure->getCode(),
                    'category' => $measure->getCategory() === null
                        ? []
                        : array_merge(['id' => $measure->getCategory()->getId()], $measure->getCategory()->getLabels()),
                    'status' => $measure->getStatus(),
                    'linkedMeasures' => $linkedMeasuresUuids,
                    'amvs' => $amvsData,
                    'rolfRisks' => $rolfRisksData,
                ], $measure->getLabels())
            ];
        }

        return $result;
    }

    public function getCount(FormattedInputParams $params): int
    {
        return $this->soaTable->countByParams($params);
    }

    public function patchSoa(Anr $anr, int $id, array $data, bool $saveInDb = true): Soa
    {
        /** @var Soa $soa */
        $soa = $this->soaTable->findByIdAndAnr($id, $anr);
        if (isset($data['remarks'])) {
            $soa->setRemarks($data['remarks']);
        }
        if (isset($data['evidences'])) {
            $soa->setEvidences($data['evidences']);
        }
        if (isset($data['actions'])) {
            $soa->setActions($data['actions']);
        }
        if (isset($data['EX'])) {
            $soa->setEx($data['EX']);
        }
        if (isset($data['LR'])) {
            $soa->setLr($data['LR']);
        }
        if (isset($data['CO'])) {
            $soa->setCo($data['CO']);
        }
        if (isset($data['BR'])) {
            $soa->setBr($data['BR']);
        }
        if (isset($data['BP'])) {
            $soa->setBp($data['BP']);
        }
        if (isset($data['RRA'])) {
            $soa->setRra($data['RRA']);
        }
        if (!empty($data['soaScaleComment']) && $soa->getSoaScaleComment() !== null
            && $soa->getSoaScaleComment()->getId() !== (int)$data['soaScaleComment']
        ) {
            /** @var SoaScaleComment $soaScaleComment */
            $soaScaleComment = $this->soaScaleCommentTable->findByIdAndAnr((int)$data['soaScaleComment'], $anr);
            $soa->setSoaScaleComment($soaScaleComment);
        }

        $this->soaTable->save($soa, $saveInDb);

        return $soa;
    }

    /**
     * @return int[]
     */
    public function patchList(Anr $anr, array $data): array
    {
        $updatedIds = [];
        foreach ($data as $row) {
            $id = $row['id'];
            if (\is_array($row['soaScaleComment'])) {
                $row['soaScaleComment'] = $row['soaScaleComment']['id'];
            }
            $updatedIds[] = $this->patchSoa($anr, $id, $row, false)->getId();
        }
        $this->soaTable->flush();

        return $updatedIds;
    }
}
