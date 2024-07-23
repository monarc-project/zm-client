<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use DateTime;
use Monarc\Core\InputFormatter\FormattedInputParams;
use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\Interfaces\PositionUpdatableServiceInterface;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Table\RecommendationSetTable;
use Monarc\FrontOffice\Table\RecommendationTable;
use Monarc\FrontOffice\Service\Traits\RecommendationsPositionsUpdateTrait;
use Throwable;

class AnrRecommendationService
{
    use RecommendationsPositionsUpdateTrait;

    private UserSuperClass $connectedUser;

    public function __construct(
        private RecommendationTable $recommendationTable,
        private RecommendationSetTable $recommendationSetTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getList(FormattedInputParams $formattedInputParams): array
    {
        $recommendationsData = [];
        /** @var Entity\Recommendation $recommendation */
        foreach ($this->recommendationTable->findByParams($formattedInputParams) as $recommendation) {
            $recommendationsData[] = $this->getPreparedRecommendationData($recommendation);
        }

        return $recommendationsData;
    }

    public function getCount(FormattedInputParams $formattedInputParams): int
    {
        return $this->recommendationTable->countByParams($formattedInputParams);
    }

    public function getRecommendationData(Entity\Anr $anr, string $uuid): array
    {
        /** @var Entity\Recommendation $recommendation */
        $recommendation = $this->recommendationTable->findByUuidAndAnr($uuid, $anr);

        return $this->getPreparedRecommendationData($recommendation);
    }

    public function create(Entity\Anr $anr, array $data, bool $saveInDb = true): Entity\Recommendation
    {
        /** @var Entity\RecommendationSet $recommendationSet */
        $recommendationSet = $data['recommendationSet'] instanceof Entity\RecommendationSet
            ? $data['recommendationSet']
            : $this->recommendationSetTable->findByUuidAndAnr($data['recommendationSet'], $anr);

        $recommendation = (new Entity\Recommendation())
            ->setAnr($anr)
            ->setRecommendationSet($recommendationSet)
            ->setImportance($data['importance'] ?? Entity\Recommendation::EMPTY_IMPORTANCE)
            ->setCode($data['code'])
            ->setDescription($data['description'])
            ->setCreator($this->connectedUser->getEmail());
        if (!empty($data['uuid'])) {
            /* The UUID is set only when it's imported from MOSP or duplicated on anr creation. */
            $recommendation->setUuid($data['uuid']);
        }
        if (isset($data['position'])) {
            $recommendation->setPosition($data['position']);
        }
        if (isset($data['comment'])) {
            $recommendation->setComment($data['comment']);
        }
        if (isset($data['status'])) {
            $recommendation->setStatus($data['status']);
        }
        if (isset($data['responsible']) || isset($data['responsable'])) {
            $recommendation->setResponsible($data['responsible'] ?? $data['responsable']);
        }
        if (isset($data['duedate'])) {
            if (!empty($data['duedate']) && !$data['duedate'] instanceof DateTime) {
                $recommendation->setDueDateFromString($data['duedate']);
            } else {
                $recommendation->setDueDate($data['duedate']);
            }
        }
        if (isset($data['counterTreated'])) {
            $recommendation->setCounterTreated($data['counterTreated']);
        }

        $this->recommendationTable->save($recommendation, $saveInDb);

        return $recommendation;
    }

    /**
     * @return string[]
     */
    public function createList(Entity\Anr $anr, array $data): array
    {
        $createdUuids = [];
        foreach ($data as $recommendationData) {
            $createdUuids[] = $this->create($anr, $recommendationData, false)->getUuid();
        }
        $this->recommendationTable->flush();

        return $createdUuids;
    }

    public function patch(Entity\Anr $anr, string $uuid, array $data): Entity\Recommendation
    {
        /** @var Entity\Recommendation $recommendation */
        $recommendation = $this->recommendationTable->findByUuidAndAnr($uuid, $anr);

        if (!empty($data['duedate'])) {
            try {
                $recommendation->setDueDate(new DateTime($data['duedate']));
            } catch (Throwable) {
            }
        } elseif (isset($data['duedate']) && $data['duedate'] === '') {
            $recommendation->setDueDate(null);
        }

        if (!empty($data['recommendationSet'])
            && $data['recommendationSet'] !== $recommendation->getRecommendationSet()->getUuid()
        ) {
            /** @var Entity\RecommendationSet $recommendationSet */
            $recommendationSet = $this->recommendationSetTable->findByUuidAndAnr($data['recommendationSet'], $anr);
            $recommendation->setRecommendationSet($recommendationSet);
        }

        if (!empty($data['code']) && $data['code'] !== $recommendation->getCode()) {
            $recommendation->setCode($data['code']);
        }
        if (!empty($data['description']) && $data['description'] !== $recommendation->getDescription()) {
            $recommendation->setDescription($data['description']);
        }
        $isImportanceChanged = false;
        if (!empty($data['importance']) && $recommendation->getImportance() !== $data['importance']) {
            $isImportanceChanged = true;
            $recommendation->setImportance($data['importance']);
        }
        if (isset($data['status']) && $recommendation->getStatus() !== (int)$data['status']) {
            $recommendation->setStatus((int)$data['status']);
        }
        if (!empty($data['comment']) && $recommendation->getComment() !== $data['comment']) {
            $recommendation->setComment($data['comment']);
        }
        if (!empty($data['responsable']) && $recommendation->getResponsible() !== $data['responsable']) {
            $recommendation->setResponsible($data['responsable']);
        }
        if (!empty($data['position']) && $recommendation->getPosition() !== $data['position']) {
            $recommendation->setPosition($data['position']);
        }

        $this->recommendationTable->save($recommendation->setUpdater($this->connectedUser->getEmail()));

        $this->updatePositions($recommendation, $data, $isImportanceChanged);

        return $recommendation;
    }

    public function delete(Entity\Anr $anr, string $uuid): void
    {
        /** @var Entity\Recommendation $recommendation */
        $recommendation = $this->recommendationTable->findByUuidAndAnr($uuid, $anr);

        if (!$recommendation->isPositionEmpty()) {
            $this->resetRecommendationsPositions($anr, [$recommendation->getUuid() => $recommendation]);
        }

        $this->recommendationTable->remove($recommendation);
    }

    /**
     * Updates the position of the recommendation, based on the implicitPosition and/or previous field passed in $data.
     */
    private function updatePositions(
        Entity\Recommendation $recommendation,
        array $data,
        bool $isImportanceChanged
    ): void {
        if (!empty($data['implicitPosition'])) {
            $newPosition = $recommendation->getPosition();

            $linkedRecommendations = $this->recommendationTable
                ->findLinkedWithRisksByAnrWithSpecifiedImportanceAndPositionAndExcludeRecommendations(
                    $recommendation->getAnr(),
                    [$recommendation->getUuid()],
                    ['r.position' => 'ASC']
                );

            switch ($data['implicitPosition']) {
                case PositionUpdatableServiceInterface::IMPLICIT_POSITION_START:
                    foreach ($linkedRecommendations as $linkedRecommendation) {
                        if ($linkedRecommendation->isPositionHigherThan($recommendation->getPosition())
                            && !$linkedRecommendation->isPositionLowerThan($recommendation->getPosition())
                        ) {
                            $this->recommendationTable->save($linkedRecommendation->shiftPositionDown(), false);
                        }
                    }
                    $newPosition = 1;
                    break;
                case PositionUpdatableServiceInterface::IMPLICIT_POSITION_END:
                    $maxPosition = 1;
                    foreach ($linkedRecommendations as $linkedRecommendation) {
                        if ($linkedRecommendation->isPositionLowerThan($recommendation->getPosition())) {
                            $maxPosition = $linkedRecommendation->getPosition();
                            $this->recommendationTable->save($linkedRecommendation->shiftPositionUp(), false);
                        }
                    }
                    $newPosition = $maxPosition;
                    break;
                case PositionUpdatableServiceInterface::IMPLICIT_POSITION_AFTER:
                    if (!empty($data['previous'])) {
                        /** @var Entity\Recommendation $previousRecommendation */
                        $previousRecommendation = $this->recommendationTable->findByUuidAndAnr(
                            $data['previous'],
                            $recommendation->getAnr()
                        );
                        $isRecommendationMovedUp = $previousRecommendation->isPositionHigherThan(
                            $recommendation->getPosition()
                        );
                        $newPosition = $isRecommendationMovedUp ? $previousRecommendation->getPosition() + 1
                            : $previousRecommendation->getPosition();

                        foreach ($linkedRecommendations as $linkedRecommendation) {
                            if ($isRecommendationMovedUp
                                && $linkedRecommendation->isPositionLowerThan($previousRecommendation->getPosition())
                                && $linkedRecommendation->isPositionHigherThan($recommendation->getPosition())
                            ) {
                                $this->recommendationTable->save(
                                    $linkedRecommendation->shiftPositionDown(),
                                    false
                                );
                            } elseif (!$isRecommendationMovedUp
                                && $linkedRecommendation->isPositionLowerThan($recommendation->getPosition())
                                && $linkedRecommendation->isPositionHigherOrEqualThan(
                                    $previousRecommendation->getPosition()
                                )
                            ) {
                                $this->recommendationTable->save($linkedRecommendation->shiftPositionUp(), false);
                            }
                        }
                    }
                    break;
            }
            $this->recommendationTable->save($recommendation->setPosition($newPosition));
        } elseif ($isImportanceChanged && $recommendation->hasLinkedRecommendationRisks()) {
            $recommendationRisk = $recommendation->getRecommendationRisks()->first();
            $linkedRisk = $recommendationRisk->getInstanceRisk() ?? $recommendationRisk->getInstanceRiskOp();
            $this->updateInstanceRiskRecommendationsPositions($linkedRisk);
        }
    }

    /**
     * Computes the due date color for the recommendation.
     * Returns 'no-date' if no due date is set on the recommendation, 'large' if there's a lot of time remaining,
     * 'warning' if there is less than 15 days remaining, and 'alert' if the due date is in the past.
     *
     * @return string 'no-date', 'large', 'warning', 'alert'
     */
    private function getDueDateColor(?DateTime $dueDate): string
    {
        if ($dueDate === null) {
            return 'no-date';
        }

        $diff = $dueDate->getTimestamp() - time();
        if ($diff < 0) {
            return "alert";
        }
        /* arbitrary 15 days */
        $days = round($diff / 60 / 60 / 24);
        if ($days <= 15) {
            return "warning";
        }

        return "large";
    }

    private function getPreparedRecommendationData(Entity\Recommendation $recommendation): array
    {
        return [
            'uuid' => $recommendation->getUuid(),
            'recommendationSet' => [
                'uuid' => $recommendation->getRecommendationSet()->getUuid(),
                'label' => $recommendation->getRecommendationSet()->getLabel(),
            ],
            'code' => $recommendation->getCode(),
            'comment' => $recommendation->getComment(),
            'description' => $recommendation->getDescription(),
            'duedate' => $recommendation->getDueDate() === null ? ''
                : ['date' => $recommendation->getDueDate()->format('Y-m-d')],
            'importance' => $recommendation->getImportance(),
            'position' => $recommendation->getPosition(),
            'responsable' => $recommendation->getResponsible(),
            'status' => $recommendation->getStatus(),
            'timerColor' => $this->getDueDateColor($recommendation->getDueDate()),
            'counterTreated' => $recommendation->getCounterTreated() === 0
                ? 'COMING'
                : '_SMILE_IN_PROGRESS (<span>' . $recommendation->getCounterTreated() . '</span>)'
        ];
    }
}
