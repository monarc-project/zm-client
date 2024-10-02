<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\InstanceRisk;
use Monarc\FrontOffice\Entity\InstanceRiskOp;
use Monarc\FrontOffice\Entity\InstanceRiskOwner;
use Monarc\FrontOffice\Table\InstanceRiskOwnerTable;

class InstanceRiskOwnerService
{
    private UserSuperClass $connectedUser;

    private array $cachedData = [];

    public function __construct(
        private InstanceRiskOwnerTable $instanceRiskOwnerTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function create(Anr $anr, string $ownerName, bool $saveIdDb = false): InstanceRiskOwner
    {
        $instanceRiskOwner = (new InstanceRiskOwner())
            ->setAnr($anr)
            ->setName($ownerName)
            ->setCreator($this->connectedUser->getEmail());

        $this->instanceRiskOwnerTable->save($instanceRiskOwner, $saveIdDb);

        return $instanceRiskOwner;
    }

    public function getOrCreateInstanceRiskOwner(Anr $sourceAnr, Anr $anr, string $ownerName): InstanceRiskOwner
    {
        if (!isset($this->cachedData['isInstanceRiskOwnersCacheLoaded'])) {
            $this->cachedData['isInstanceRiskOwnersCacheLoaded'] = true;
            /** @var InstanceRiskOwner $instanceRiskOwner */
            foreach ($this->instanceRiskOwnerTable->findByAnr($sourceAnr) as $instanceRiskOwner) {
                $this->cachedData['instanceRiskOwners'][$instanceRiskOwner->getName()] = $instanceRiskOwner;
            }
        }
        if (!isset($this->cachedData['instanceRiskOwners'][$ownerName])) {
            $this->cachedData['instanceRiskOwners'][$ownerName] = $this->create($anr, $ownerName);
        }

        return $this->cachedData['instanceRiskOwners'][$ownerName];
    }

    public function processRiskOwnerNameAndAssign(string $ownerName, InstanceRisk|InstanceRiskOp $instanceRisk): void
    {
        if (empty($ownerName)) {
            $instanceRisk->setInstanceRiskOwner(null);
        } else {
            /** @var Anr $anr */
            $anr = $instanceRisk->getAnr();
            $instanceRiskOwner = $this->getOrCreateInstanceRiskOwner($anr, $anr, $ownerName);
            $instanceRisk->setInstanceRiskOwner($instanceRiskOwner);
        }
    }

    public function getList(Anr $anr, array $params = []): array
    {
        $result = [];
        foreach ($this->instanceRiskOwnerTable->findByAnrAndFilterParams($anr, $params) as $instanceRiskOwner) {
            $result[] = [
                'id' => $instanceRiskOwner->getId(),
                'name' => $instanceRiskOwner->getName(),
            ];
        }

        return $result;
    }
}
