<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Service;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Monarc\Core\Exception\ActionForbiddenException;
use Monarc\Core\Scenario\Table\CatalogTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Scenario\Table\ScenarioAnalysisTable;
use Monarc\FrontOffice\Scenario\Table\ScenarioTemplateSnapshotTable;

/** Authorised catalog-to-client copy; it never updates a catalog resource. */
final class ScenarioTemplateInstantiationService
{
    public function __construct(
        private CatalogTable $catalogTable,
        private ScenarioAnalysisTable $analysisTable,
        private ScenarioTemplateSnapshotTable $snapshotTable,
        private ScenarioAnalysisService $analysisService,
        private ConnectedUserService $connectedUserService
    ) {
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function instantiate(
        Anr $anr,
        array $data,
        ?int $revision,
        string $correlationId,
        ?User $actor = null
    ): ?array {
        $user = $actor ?? $this->user();
        $existing = $this->snapshotTable->find(
            (int) $anr->getId(),
            $data['templateUuid'],
            (int) $data['templateVersion']
        );
        if ($existing !== null) {
            return $this->existingResult($anr, $existing);
        }
        // Reading the shared published catalog is re-authorised for every new request.
        $catalog = $this->catalogTable->findPublishedTemplateSnapshot(
            $data['templateUuid'],
            (int) $data['templateVersion']
        );
        if ($catalog === null) {
            return null;
        }
        try {
            return $this->snapshotTable->transactional(function () use (
                $anr,
                $data,
                $revision,
                $correlationId,
                $catalog,
                $user
            ): ?array {
                $analysis = $this->analysisService->get($anr);
                if ($analysis === null) {
                    $translation = $catalog['translations'][0] ?? [];
                    $analysis = $this->analysisService->create($anr, [
                        'languageCode' => $translation['locale'] ?? 'en',
                    ], $correlationId, $user);
                } elseif ($revision === null || (int) $analysis['revision'] !== $revision) {
                    return null;
                }
                $analysisId = $this->analysisTable->idByAnr((int) $anr->getId());
                if ($analysisId === null) {
                    return null;
                }
                $snapshot = $this->snapshotTable->create(
                    (int) $anr->getId(),
                    $analysisId,
                    (int) $user->getId(),
                    $catalog
                );
                $this->analysisTable->auditTemplateSelection(
                    (int) $anr->getId(),
                    (int) $user->getId(),
                    $correlationId,
                    $analysis['uuid'],
                    ['templateUuid' => $data['templateUuid'], 'templateVersion' => (int) $data['templateVersion']]
                );

                return ['analysis' => $analysis, 'snapshot' => $snapshot];
            });
        } catch (UniqueConstraintViolationException) {
            $existing = $this->snapshotTable->find(
                (int) $anr->getId(),
                $data['templateUuid'],
                (int) $data['templateVersion']
            );

            return $existing === null ? null : $this->existingResult($anr, $existing);
        }
    }

    /** @return array<string, mixed>|null */
    public function getSnapshot(Anr $anr): ?array
    {
        $snapshot = $this->snapshotTable->findLatestByAnr((int) $anr->getId());
        if ($snapshot === null) {
            return null;
        }

        return $this->existingResult($anr, $snapshot);
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function saveOverride(
        Anr $anr,
        string $recordUuid,
        array $data,
        int $revision,
        string $correlationId
    ): ?array {
        $user = $this->user();
        $snapshot = $this->snapshotTable->findByUuid((int) $anr->getId(), $data['snapshotUuid']);
        $analysis = $this->analysisService->get($anr);
        $snapshotId = $this->snapshotTable->findIdByUuid((int) $anr->getId(), $data['snapshotUuid']);
        if ($snapshot === null || $snapshotId === null || $analysis === null) {
            return null;
        }
        $override = $this->snapshotTable->saveOverride(
            (int) $anr->getId(),
            (int) $user->getId(),
            $snapshotId,
            $recordUuid,
            $data['fieldName'],
            $data['value'],
            $revision
        );
        if ($override === null) {
            return null;
        }
        $this->analysisTable->auditTemplateOverride(
            (int) $anr->getId(),
            (int) $user->getId(),
            $correlationId,
            $analysis['uuid'],
            [
                'snapshotUuid' => $data['snapshotUuid'],
                'recordUuid' => $recordUuid,
                'fieldName' => $data['fieldName'],
                'revision' => $override['revision'],
            ]
        );

        return [
            'override' => $override,
            'snapshot' => $this->snapshotTable->findByUuid((int) $anr->getId(), $data['snapshotUuid']),
        ];
    }

    /** @param array<string, mixed> $snapshot @return array<string, mixed>|null */
    private function existingResult(Anr $anr, array $snapshot): ?array
    {
        $analysis = $this->analysisService->get($anr);

        return $analysis === null ? null : ['analysis' => $analysis, 'snapshot' => $snapshot];
    }

    private function user(): User
    {
        $user = $this->connectedUserService->getConnectedUser();
        if (!$user instanceof User) {
            throw new ActionForbiddenException('Scenario template instantiation is forbidden.');
        }

        return $user;
    }
}
