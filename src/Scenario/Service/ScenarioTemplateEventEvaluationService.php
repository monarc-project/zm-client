<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Service;

use Monarc\Core\Exception\ActionForbiddenException;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Scenario\Table\ScenarioAnalysisTable;
use Monarc\FrontOffice\Scenario\Table\ScenarioTemplateEventEvaluationTable;
use Monarc\FrontOffice\Scenario\Table\ScenarioTemplateModelRiskScenarioDerivationTable;
use Monarc\FrontOffice\Scenario\Table\ScenarioTemplateSnapshotTable;
use Monarc\FrontOffice\Table\UserAnrTable;
use RuntimeException;

/** Coordinates non-occurrence Context Establishment evaluation evidence. */
final class ScenarioTemplateEventEvaluationService
{
    public function __construct(
        private ScenarioTemplateSnapshotTable $snapshots,
        private ScenarioTemplateEventEvaluationTable $evaluations,
        private ScenarioTemplateModelRiskScenarioDerivationTable $derivations,
        private ScenarioAnalysisTable $analyses,
        private ConnectedUserService $users,
        private UserAnrTable $userAnrTable
    ) {
    }

    /** @return array<string, mixed> */
    public function list(Anr $anr): array
    {
        $canEdit = $this->canEdit($anr);
        $snapshot = $this->snapshots->findLatestByAnr((int) $anr->getId());
        if ($snapshot === null) {
            return [
                'compatibility' => 'no_template',
                'canEdit' => $canEdit,
                'template' => null,
                'groups' => [],
            ];
        }
        $snapshotId = $this->snapshots->findIdByUuid((int) $anr->getId(), $snapshot['uuid']);
        if ($snapshotId === null) {
            return ['compatibility' => 'legacy_snapshot', 'canEdit' => false, 'template' => null, 'groups' => []];
        }
        $hasModels = false;
        $hasEvents = false;
        foreach ($snapshot['records'] as $record) {
            $hasModels = $hasModels || $record['type'] === 'template-risk-scenario-model';
            $hasEvents = $hasEvents || $record['type'] === 'template-model-event';
        }
        if (!$hasModels) {
            return [
                'compatibility' => array_key_exists('risk_scenario_models', $snapshot['payload'])
                    ? 'no_models'
                    : 'legacy_snapshot',
                'canEdit' => $canEdit,
                'template' => $this->templateProvenance($snapshot),
                'groups' => [],
            ];
        }
        $groups = $this->evaluations->grouped((int) $anr->getId(), $snapshotId, $snapshot);
        // A reader may inspect Context Establishment evidence but must not be
        // able to infer whether a writer has created local drafts from it.
        if ($canEdit) {
            $derivations = [];
            foreach ($this->derivations->listBySnapshot((int) $anr->getId(), $snapshotId) as $derivation) {
                $derivations[$derivation['modelRecordUuid']][] = $derivation;
            }
            foreach ($groups as &$group) {
                $group['existingDerivations'] = $derivations[$group['modelRecordUuid']] ?? [];
            }
            unset($group);
        }

        return [
            'compatibility' => $hasEvents ? 'modeled' : 'no_events',
            'canEdit' => $canEdit,
            'template' => $this->templateProvenance($snapshot),
            'groups' => $groups,
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function save(
        Anr $anr,
        string $eventRecordUuid,
        array $data,
        int $revision,
        string $correlationId
    ): array {
        $user = $this->writer($anr);
        $snapshot = $this->snapshots->findLatestByAnr((int) $anr->getId());
        if ($snapshot === null) {
            throw new RuntimeException('No template has been selected for this analysis.');
        }
        $snapshotId = $this->snapshots->findIdByUuid((int) $anr->getId(), $snapshot['uuid']);
        if ($snapshotId === null || !$this->evaluations->containsEventRecord(
            (int) $anr->getId(),
            $snapshotId,
            $eventRecordUuid
        )) {
            throw new RuntimeException('Template event evaluation was not found.');
        }
        $result = $this->evaluations->save(
            (int) $anr->getId(),
            $snapshotId,
            $eventRecordUuid,
            $data['state'],
            $data['comment'] ?? null,
            $revision,
            (int) $user->getId()
        );
        if ($result === null) {
            throw new RuntimeException('Template event evaluation revision conflict.');
        }
        $this->analyses->auditTemplateEventEvaluation(
            (int) $anr->getId(),
            (int) $user->getId(),
            $correlationId,
            $eventRecordUuid,
            [
                'snapshotUuid' => $snapshot['uuid'],
                'state' => $result['state'],
                'comment' => $result['comment'],
                'revision' => $result['revision'],
            ]
        );

        return $this->list($anr);
    }

    private function canEdit(Anr $anr): bool
    {
        $user = $this->users->getConnectedUser();
        $link = $user instanceof User ? $this->userAnrTable->findByAnrAndUser($anr, $user) : null;

        return $link !== null && $link->hasWriteAccess();
    }

    private function writer(Anr $anr): User
    {
        $user = $this->users->getConnectedUser();
        if (!$user instanceof User || !$this->canEdit($anr)) {
            throw new ActionForbiddenException('Scenario event evaluation is read only.');
        }

        return $user;
    }

    /** @param array<string, mixed> $snapshot @return array<string, mixed> */
    private function templateProvenance(array $snapshot): array
    {
        $template = [];
        foreach ($snapshot['records'] as $record) {
            if ($record['type'] === 'template') {
                $template = $record['content'];
                break;
            }
        }
        $translations = is_array($template['translations'] ?? null) ? $template['translations'] : [];
        $translation = null;
        foreach ($translations as $candidate) {
            if (is_array($candidate) && ($candidate['locale'] ?? null) === 'en') {
                $translation = $candidate;
                break;
            }
            if ($translation === null && is_array($candidate)) {
                $translation = $candidate;
            }
        }

        return [
            'snapshotUuid' => $snapshot['uuid'],
            'stableUuid' => $snapshot['catalogTemplateUuid'],
            'version' => $snapshot['catalogTemplateVersion'],
            'title' => $translation['title'] ?? 'Selected template',
        ];
    }
}
