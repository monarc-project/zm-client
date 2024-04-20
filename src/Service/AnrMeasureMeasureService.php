<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Table;

class AnrMeasureMeasureService
{
    public function __construct(
        private Table\MeasureMeasureTable $measureMeasureTable,
        private Table\MeasureTable $measureTable
    ) {
    }

    public function getList(Entity\Anr $anr): array
    {
        $result = [];
        /** @var Entity\MeasureMeasure $measureMeasure */
        foreach ($this->measureMeasureTable->findByAnr($anr) as $measureMeasure) {
            $result[] = [
                'masterMeasure' => array_merge([
                    'uuid' => $measureMeasure->getMasterMeasure()->getUuid(),
                    'code' => $measureMeasure->getMasterMeasure()->getCode(),
                ], $measureMeasure->getMasterMeasure()->getLabels()),
                'linkedMeasure' => array_merge([
                    'uuid' => $measureMeasure->getLinkedMeasure()->getUuid(),
                    'code' => $measureMeasure->getLinkedMeasure()->getCode(),
                ], $measureMeasure->getLinkedMeasure()->getLabels()),
            ];
        }

        return $result;
    }

    public function create(Entity\Anr $anr, array $data, bool $saveInDb = true): Entity\Measure
    {
        if ($data['masterMeasureUuid'] === $data['linkedMeasureUuid']) {
            throw new Exception('It is not possible to link a control to itself', 412);
        }

        /** @var Entity\Measure $masterMeasure */
        $masterMeasure = $this->measureTable->findByUuidAndAnr($data['masterMeasureUuid'], $anr);
        /** @var Entity\Measure $linkedMeasure */
        $linkedMeasure = $this->measureTable->findByUuidAndAnr($data['linkedMeasureUuid'], $anr);

        $masterMeasure->addLinkedMeasure($linkedMeasure);
        $this->measureTable->save($linkedMeasure, $saveInDb);

        return $masterMeasure;
    }

    /**
     * @return string[]
     */
    public function createList(Entity\Anr $anr, array $data): array
    {
        $createdIds = [];
        foreach ($data as $rowData) {
            $createdIds[] = $this->create($anr, $rowData, false)->getUuid();
        }
        $this->measureMeasureTable->flush();

        return $createdIds;
    }

    public function delete(Entity\Anr $anr, string $masterMeasureUuid, string $linkedMeasureUuid): void
    {
        /** @var Entity\Measure $masterMeasure */
        $masterMeasure = $this->measureTable->findByUuidAndAnr($masterMeasureUuid, $anr);
        /** @var Entity\Measure $linkedMeasure */
        $linkedMeasure = $this->measureTable->findByUuidAndAnr($linkedMeasureUuid, $anr);
        $masterMeasure->removeLinkedMeasure($linkedMeasure);

        $this->measureTable->save($masterMeasure);
    }
}
