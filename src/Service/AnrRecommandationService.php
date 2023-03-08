<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use DateTime;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\OptimisticLockException;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Model\Entity\AbstractEntity;
use Monarc\Core\Service\AbstractService;
use Monarc\FrontOffice\Model\Entity\Recommandation;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\RecommandationSetTable;
use Monarc\FrontOffice\Model\Table\RecommandationTable;
use Monarc\FrontOffice\Model\Table\UserAnrTable;
use Monarc\FrontOffice\Service\Traits\RecommendationsPositionsUpdateTrait;
use Throwable;

/**
 * TODO: remove the Abstract class inheritance, inject all the dependencies.
 */
class AnrRecommandationService extends AbstractService
{
    use RecommendationsPositionsUpdateTrait;

    protected array $filterColumns = ['code', 'description'];

    protected AnrTable $anrTable;

    protected UserAnrTable $userAnrTable;

    protected RecommandationTable $recommendationTable;

    protected RecommandationSetTable $recommendationSetTable;

    /**
     * TODO: refactor and remove the Abstract inheritance after.
     */
    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null)
    {
        [$filterJoin,$filterLeft,$filtersCol] = $this->get('entity')->getFiltersForService();
        $recos = $this->get('table')->fetchAllFiltered(
            array_keys($this->get('entity')->getJsonArray()),
            $page,
            $limit,
            $this->parseFrontendOrder($order),
            $this->parseFrontendFilter($filter, $this->filterColumns),
            $filterAnd,
            $filterJoin,
            $filterLeft
        );

        foreach ($recos as $key => $reco) {
            $recos[$key]['timerColor'] = $this->getDueDateColor($reco['duedate']);
            $recos[$key]['counterTreated'] = $reco['counterTreated'] === 0
                ? 'COMING'
                : '_SMILE_IN_PROGRESS (<span>' . $reco['counterTreated'] . '</span>)';
        }

        return $recos;
    }

    public function create($data, $last = true)
    {
        $anr = $this->anrTable->findById($data['anr']);

        $recommendationSet = $this->recommendationSetTable->findByAnrAndUuid($anr, $data['recommandationSet']);

        $entity = (new Recommandation())
            ->setAnr($anr)
            ->setRecommandationSet($recommendationSet)
            ->setImportance($data['importance'])
            ->setCode($data['code'])
            ->setDescription($data['description'])
            ->setCreator($this->getConnectedUser()->getEmail());

        $this->recommendationTable->saveEntity($entity, $last);

        return $entity->getUuid();
    }

    public function patch($id, $data)
    {
        $anr = $this->anrTable->findById($data['anr']);

        $recommendation = $this->recommendationTable->findByAnrAndUuid($anr, $id);

        if (!empty($data['duedate'])) {
            try {
                $recommendation->setDueDate(new DateTime($data['duedate']));
            } catch (Throwable $e) {
            }
        } elseif (isset($data['duedate']) && $data['duedate'] === '') {
            $recommendation->setDueDate(null);
        }

        if (!empty($data['recommandationSet'])
            && $data['recommandationSet'] !== $recommendation->getRecommandationSet()->getUuid()
        ) {
            $recommendationSet = $this->recommendationSetTable->findByAnrAndUuid($anr, $data['recommandationSet']);
            $recommendation->setRecommandationSet($recommendationSet);
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
        if (!empty($data['responsable']) && $recommendation->getResponsable() !== $data['responsable']) {
            $recommendation->setResponsable($data['responsable']);
        }
        if (!empty($data['position']) && $recommendation->getPosition() !== $data['position']) {
            $recommendation->setPosition($data['position']);
        }

        $this->recommendationTable->saveEntity($recommendation->setUpdater($this->getConnectedUser()->getEmail()));

        $this->updatePositions($recommendation, $data, $isImportanceChanged);
    }

    public function update($id, $data)
    {
        foreach ($this->filterColumns as $filterColumn) {
            unset($data[$filterColumn]);
        }

        $this->patch($id, $data);
    }

    /**
     * @param array $id
     *
     * @return bool
     *
     * @throws EntityNotFoundException
     * @throws OptimisticLockException
     */
    public function delete($id)
    {
        /** @var AnrTable $anrTable */
        $anrTable = $this->get('anrTable');
        $anr = $anrTable->findById($id['anr']);
        $recommendation = $this->recommendationTable->findByAnrAndUuid($anr, $id['uuid']);

        if (!$recommendation->isPositionEmpty()) {
            $this->resetRecommendationsPositions($anr, [$recommendation->getUuid() => $recommendation]);
        }

        $this->recommendationTable->deleteEntity($recommendation);

        return true;
    }

    /**
     * Updates the position of the recommendation, based on the implicitPosition and/or previous field passed in $data.
     */
    private function updatePositions(Recommandation $recommendation, array $data, bool $isImportanceChanged): void
    {
        if (!empty($data['implicitPosition'])) {
            $newPosition = $recommendation->getPosition();

            $linkedRecommendations = $this->recommendationTable
                ->findLinkedWithRisksByAnrWithSpecifiedImportanceAndPositionAndExcludeRecommendations(
                    $recommendation->getAnr(),
                    [$recommendation->getUuid()],
                    ['r.position' => 'ASC']
                );

            switch ($data['implicitPosition']) {
                case AbstractEntity::IMP_POS_START:
                    foreach ($linkedRecommendations as $linkedRecommendation) {
                        if ($linkedRecommendation->isPositionHigherThan($recommendation->getPosition())
                            && !$linkedRecommendation->isPositionLowerThan($recommendation->getPosition())
                        ) {
                            $this->recommendationTable->saveEntity($linkedRecommendation->shiftPositionDown(), false);
                        }
                    }

                    $newPosition = 1;

                    break;
                case AbstractEntity::IMP_POS_END:
                    $maxPosition = 1;
                    foreach ($linkedRecommendations as $linkedRecommendation) {
                        if ($linkedRecommendation->isPositionLowerThan($recommendation->getPosition())) {
                            $maxPosition = $linkedRecommendation->getPosition();
                            $this->recommendationTable->saveEntity($linkedRecommendation->shiftPositionUp(), false);
                        }
                    }

                    $newPosition = $maxPosition;

                    break;
                case AbstractEntity::IMP_POS_AFTER:
                    if (!empty($data['previous'])) {
                        $previousRecommendation = $this->recommendationTable->findByAnrAndUuid(
                            $recommendation->getAnr(),
                            $data['previous']
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
                                $this->recommendationTable->saveEntity(
                                    $linkedRecommendation->shiftPositionDown(),
                                    false
                                );
                            } elseif (!$isRecommendationMovedUp
                                && $linkedRecommendation->isPositionLowerThan($recommendation->getPosition())
                                && $linkedRecommendation->isPositionHigherOrEqualThan(
                                    $previousRecommendation->getPosition()
                                )
                            ) {
                                $this->recommendationTable->saveEntity($linkedRecommendation->shiftPositionUp(), false);
                            }
                        }
                    }
                    break;
            }

            $this->recommendationTable->saveEntity($recommendation->setPosition($newPosition));

        } elseif ($isImportanceChanged && !$recommendation->getRecommendationRisks()->isEmpty()) {
            foreach ($recommendation->getRecommendationRisks() as $recommendationRisk) {
                $linkedRisk = $recommendationRisk->getInstanceRisk() ?? $recommendationRisk->getInstanceRiskOp();
                $this->updateInstanceRiskRecommendationsPositions($linkedRisk);

                break;
            }
        }
    }

    /**
     * Computes the due date color for the recommendation. Returns 'no-date' if no due date is set on the
     * recommendation, 'large' if there's a lot of time remaining, 'warning' if there is less than 15 days remaining,
     * and 'alert' if the due date is in the past.
     * @param string $dueDate The due date, in yyyy-mm-dd format
     * @return string 'no-date', 'large', 'warning', 'alert'
     */
    protected function getDueDateColor($dueDate)
    {
        if (empty($dueDate) || $dueDate == '0000-00-00') {
            return 'no-date';
        } else {
            $now = time();
            if ($dueDate instanceof DateTime) {
                $dueDate = $dueDate->getTimestamp();
            } else {
                $dueDate = strtotime($dueDate);
            }
            $diff = $dueDate - $now;

            if ($diff < 0) {
                return "alert";
            } else {
                $days = round($diff / 60 / 60 / 24);
                if ($days <= 15) {//arbitrary 15 days
                    return "warning";
                } else {
                    return "large";
                }
            }
        }
    }
}
