<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Service;

use Monarc\Core\Scenario\Feature\ScenarioCapability;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Table\UserTable;
use Throwable;

/** Establishes the BFF subject before normal RBAC and ANR validation run. */
final class ScenarioDelegatedAuthenticationService
{
    public function __construct(
        private LegacyBridgeService $legacyBridgeService,
        private ScenarioCapability $capability,
        private ConnectedUserService $connectedUserService,
        private UserTable $userTable
    ) {
    }

    public function authenticate(?string $session, ?string $serviceToken, ?int $anrId): bool
    {
        if ($session === null || $serviceToken === null || !$this->capability->isEnabled()) {
            return false;
        }
        try {
            $resolved = $this->legacyBridgeService->resolveInternalSession($session, $serviceToken);
        } catch (Throwable) {
            return false;
        }
        if ($resolved === null || $resolved['office'] !== 'frontoffice') {
            return false;
        }
        if ($anrId !== null && (int) $resolved['anrId'] !== $anrId) {
            return false;
        }
        $user = $this->userTable->findById((int) $resolved['subject'], false);
        if (!$user instanceof User) {
            return false;
        }
        $this->connectedUserService->setConnectedUser($user);

        return true;
    }
}
