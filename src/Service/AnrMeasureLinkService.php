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

class AnrMeasureLinkService
{
    public function __construct(private Table\MeasureTable $measureTable)
    {
    }

    public function getList(Entity\Anr $anr): array
    {
        $result = [];
        /** @var Entity\Measure $masterMeasure */
        foreach ($this->measureTable->findByAnr($anr) as $masterMeasure) {
            foreach ($masterMeasure->getLinkedMeasures() as $linkedMeasure) {
                $result[] = [
                    'masterMeasure' => array_merge([
                        'uuid' => $masterMeasure->getUuid(),
                        'code' => $masterMeasure->getCode(),
                    ], $masterMeasure->getLabels()),
                    'linkedMeasure' => array_merge([
                        'uuid' => $linkedMeasure->getUuid(),
                        'code' => $linkedMeasure->getCode(),
                    ], $linkedMeasure->getLabels()),
                ];
            }
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
        $this->measureTable->flush();

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
