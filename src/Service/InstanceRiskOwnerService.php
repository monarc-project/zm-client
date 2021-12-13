<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2021 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskOwnerTable;

class InstanceRiskOwnerService
{
    private AnrTable $anrTable;

    private InstanceRiskOwnerTable $instanceRiskOwnerTable;

    public function __construct(AnrTable $anrTable, InstanceRiskOwnerTable $instanceRiskOwnerTable)
    {
        $this->anrTable = $anrTable;
        $this->instanceRiskOwnerTable = $instanceRiskOwnerTable;
    }

    public function getList(int $anrId, array $params = []): array
    {
        $anr = $this->anrTable->findById($anrId);
        $result = [];
        foreach ($this->instanceRiskOwnerTable->findByAnrAndFilterParams($anr, $params) as $instanceRiskOwner) {
            $result[] = [
                'id' => $instanceRiskOwner->getId(),
                'name' => $instanceRiskOwner->getName(),
                'numberOfInstancesRisks' => count($instanceRiskOwner->getInstanceRisks()),
                'numberOfOperationalInstancesRisks' => count($instanceRiskOwner->getOperationalInstanceRisks()),
            ];
        }

        return $result;
    }

    public function updateOwner(int $id, array $data): int
    {
        $anr = $this->anrTable->findById($data['anr']);
        if (!empty($data['name'])) {
            $instanceRiskOwner = $this->instanceRiskOwnerTable->findByAnrAndId($anr, $id);
            $instanceRiskOwner->setName($data['name']);
            $this->instanceRiskOwnerTable->save($instanceRiskOwner);
        }

        return $instanceRiskOwner->getId();
    }

    public function deleteOwner($id):void
    {
        $ownerToDelete = $this->instanceRiskOwnerTable->findById($id);
        if ($ownerToDelete === null) {
            throw new EntityNotFoundException(sprintf('Owner with ID %d is not found', $id));
        }
        $this->instanceRiskOwnerTable->remove($ownerToDelete);
    }
}
