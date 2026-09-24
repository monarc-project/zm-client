<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Service;

use Monarc\Core\Scenario\Feature\ScenarioCapability;
use Monarc\Core\Scenario\Service\ScenarioAuthenticationService as ScenarioAuthenticationServiceContract;

/**
 * Office Scenario-session behaviour for the shared MONARC authentication flow.
 */
final class ScenarioAuthenticationService implements ScenarioAuthenticationServiceContract
{
    public function __construct(
        private LegacyBridgeService $legacyBridgeService,
        private ScenarioCapability $scenarioCapability
    ) {
    }

    public function revokeUserSessions(int $userId): void
    {
        if ($this->scenarioCapability->isEnabled()) {
            $this->legacyBridgeService->revokeUserSessions($userId);
        }
    }
}
