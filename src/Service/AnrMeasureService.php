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

class AnrMeasureService
{
    private UserSuperClass $connectedUser;

    public function __construct(
        private Table\MeasureTable $measureTable,
        private Table\ReferentialTable $referentialTable,
        private Table\SoaCategoryTable $soaCategoryTable,
        private SoaService $soaService,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getList(FormattedInputParams $params): array
    {
        $result = [];

        $includeLinks = $params->hasFilterFor('includeLinks') && $params->getFilterFor('includeLinks')['value'];
        /** @var Entity\Measure $measure */
        foreach ($this->measureTable->findByParams($params) as $measure) {
            $result[] = $this->prepareMeasureDataResult($measure, $includeLinks);
        }

        return $result;
    }

    public function getCount(FormattedInputParams $params): int
    {
        return $this->measureTable->countByParams($params);
    }

    public function getMeasureData(Entity\Anr $anr, string $uuid): array
    {
        /** @var Entity\Measure $measure */
        $measure = $this->measureTable->findByUuidAndAnr($uuid, $anr);

        return $this->prepareMeasureDataResult($measure);
    }

    public function create(Entity\Anr $anr, array $data, bool $saveInDb = true): Entity\Measure
    {
        /** @var Entity\Referential $referential */
        $referential = $this->referentialTable->findByUuidAndAnr($data['referentialUuid'], $anr);
        /** @var Entity\SoaCategory $soaCategory */
        $soaCategory = $this->soaCategoryTable->findByIdAndAnr($data['categoryId'], $anr);

        $measure = $this->createMeasureObject($anr, $referential, $soaCategory, $data, $saveInDb);

        $this->soaService->createSoaObject($anr, $measure);

        return $measure;
    }

    public function createMeasureObject(
        Entity\Anr $anr,
        Entity\Referential $referential,
        ?Entity\SoaCategory $soaCategory,
        array $data,
        bool $saveInDb = true
    ): Entity\Measure {
        /** @var Entity\Measure $measure */
        $measure = (new Entity\Measure())
            ->setAnr($anr)
            ->setCode($data['code'])
            ->setLabels($data)
            ->setReferential($referential)
            ->setCategory($soaCategory)
            ->setCreator($this->connectedUser->getEmail());
        if (!empty($data['uuid'])) {
            $measure->setUuid($data['uuid']);
        }

        $this->measureTable->save($measure, $saveInDb);

        return $measure;
    }

    public function createList(Entity\Anr $anr, array $data): array
    {
        $createdUuids = [];
        foreach ($data as $row) {
            $createdUuids[] = $this->create($anr, $row, false)->getUuid();
        }
        $this->measureTable->flush();

        return $createdUuids;
    }

    public function update(Entity\Anr $anr, string $uuid, array $data): Entity\Measure
    {
        /** @var Entity\Measure $measure */
        $measure = $this->measureTable->findByUuidAndAnr($uuid, $anr);
        $measure->setLabels($data)
            ->setCode($data['code'])
            ->setUpdater($this->connectedUser->getEmail());
        if ($measure->getCategory() === null || $measure->getCategory()->getId() !== $data['categoryId']) {
            $previousLinkedCategory = $measure->getCategory();
            /** @var Entity\SoaCategory $soaCategory */
            $soaCategory = $this->soaCategoryTable->findByIdAndAnr($data['categoryId'], $anr);
            $measure->setCategory($soaCategory);
            if ($previousLinkedCategory !== null && $previousLinkedCategory->getMeasures()->isEmpty()) {
                $this->soaCategoryTable->remove($previousLinkedCategory, false);
            }
        }

        $this->measureTable->save($measure);

        return $measure;
    }

    public function delete(Entity\Anr $anr, string $uuid): void
    {
        /** @var Entity\Measure $measure */
        $measure = $this->measureTable->findByUuidAndAnr($uuid, $anr);

        $this->processMeasureRemoval($measure);
    }

    public function deleteList(Entity\Anr $anr, array $data)
    {
        /** @var Entity\Measure[] $measures */
        $measures = $this->measureTable->findByUuidsAndAnr($data, $anr);

        foreach ($measures as $measure) {
            $this->processMeasureRemoval($measure, false);
        }
        $this->measureTable->flush();
    }

    private function processMeasureRemoval(Entity\Measure $measure, bool $saveInDb = true): void
    {
        $previousLinkedCategory = $measure->getCategory();
        $measure->setCategory(null);
        if ($previousLinkedCategory !== null && $previousLinkedCategory->getMeasures()->isEmpty()) {
            $this->soaCategoryTable->remove($previousLinkedCategory, false);
        }

        $this->measureTable->remove($measure, $saveInDb);
    }

    private function prepareMeasureDataResult(Entity\Measure $measure, bool $includeLinks = false): array
    {
        $linkedMeasures = [];
        if ($includeLinks) {
            /** @var Entity\Measure $linkedMeasure */
            foreach ($measure->getLinkedMeasures() as $linkedMeasure) {
                $linkedMeasures[] = array_merge([
                    'id' => $linkedMeasure->getId(),
                    'uuid' => $linkedMeasure->getUuid(),
                    'code' => $linkedMeasure->getCode(),
                ], $linkedMeasure->getLabels());
            }
        }

        return array_merge([
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
            'linkedMeasures' => $linkedMeasures,
        ], $measure->getLabels());
    }
}
