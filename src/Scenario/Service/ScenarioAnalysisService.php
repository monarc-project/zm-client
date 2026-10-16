<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Service;

use Closure;
use Monarc\Core\Exception\ActionForbiddenException;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Scenario\Table\ScenarioAnalysisTable;
use Monarc\FrontOffice\Table\AnrTable;
use Monarc\FrontOffice\Table\UserAnrTable;
use Monarc\FrontOffice\Table\UserTable;

/** Coordinates the Scenario-analysis metadata lifecycle within an authorised ANR. */
final class ScenarioAnalysisService
{
    public function __construct(
        private ScenarioAnalysisTable $scenarioAnalysisTable,
        private AnrTable $anrTable,
        private UserAnrTable $userAnrTable,
        private UserTable $userTable,
        private ConnectedUserService $connectedUserService,
        private Closure $findLinkedSupervisor
    ) {
    }

    /** @return array<string, mixed>|null */
    public function get(Anr $anr): ?array
    {
        return $this->withIdentity($anr, $this->scenarioAnalysisTable->findByAnr((int) $anr->getId()));
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function create(Anr $anr, array $data, string $correlationId, ?User $actor = null): array
    {
        $user = $actor ?? $this->connectedUser();
        $this->assertSourceAnrReadable($data, $user);

        return $this->withIdentity($anr, $this->scenarioAnalysisTable->create(
            (int) $anr->getId(),
            (int) $user->getId(),
            $data,
            $correlationId
        ));
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function update(
        Anr $anr,
        string $uuid,
        array $data,
        int $revision,
        string $correlationId,
        ?User $actor = null
    ): ?array {
        return $this->withIdentity($anr, $this->scenarioAnalysisTable->update(
            (int) $anr->getId(),
            $uuid,
            (int) ($actor ?? $this->connectedUser())->getId(),
            $data,
            $revision,
            $correlationId
        ));
    }

    /** @param array<string, mixed>|null $analysis @return array<string, mixed>|null */
    private function withIdentity(Anr $anr, ?array $analysis): ?array
    {
        if ($analysis === null) {
            return null;
        }
        $owner = $analysis['ownerId'] === null
            ? null
            : $this->userTable->findById((int) $analysis['ownerId'], false);
        $ownerName = $owner instanceof User
            ? trim($owner->getFirstname() . ' ' . $owner->getLastname())
            : '';
        $analysis['analysisIdentity'] = [
            'title' => $anr->getLabel(),
            'description' => $anr->getDescription(),
            'languageCode' => $anr->getLanguageCode(),
            'ownerId' => $analysis['ownerId'],
            'ownerName' => $ownerName !== '' ? $ownerName : null,
        ];

        return $analysis;
    }

    private function connectedUser(): User
    {
        $user = $this->connectedUserService->getConnectedUser();
        if (!$user instanceof User) {
            throw new ActionForbiddenException('Scenario analysis access is forbidden.');
        }

        return $user;
    }

    /** @param array<string, mixed> $data */
    private function assertSourceAnrReadable(array $data, User $user): void
    {
        if (!isset($data['sourceAnrId'])) {
            return;
        }

        $sourceAnr = $this->anrTable->findById((int) $data['sourceAnrId'], false);
        if (!$sourceAnr instanceof Anr) {
            throw new ActionForbiddenException('The selected source analysis is not accessible.');
        }

        $hasDirectAccess = $this->userAnrTable->findByAnrAndUser($sourceAnr, $user) !== null;
        $hasSupervisorAccess = ($this->findLinkedSupervisor)($sourceAnr, $user) !== null;
        if (!$hasDirectAccess && !$hasSupervisorAccess) {
            throw new ActionForbiddenException('The selected source analysis is not accessible.');
        }
    }
}
