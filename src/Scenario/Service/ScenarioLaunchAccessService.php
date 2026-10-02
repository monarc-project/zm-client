<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Service;

use Monarc\Core\Scenario\Contract\AnalysisType;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Service\AnrSupervisorService;
use Monarc\FrontOffice\Table\AnrTable;
use Monarc\FrontOffice\Table\UserAnrTable;

/**
 * Applies the same Scenario-analysis read rule to the legacy-to-Scenario UI
 * handoff as the API middleware: explicit ANR access or an active linked
 * supervisor role.
 */
final class ScenarioLaunchAccessService
{
    public function __construct(
        private AnrTable $anrTable,
        private UserAnrTable $userAnrTable,
        private AnrSupervisorService $anrSupervisorService
    ) {
    }

    public function canOpen(User $user, int $anrId): bool
    {
        $anr = $this->anrTable->findById($anrId, false);
        if (!$anr instanceof Anr || $anr->getAnalysisType() !== AnalysisType::SCENARIO) {
            return false;
        }

        return $this->userAnrTable->findByAnrAndUser($anr, $user) !== null
            || $this->anrSupervisorService->findLinkedSupervisor($anr, $user) !== null;
    }
}
