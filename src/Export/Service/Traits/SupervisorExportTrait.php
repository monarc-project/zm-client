<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Service\Traits;

use Monarc\FrontOffice\Entity;

trait SupervisorExportTrait
{
    private function prepareSupervisorIdentity(?Entity\AnrSupervisor $supervisor): ?array
    {
        if ($supervisor === null) {
            return null;
        }

        return [
            'name' => $supervisor->getName(),
            'email' => $supervisor->getEmail(),
        ];
    }

    private function prepareLegacyRiskOwnerName(?Entity\AnrSupervisor $supervisor): ?string
    {
        if ($supervisor === null) {
            return null;
        }

        return $supervisor->hasRole(Entity\AnrSupervisorRole::ROLE_RISK_OWNER) ? $supervisor->getName() : null;
    }

    private function prepareResidualRiskAcceptanceData(
        ?string $decision,
        ?Entity\AnrSupervisor $approverSupervisor,
        ?\DateTimeInterface $decidedAt,
        ?string $performedByName,
        ?string $performedByEmail,
        bool $performedOnBehalf,
        ?string $justification
    ): ?array {
        if ($decision === null
            && $approverSupervisor === null
            && $decidedAt === null
            && $performedByName === null
            && $performedByEmail === null
            && $justification === null
        ) {
            return null;
        }

        return [
            'decision' => $decision,
            'approver' => $this->prepareSupervisorIdentity($approverSupervisor),
            'date' => $decidedAt?->format('Y-m-d'),
            'performedByName' => $performedByName,
            'performedByEmail' => $performedByEmail,
            'performedOnBehalf' => $performedOnBehalf,
            'justification' => $justification,
        ];
    }
}
