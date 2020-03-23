<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use DateTime;
use Doctrine\ORM\EntityNotFoundException;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Model\Entity\AbstractEntity;
use Monarc\Core\Service\AbstractService;
use Monarc\FrontOffice\Model\Entity\Recommandation;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\RecommandationTable;
use Throwable;

/**
 * This class is the service that handles recommendations within an ANR.
 * @package Monarc\FrontOffice\Service
 */
class AnrRecommandationService extends AbstractService
{
    protected $filterColumns = ['code', 'description'];
    protected $dependencies = ['anr', 'recommandationSet'];
    protected $anrTable;
    protected $userAnrTable;

    /**
     * @inheritdoc
     */
    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null)
    {
        list($filterJoin,$filterLeft,$filtersCol) = $this->get('entity')->getFiltersForService();
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

    /**
     * @inheritdoc
     */
    public function create($data, $last = true)
    {
        /** @var RecommandationTable $recommendationTable */
        $recommendationTable = $this->get('table');

        $class = $this->get('entity');
        /** @var Recommandation $entity */
        $entity = new $class();
        $entity->setLanguage($this->getLanguage());
        $entity->setDbAdapter($recommendationTable->getDb());

        $entity->setAnr($data['anr']);

        $entity->exchangeArray($data);

        $dependencies = property_exists($this, 'dependencies') ? $this->dependencies : [];
        $this->setDependencies($entity, $dependencies);

        return $recommendationTable->save($entity, $last);
    }

    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        parent::patch($id, $this->prepareUpdateData($id, $data));
    }

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        parent::update($id, $this->prepareUpdateData($id, $data));
    }

    /**
     * Updates the position of the recommendation, based on the implicitPosition field passed in $data.
     *
     * @param array $recommendationId The recommendation composite ID [anr, uuid]
     * @param array $data The positioning data (implicitPosition field, and previous)
     *
     * @return array
     *
     * @throws Exception
     * @throws EntityNotFoundException
     */
    private function updatePosition(array $recommendationId, array $data): array
    {
        if (!empty($data['implicitPosition'])) {
            /** @var AnrTable $anrTable */
            $anrTable = $this->get('anrTable');
            $anr = $anrTable->findById($recommendationId['anr']);

            /** @var RecommandationTable $recommendationTable */
            $recommendationTable = $this->get('table');
            $recommendation = $recommendationTable->findByAnrAndUuid($anr, $recommendationId['uuid']);
            $newPosition = $recommendation->getPosition();

            $linkedRecommendations = $recommendationTable
                ->findLinkedWithRisksByAnrWithSpecifiedImportanceAndPositionAndExcludeRecommendations(
                    $anr,
                    [$recommendationId['uuid']],
                    ['position' => 'ASC']
                );

            switch ($data['implicitPosition']) {
                case AbstractEntity::IMP_POS_START:
                    foreach ($linkedRecommendations as $linkedRecommendation) {
                        if ($linkedRecommendation->isPositionHigherThan($recommendation->getPosition())
                            && !$linkedRecommendation->isPositionLowerThan($recommendation->getPosition())
                        ) {
                            $recommendationTable->saveEntity($linkedRecommendation->shiftPositionDown(), false);
                        }
                    }

                    $newPosition = 1;

                    break;
                case AbstractEntity::IMP_POS_END:
                    $maxPosition = 1;
                    foreach ($linkedRecommendations as $linkedRecommendation) {
                        if ($linkedRecommendation->isPositionLowerThan($recommendation->getPosition())) {
                            $maxPosition = $linkedRecommendation->getPosition();
                            $recommendationTable->saveEntity($linkedRecommendation->shiftPositionUp(), false);
                        }
                    }

                    $newPosition = $maxPosition;

                    break;
                case AbstractEntity::IMP_POS_AFTER:
                    if (!empty($data['previous'])) {
                        $previousRecommendation = $recommendationTable->findByAnrAndUuid($anr, $data['previous']);
                        $isRecommendationMovedUp = $previousRecommendation->isPositionHigherThan(
                            $recommendation->getPosition()
                        );
                        foreach ($linkedRecommendations as $linkedRecommendation) {
                            if ($isRecommendationMovedUp
                                && $linkedRecommendation->isPositionLowerThan($previousRecommendation->getPosition())
                                && $linkedRecommendation->isPositionHigherThan($recommendation->getPosition())
                            ) {
                                $recommendationTable->saveEntity($linkedRecommendation->shiftPositionDown(), false);
                            } elseif (!$isRecommendationMovedUp
                                && $linkedRecommendation->isPositionLowerThan($recommendation->getPosition())
                                && $linkedRecommendation->isPositionHigherOrEqualThan(
                                    $previousRecommendation->getPosition()
                                )
                            ) {
                                $recommendationTable->saveEntity($linkedRecommendation->shiftPositionUp(), false);
                            }
                        }

                        $newPosition = $previousRecommendation->getPosition();
                    }
                    break;
            }

            $recommendationTable->saveEntity($recommendation->setPosition($newPosition));
        }

        unset($data['implicitPosition'], $data['previous']);

        return $data;
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

    private function prepareUpdateData($id, $data): array
    {
        if (!isset($data['duedate'])) {
            $data['duedate'] = null;
        }

        if ($data['duedate'] !== null) {
            try {
                $data['duedate'] = new DateTime($data['duedate']);
            } catch (Throwable $e) {
                throw new Exception('Invalid date format', 412);
            }
        }

        if (empty($data['recommandationSet'])) {
            $data['recommandationSet'] = $this->get('table')->getEntity($id)->get('recommandationSet');
        }

        return $this->updatePosition($id, $data);
    }
}
