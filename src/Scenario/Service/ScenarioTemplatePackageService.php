<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Service;

use Monarc\Core\Exception\ActionForbiddenException;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Scenario\Table\ScenarioTemplateSnapshotTable;
use Monarc\FrontOffice\Table\UserAnrTable;

/** Read only: no shared source lookup and no writes to local evidence. */
final class ScenarioTemplatePackageService
{
    public function __construct(
        private ScenarioTemplateSnapshotTable $snapshots,
        private ConnectedUserService $users,
        private UserAnrTable $userAnrs,
        private \Monarc\FrontOffice\Service\AnrSupervisorService $supervisors
    ) {
    }

    public function read(Anr $anr): array
    {
        $user = $this->users->getConnectedUser();
        if (!$user instanceof User || ($this->userAnrs->findByAnrAndUser($anr, $user) === null
            && $this->supervisors->findLinkedSupervisor($anr, $user) === null)) {
            throw new ActionForbiddenException('Scenario template package access is forbidden.');
        }
        $snapshot = $this->snapshots->findLatestByAnr((int) $anr->getId());
        if ($snapshot === null) {
            return ['compatibility' => 'no_template', 'template' => null, 'links' => []];
        }

        return [
            'compatibility' => array_key_exists('knowledgeBasePackage', $snapshot['payload'])
                ? 'snapshot' : 'legacy_snapshot',
            'template' => [
                'snapshotUuid' => $snapshot['uuid'],
                'stableUuid' => $snapshot['catalogTemplateUuid'],
                'version' => $snapshot['catalogTemplateVersion'],
                'checksum' => $snapshot['catalogReleaseChecksum'],
                'provenance' => $snapshot['catalogProvenance'],
            ],
            'links' => array_map(static function (array $link): array {
                // Preserve historic snapshot payloads while projecting the current package contract.
                unset($link['intendedUse']);

                return $link;
            }, $snapshot['payload']['knowledgeBasePackage'] ?? []),
        ];
    }
}
