<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\InputFormatter\FormattedInputParams;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Table;

class AnrRolfRiskService
{
    private UserSuperClass $connectedUser;

    public function __construct(
        private Table\RolfRiskTable $rolfRiskTable,
        private Table\RolfTagTable $rolfTagTable,
        private Table\MeasureTable $measureTable,
        private Table\MonarcObjectTable $monarcObjectTable,
        private Table\ReferentialTable $referentialTable,
        private Table\InstanceRiskOpTable $instanceRiskOpTable,
        private AnrInstanceRiskOpService $anrInstanceRiskOpService,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getList(FormattedInputParams $formattedInputParams): array
    {
        $rolfRiskData = [];
        /** @var Entity\RolfRisk $rolfRisk */
        foreach ($this->rolfRiskTable->findByParams($formattedInputParams) as $rolfRisk) {
            $rolfRiskData[] = $this->prepareRolfRiskData($rolfRisk);
        }

        return $rolfRiskData;
    }

    public function getCount(FormattedInputParams $formattedInputParams): int
    {
        return $this->rolfRiskTable->countByParams($formattedInputParams);
    }

    public function getRolfRiskData(Entity\Anr $anr, int $id): array
    {
        /** @var Entity\RolfRisk $rolfRisk */
        $rolfRisk = $this->rolfRiskTable->findByIdAndAnr($id, $anr);

        return $this->prepareRolfRiskData($rolfRisk);
    }

    public function create(Entity\Anr $anr, array $data, bool $saveInDb = true): Entity\RolfRisk
    {
        /** @var Entity\RolfRisk $rolfRisk */
        $rolfRisk = (new Entity\RolfRisk())
            ->setAnr($anr)
            ->setCode($data['code'])
            ->setLabels($data)
            ->setDescriptions($data)
            ->setCreator($this->connectedUser->getEmail());

        if (!empty($data['measures'])) {
            /** @var Entity\Measure $measure */
            foreach ($this->measureTable->findByUuidsAndAnr($data['measures'], $anr) as $measure) {
                $rolfRisk->addMeasure($measure);
            }
        }
        if (!empty($data['tags'])) {
            /** @var Entity\RolfTag $tag */
            foreach ($this->rolfTagTable->findByIdsAndAnr($data['tags'], $anr) as $tag) {
                $rolfRisk->addTag($tag);
                /* Create operation instance risks for the linked rolf tag. */
                /** @var Entity\MonarcObject $monarcObject */
                foreach ($this->monarcObjectTable->findByAnrAndRolfTag($anr, $tag) as $monarcObject) {
                    foreach ($monarcObject->getInstances() as $instance) {
                        $this->anrInstanceRiskOpService->createInstanceRiskOpWithScales(
                            $instance,
                            $monarcObject,
                            $rolfRisk
                        );
                    }
                }
            }
        }

        $this->rolfRiskTable->save($rolfRisk, $saveInDb);

        return $rolfRisk;
    }

    public function createList(Entity\Anr $anr, array $data): array
    {
        $createdRowsNum = [];
        foreach ($data as $rowNum => $rowData) {
            $this->create($anr, $rowData, false);
            $createdRowsNum[] = $rowNum;
        }
        $this->rolfRiskTable->flush();

        return $createdRowsNum;
    }

    public function update(Entity\Anr $anr, int $id, array $data): Entity\RolfRisk
    {
        /** @var Entity\RolfRisk $rolfRisk */
        $rolfRisk = $this->rolfRiskTable->findByIdAndAnr($id, $anr);

        $rolfRisk->removeAllMeasures();
        if (!empty($data['measures'])) {
            /** @var Entity\Measure $measure */
            foreach ($this->measureTable->findByUuidsAndAnr($data['measures'], $anr) as $measure) {
                $rolfRisk->addMeasure($measure);
            }
        }

        $rolfTagIds = array_map('\intval', $data['tags']);
        foreach ($rolfRisk->getTags() as $rolfTag) {
            $rolfTagIdKey = \array_search($rolfTag->getId(), $rolfTagIds, true);
            if ($rolfTagIdKey !== false) {
                unset($rolfTagIds[$rolfTagIdKey]);
            } else {
                $rolfRisk->removeTag($rolfTag);
                /* Set the related operational risks to specific. */
                foreach ($this->monarcObjectTable->findByAnrAndRolfTag($anr, $rolfTag) as $monarcObject) {
                    $instancesRisksOp = $this->instanceRiskOpTable->findByObjectAndRolfRisk($monarcObject, $rolfRisk);
                    foreach ($instancesRisksOp as $instanceRiskOp) {
                        $this->instanceRiskOpTable->save($instanceRiskOp->setIsSpecific(true), false);
                    }
                }
            }
        }
        if (!empty($rolfTagIds)) {
            /** @var Entity\RolfTag $rolfTag */
            foreach ($this->rolfTagTable->findByIdsAndAnr($rolfTagIds, $anr) as $rolfTag) {
                $rolfRisk->addTag($rolfTag);
                /* Create operation instance risks for the linked rolf tag. */
                /** @var Entity\MonarcObject $monarcObject */
                foreach ($this->monarcObjectTable->findByAnrAndRolfTag($anr, $rolfTag) as $monarcObject) {
                    foreach ($monarcObject->getInstances() as $instance) {
                        $this->anrInstanceRiskOpService->createInstanceRiskOpWithScales(
                            $instance,
                            $monarcObject,
                            $rolfRisk
                        );
                    }
                }
            }
        }

        if ($rolfRisk->areLabelsDifferent($data) || $rolfRisk->areDescriptionsDifferent($data)) {
            $rolfRisk->setLabels($data)->setDescriptions($data);
            /* If the labels or descriptions changed the operational risks labels have to be updated as well. */
            foreach ($rolfRisk->getTags() as $rolfTag) {
                foreach ($this->monarcObjectTable->findByAnrAndRolfTag($anr, $rolfTag) as $monarcObject) {
                    $instancesRisksOp = $this->instanceRiskOpTable->findByObjectAndRolfRisk($monarcObject, $rolfRisk);
                    foreach ($instancesRisksOp as $instanceRiskOp) {
                        $instanceRiskOp->setRiskCacheCode($rolfRisk->getCode())
                            ->setRiskCacheLabels([
                                'riskCacheLabel1' => $rolfRisk->getLabel(1),
                                'riskCacheLabel2' => $rolfRisk->getLabel(2),
                                'riskCacheLabel3' => $rolfRisk->getLabel(3),
                                'riskCacheLabel4' => $rolfRisk->getLabel(4),
                            ])
                            ->setRiskCacheDescriptions([
                                'riskCacheDescription1' => $rolfRisk->getDescription(1),
                                'riskCacheDescription2' => $rolfRisk->getDescription(2),
                                'riskCacheDescription3' => $rolfRisk->getDescription(3),
                                'riskCacheDescription4' => $rolfRisk->getDescription(4),
                            ]);

                        $this->instanceRiskOpTable->save($instanceRiskOp, false);
                    }
                }
            }
        }

        $this->rolfRiskTable->save($rolfRisk->setCode($data['code'])->setUpdater($this->connectedUser->getEmail()));

        return $rolfRisk;
    }

    public function delete(Entity\Anr $anr, int $id): void
    {
        /** @var Entity\RolfRisk $rolfRisk */
        $rolfRisk = $this->rolfRiskTable->findByIdAndAnr($id, $anr);
        $this->removeRolfRisk($anr, $rolfRisk);
    }

    public function deleteList(Entity\Anr $anr, array $data): void
    {
        /** @var Entity\RolfRisk $rolfRisk */
        foreach ($this->rolfRiskTable->findByIdsAndAnr($data, $anr) as $rolfRisk) {
            $this->removeRolfRisk($anr, $rolfRisk, false);
        }
        $this->rolfRiskTable->flush();
    }

    /**
     * Performs the measures linking to the rolf risks when a new referential mapping is processed.
     */
    public function linkMeasuresToRisks(
        Entity\Anr $anr,
        string $sourceReferentialUuid,
        string $destinationReferentialUuid
    ): void {
        /** @var Entity\Referential $destinationReferential */
        $destinationReferential = $this->referentialTable->findByUuidAndAnr($destinationReferentialUuid, $anr);
        foreach ($destinationReferential->getMeasures() as $destinationMeasure) {
            foreach ($destinationMeasure->getLinkedMeasures() as $measureLink) {
                if ($measureLink->getReferential()->getUuid() === $sourceReferentialUuid) {
                    foreach ($measureLink->getRolfRisks() as $rolfRisk) {
                        $destinationMeasure->addRolfRisk($rolfRisk);
                    }
                    $this->measureTable->save($destinationMeasure, false);
                }
            }
        }
        $this->measureTable->flush();
    }

    private function removeRolfRisk(Entity\Anr $anr, Entity\RolfRisk $rolfRisk, bool $saveInDb = true): void
    {
        foreach ($this->instanceRiskOpTable->findByAnrAndRolfRisk($anr, $rolfRisk) as $instanceRiskOp) {
            $this->instanceRiskOpTable->save($instanceRiskOp->setIsSpecific(true), false);
        }

        $this->rolfRiskTable->remove($rolfRisk, $saveInDb);
    }

    private function prepareRolfRiskData(Entity\RolfRisk $rolfRisk): array
    {
        $tagsData = [];
        foreach ($rolfRisk->getTags() as $tag) {
            $tagsData[] = array_merge([
                'id' => $tag->getId(),
                'code' => $tag->getCode(),
            ], $tag->getLabels());
        }
        $measuresData = [];
        foreach ($rolfRisk->getMeasures() as $measure) {
            $measuresData[] = array_merge([
                'uuid' => $measure->getUuid(),
                'code' => $measure->getCode(),
                'referential' => [
                    'uuid' => $measure->getReferential()->getUuid(),
                ],
            ], $measure->getLabels());
        }

        return array_merge([
            'id' => $rolfRisk->getId(),
            'code' => $rolfRisk->getCode(),
            'tags' => $tagsData,
            'measures' => $measuresData,
        ], $rolfRisk->getLabels(), $rolfRisk->getDescriptions());
    }
}
