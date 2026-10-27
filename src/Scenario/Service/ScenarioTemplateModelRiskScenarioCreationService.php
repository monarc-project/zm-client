<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Service;

use Monarc\Core\Exception\ActionForbiddenException;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Scenario\Exception\ScenarioRiskScenarioException;
use Monarc\FrontOffice\Scenario\Table\ScenarioAnalysisTable;
use Monarc\FrontOffice\Scenario\Table\ScenarioRiskScenarioTable;
use Monarc\FrontOffice\Scenario\Table\ScenarioTemplateEventEvaluationTable;
use Monarc\FrontOffice\Scenario\Table\ScenarioTemplateModelRiskScenarioDerivationTable;
use Monarc\FrontOffice\Scenario\Table\ScenarioTemplateSnapshotTable;
use Monarc\FrontOffice\Table\UserAnrTable;
use RuntimeException;

/** Creates independent local drafts only after an authorised, explicit model selection. */
final class ScenarioTemplateModelRiskScenarioCreationService
{
    public function __construct(
        private ScenarioTemplateSnapshotTable $snapshots,
        private ScenarioTemplateEventEvaluationTable $evaluations,
        private ScenarioTemplateModelRiskScenarioDerivationTable $derivations,
        private ScenarioRiskScenarioTable $riskScenarios,
        private ScenarioAnalysisTable $analyses,
        private ConnectedUserService $users,
        private UserAnrTable $userAnrTable
    ) {
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function create(Anr $anr, array $data, int $ifMatchRevision, string $correlationId): array
    {
        $user = $this->writer($anr);
        $anrId = (int) $anr->getId();
        $requestChecksum = $this->checksum($data);

        return $this->snapshots->transactional(function () use (
            $anrId,
            $user,
            $data,
            $requestChecksum,
            $correlationId,
            $ifMatchRevision
        ): array {
            $existing = $this->derivations->findRequest($anrId, $data['idempotencyKey']);
            if ($existing !== null) {
                if (!hash_equals((string) $existing['request_checksum'], $requestChecksum)) {
                    throw new ScenarioRiskScenarioException(
                        'idempotency_conflict',
                        'Use a new submission to create a different set of local risk scenarios.'
                    );
                }

                return [
                    'replayed' => true,
                    'items' => $this->derivations->listByRequest((int) $existing['id']),
                ];
            }
            $snapshot = $this->snapshots->findLatestByAnr($anrId);
            if ($snapshot === null || $ifMatchRevision !== (int) $data['sourceRevision']) {
                throw new ScenarioRiskScenarioException(
                    $snapshot === null ? 'unavailable_template' : 'revision_conflict',
                    $snapshot === null
                        ? 'No template model is available for this analysis.'
                        : 'Reload the template model before creating local risk scenarios.'
                );
            }
            $snapshotId = $this->snapshots->findIdByUuid($anrId, $snapshot['uuid']);
            $analysisId = $this->analyses->idByAnr($anrId);
            if ($snapshotId === null || $analysisId === null) {
                throw new ScenarioRiskScenarioException('unavailable_template', 'No template model is available for this analysis.');
            }
            $plan = $this->plan($anrId, $snapshotId, $snapshot, $data);

            $request = $this->derivations->createRequest(
                $anrId,
                $snapshotId,
                $data['idempotencyKey'],
                $requestChecksum,
                (int) $data['sourceRevision'],
                (int) $user->getId(),
                $correlationId
            );
            foreach ($plan as $model) {
                $riskScenario = $this->riskScenarios->createScenario(
                    $anrId,
                    $analysisId,
                    (int) $user->getId(),
                    [
                        'title' => $model['localTitle'],
                        'description' => $model['localDescription'],
                        'sourceSnapshotUuid' => $snapshot['uuid'],
                        'sourceInstanceRecordUuid' => $model['modelRecordUuid'],
                    ],
                    $correlationId
                );
                $derivation = $this->derivations->createModelDerivation(
                    $anrId,
                    $snapshotId,
                    $request['id'],
                    $model['modelRecordUuid'],
                    $riskScenario['uuid'],
                    $this->modelSourcePayload($snapshot, $model),
                    (int) $user->getId()
                );
                $riskScenarioRevision = (int) $riskScenario['revision'];
                foreach ($model['events'] as $event) {
                    $localEvent = $this->riskScenarios->createNode(
                        $anrId,
                        $riskScenario['uuid'],
                        'event',
                        (int) $user->getId(),
                        [
                            'title' => $event['eventTitle'],
                            'description' => $event['guidance'],
                        ],
                        $riskScenarioRevision,
                        $correlationId
                    );
                    if ($localEvent === null) {
                        throw new RuntimeException('The local event could not be created.');
                    }
                    $riskScenarioRevision = (int) $localEvent['riskScenarioRevision'];
                    $this->derivations->createEventDerivation(
                        $anrId,
                        $derivation['id'],
                        $event['eventRecordUuid'],
                        $localEvent['uuid'],
                        $this->eventSourcePayload($event)
                    );
                }
                $this->analyses->auditTemplateModelRiskScenarioCreation(
                    $anrId,
                    (int) $user->getId(),
                    $correlationId,
                    $riskScenario['uuid'],
                    [
                        'templateSnapshotUuid' => $snapshot['uuid'],
                        'templateStableUuid' => $snapshot['catalogTemplateUuid'],
                        'templateVersion' => $snapshot['catalogTemplateVersion'],
                        'templateChecksum' => $snapshot['catalogReleaseChecksum'],
                        'modelRecordUuid' => $model['modelRecordUuid'],
                        'modelStableUuid' => $model['modelStableUuid'],
                        'sourceRevision' => $model['sourceRevision'],
                        'selectedEventRecordUuids' => array_column($model['events'], 'eventRecordUuid'),
                        'idempotencyKey' => $data['idempotencyKey'],
                        'creationRequestUuid' => $request['uuid'],
                    ]
                );
            }

            return ['replayed' => false, 'items' => $this->derivations->listByRequest($request['id'])];
        });
    }

    /** @param array<string, mixed> $snapshot @param array<string, mixed> $data @return array<int, array<string, mixed>> */
    private function plan(int $anrId, int $snapshotId, array $snapshot, array $data): array
    {
        $groups = $this->evaluations->grouped($anrId, $snapshotId, $snapshot);
        $byModel = [];
        foreach ($groups as $group) {
            $byModel[$group['modelRecordUuid']] = $group;
        }
        $plan = [];
        foreach ($data['models'] as $selected) {
            $group = $byModel[$selected['modelRecordUuid']] ?? null;
            if ($group === null || (int) $group['sourceRevision'] !== (int) $selected['sourceRevision']) {
                throw new ScenarioRiskScenarioException(
                    'unavailable_template',
                    'One or more selected template models are no longer available in this analysis snapshot.'
                );
            }
            $events = [];
            $available = [];
            foreach ($group['events'] as $event) {
                $available[$event['eventRecordUuid']] = $event;
            }
            foreach ($selected['eventRecordUuids'] as $eventRecordUuid) {
                if (!isset($available[$eventRecordUuid])) {
                    throw new ScenarioRiskScenarioException(
                        'unavailable_template',
                        'A selected template event is not part of its strategic model.'
                    );
                }
                $events[] = $available[$eventRecordUuid];
            }
            $plan[] = [
                'modelRecordUuid' => $group['modelRecordUuid'],
                'modelStableUuid' => $group['modelStableUuid'],
                'modelTitle' => $group['title'],
                'modelDescription' => $group['description'],
                'sourceRevision' => $group['sourceRevision'],
                'localTitle' => $selected['title'],
                'localDescription' => $selected['description'],
                'events' => $events,
            ];
        }

        return $plan;
    }

    /** @param array<string, mixed> $snapshot @param array<string, mixed> $model @return array<string, mixed> */
    private function modelSourcePayload(array $snapshot, array $model): array
    {
        return [
            'template' => [
                'snapshotUuid' => $snapshot['uuid'],
                'stableUuid' => $snapshot['catalogTemplateUuid'],
                'version' => $snapshot['catalogTemplateVersion'],
                'checksum' => $snapshot['catalogReleaseChecksum'],
            ],
            'model' => [
                'recordUuid' => $model['modelRecordUuid'],
                'stableUuid' => $model['modelStableUuid'],
                'title' => $model['modelTitle'],
                'description' => $model['modelDescription'],
                'sourceRevision' => $model['sourceRevision'],
            ],
        ];
    }

    /** @param array<string, mixed> $event @return array<string, mixed> */
    private function eventSourcePayload(array $event): array
    {
        return [
            'recordUuid' => $event['eventRecordUuid'],
            'stableUuid' => $event['eventStableUuid'],
            'version' => $event['eventVersion'],
            'title' => $event['eventTitle'],
            'guidance' => $event['guidance'],
            'relevanceState' => $event['state'],
            'relevanceComment' => $event['comment'],
        ];
    }

    /** @param array<string, mixed> $data */
    private function checksum(array $data): string
    {
        usort($data['models'], static fn (array $left, array $right): int => $left['modelRecordUuid'] <=> $right['modelRecordUuid']);
        foreach ($data['models'] as &$model) {
            sort($model['eventRecordUuids']);
            ksort($model);
        }
        unset($model);
        ksort($data);

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function writer(Anr $anr): User
    {
        $user = $this->users->getConnectedUser();
        $link = $user instanceof User ? $this->userAnrTable->findByAnrAndUser($anr, $user) : null;
        if (!$user instanceof User || $link === null || !$link->hasWriteAccess()) {
            throw new ActionForbiddenException('Scenario template models are read only.');
        }

        return $user;
    }
}
