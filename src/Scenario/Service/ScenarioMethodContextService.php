<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Service;

use DateTime;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\ReassessmentTriggerService as CoreReassessmentTriggerService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Question;
use Monarc\FrontOffice\Entity\QuestionChoice;
use Monarc\FrontOffice\Entity\ReassessmentTrigger;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Model\Table\QuestionTable;
use Monarc\FrontOffice\Scenario\Table\ScenarioAnalysisTable;
use Monarc\FrontOffice\Scenario\Table\ScenarioAssessmentTable;
use Monarc\FrontOffice\Table\AnrTable;
use Monarc\FrontOffice\Table\ReassessmentTriggerTable;

/** Reuses established per-ANR context-method data for a Scenario analysis. */
final class ScenarioMethodContextService
{
    public function __construct(
        private QuestionTable $questionTable,
        private ReassessmentTriggerTable $reassessmentTriggerTable,
        private AnrTable $anrTable,
        private ScenarioAnalysisTable $scenarioAnalysisTable,
        private ScenarioAssessmentTable $scenarioAssessmentTable,
        private ScenarioReviewService $reviewService,
        private CoreReassessmentTriggerService $coreReassessmentTriggerService,
        private ConnectedUserService $connectedUserService
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function trendQuestions(Anr $anr): array
    {
        $questions = $this->questionTable->findByAnr($anr);
        usort(
            $questions,
            static fn (Question $left, Question $right): int => $left->getPosition() <=> $right->getPosition()
        );

        return array_map(fn (Question $question): array => $this->questionData($question, $anr), $questions);
    }

    /** @return array<string, mixed> */
    public function updateTrendResponse(Anr $anr, int $questionId, mixed $response, string $correlationId): array
    {
        foreach ($this->questionTable->findByAnr($anr) as $question) {
            if ($question->getId() !== $questionId) {
                continue;
            }

            $question->setResponse($this->normaliseTrendResponse($question, $response));
            $this->questionTable->saveEntity($question);
            $this->audit($anr, $correlationId, 'analysis_context_trend_updated', ['questionId' => $questionId]);

            return $this->questionData($question, $anr);
        }

        throw new \InvalidArgumentException('The trend question was not found.');
    }

    /** @return array<string, mixed> */
    public function reassessmentStrategy(Anr $anr): array
    {
        return [
            'lastReviewAt' => $anr->getReassessmentLastReviewDate()?->format('Y-m-d'),
            'reviewFrequency' => $anr->getReassessmentReviewFrequency(),
            'availableReviewFrequencies' => Anr::getAvailableReviewFrequencies(),
            'availableTriggers' => $this->coreReassessmentTriggerService->getSelectionData($anr->getLanguageCode()),
            'triggers' => array_map(
                fn (ReassessmentTrigger $trigger): array => $this->triggerData($trigger),
                $this->reassessmentTriggerTable->findByAnrOrderedByPosition($anr)
            ),
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function updateReassessmentStrategy(Anr $anr, array $data, string $correlationId): array
    {
        if (array_key_exists('lastReviewAt', $data)) {
            $anr->setReassessmentLastReviewDate($this->normaliseLastReviewAt($data['lastReviewAt']));
        }
        if (array_key_exists('reviewFrequency', $data)) {
            $reviewFrequency = (string) $data['reviewFrequency'];
            if (!in_array($reviewFrequency, Anr::getAvailableReviewFrequencies(), true)) {
                throw new \InvalidArgumentException('The reassessment review frequency is invalid.');
            }
            $anr->setReassessmentReviewFrequency($reviewFrequency);
        }
        if ($data !== []) {
            $this->reviewService->saveStrategyAndSynchronizeProjection($anr, function () use ($anr): void {
                $anr->setUpdater($this->currentUserEmail());
                $this->anrTable->save($anr);
                // Context Establishment owns the writable strategy. The Scenario
                // registry date is only its derived compatibility projection.
            });
            $this->audit($anr, $correlationId, 'analysis_reassessment_strategy_updated', array_keys($data));
        }

        return $this->reassessmentStrategy($anr);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function createReassessmentTrigger(Anr $anr, array $data, string $correlationId): array
    {
        $trigger = (new ReassessmentTrigger())
            ->setAnr($anr)
            ->setTriggerType($data['triggerType'] ?? null)
            ->setDescription(trim((string) $data['description']))
            ->setMonitoringApproach(trim((string) ($data['monitoringApproach'] ?? '')))
            ->setIsActive((bool) ($data['isActive'] ?? true))
            ->setPosition($this->reassessmentTriggerTable->findMaxPosition(['anr' => $anr]) + 1)
            ->setCreator($this->currentUserEmail());
        $this->reassessmentTriggerTable->save($trigger);
        $this->audit($anr, $correlationId, 'analysis_reassessment_trigger_created', ['triggerId' => $trigger->getId()]);

        return $this->triggerData($trigger);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function updateReassessmentTrigger(Anr $anr, int $triggerId, array $data, string $correlationId): array
    {
        /** @var ReassessmentTrigger $trigger */
        $trigger = $this->reassessmentTriggerTable->findByIdAndAnr($triggerId, $anr);
        if (isset($data['triggerType'])) {
            $trigger->setTriggerType($data['triggerType']);
        }
        if (isset($data['description'])) {
            $trigger->setDescription(trim((string) $data['description']));
        }
        if (isset($data['monitoringApproach'])) {
            $trigger->setMonitoringApproach(trim((string) $data['monitoringApproach']));
        }
        if (isset($data['isActive'])) {
            $trigger->setIsActive((bool) $data['isActive']);
        }
        if (isset($data['position']) && (int) $data['position'] !== $trigger->getPosition()) {
            $this->moveTrigger($trigger, (int) $data['position']);
        }
        $trigger->setUpdater($this->currentUserEmail());
        $this->reassessmentTriggerTable->save($trigger);
        $this->audit($anr, $correlationId, 'analysis_reassessment_trigger_updated', ['triggerId' => $triggerId]);

        return $this->triggerData($trigger);
    }

    public function deleteReassessmentTrigger(Anr $anr, int $triggerId, string $correlationId): void
    {
        /** @var ReassessmentTrigger $trigger */
        $trigger = $this->reassessmentTriggerTable->findByIdAndAnr($triggerId, $anr);
        if ($this->scenarioAssessmentTable->hasMonitoringForReassessmentTrigger((int) $anr->getId(), $triggerId)) {
            throw new \InvalidArgumentException(
                'This reassessment trigger is used by monitoring evidence. Deactivate it instead of removing it.'
            );
        }
        $this->reassessmentTriggerTable->incrementPositions(
            $trigger->getPosition() + 1,
            -1,
            -1,
            ['anr' => $anr],
            $this->currentUserEmail()
        );
        $this->reassessmentTriggerTable->remove($trigger);
        $this->audit($anr, $correlationId, 'analysis_reassessment_trigger_removed', ['triggerId' => $triggerId]);
    }

    /** @return array<string, mixed> */
    private function questionData(Question $question, Anr $anr): array
    {
        $response = $question->getResponse();
        if ($question->getType() === 2 && $response !== null && $response !== '') {
            $decoded = json_decode($response, true);
            $response = json_last_error() === JSON_ERROR_NONE ? $decoded : $response;
        }

        return [
            'id' => $question->getId(),
            'label' => $question->getLabel($anr->getLanguage()),
            'type' => $question->getType(),
            'multiple' => $question->isMultiChoice(),
            'response' => $response,
            'choices' => array_map(
                fn (QuestionChoice $choice): array => [
                    'id' => $choice->getId(),
                    'label' => $choice->getLabel($anr->getLanguage()),
                ],
                $question->getQuestionChoices()->toArray()
            ),
        ];
    }

    private function normaliseTrendResponse(Question $question, mixed $response): string
    {
        if ($question->getType() === 1) {
            if (!is_string($response) || mb_strlen($response) > 65_535) {
                throw new \InvalidArgumentException('The trend response is invalid.');
            }

            return trim($response);
        }

        if ($question->isMultiChoice()) {
            if (!is_array($response) || array_filter($response, static fn (mixed $value): bool => !is_int($value))) {
                throw new \InvalidArgumentException('The trend response is invalid.');
            }

            return json_encode(array_values(array_unique($response)), JSON_THROW_ON_ERROR);
        }
        if (!is_int($response) && !is_string($response)) {
            throw new \InvalidArgumentException('The trend response is invalid.');
        }

        return (string) $response;
    }

    /** @return array<string, mixed> */
    private function triggerData(ReassessmentTrigger $trigger): array
    {
        return [
            'id' => $trigger->getId(),
            'triggerType' => $trigger->getTriggerType(),
            'description' => $trigger->getDescription(),
            'monitoringApproach' => $trigger->getMonitoringApproach(),
            'isActive' => $trigger->isActive(),
            'position' => $trigger->getPosition(),
        ];
    }

    private function normaliseLastReviewAt(mixed $value): ?DateTime
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException('The reassessment last review date is invalid.');
        }

        $date = DateTime::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException('The reassessment last review date is invalid.');
        }

        return $date;
    }

    private function moveTrigger(ReassessmentTrigger $trigger, int $newPosition): void
    {
        $oldPosition = $trigger->getPosition();
        $maxPosition = $this->reassessmentTriggerTable->findMaxPosition(['anr' => $trigger->getAnr()]);
        $position = max(1, min($newPosition, $maxPosition));
        if ($position < $oldPosition) {
            $this->reassessmentTriggerTable->incrementPositions(
                $position,
                $oldPosition - 1,
                1,
                ['anr' => $trigger->getAnr()],
                $this->currentUserEmail()
            );
        } elseif ($position > $oldPosition) {
            $this->reassessmentTriggerTable->incrementPositions(
                $oldPosition + 1,
                $position,
                -1,
                ['anr' => $trigger->getAnr()],
                $this->currentUserEmail()
            );
        }
        $trigger->setPosition($position);
    }

    private function currentUserEmail(): string
    {
        $user = $this->connectedUserService->getConnectedUser();

        return $user instanceof User ? $user->getEmail() : 'scenario-system@localhost';
    }

    /** @param array<string, mixed>|array<int, string> $patch */
    private function audit(Anr $anr, string $correlationId, string $action, array $patch): void
    {
        $user = $this->connectedUserService->getConnectedUser();
        if (!$user instanceof User) {
            return;
        }

        $this->scenarioAnalysisTable->auditMethodContext(
            (int) $anr->getId(),
            (int) $user->getId(),
            $correlationId,
            $action,
            $patch
        );
    }
}
