<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use DateTime;
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

        /** @var AnrTable $anrTable */
        $anrTable = $this->get('anrTable');
        $anr = $anrTable->findById($data['anr']);

        $entity->setAnr($data['anr']);

        $data['position'] = $recommendationTable->getMaxPositionByAnr($anr) + 1;

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
     * @param int $id The recommendation composite ID [anr, uuid]
     * @param array $data The positionning data (implicitPosition field, and previous)
     */
    private function updatePosition($id, $data): array
    {
        if (!empty($data['implicitPosition'])) {
            $entity = $this->get('table')->getEntity($id);
            if ($entity->get('position') > 0) {
                switch ($data['implicitPosition']) {
                    case AbstractEntity::IMP_POS_START:
                        $data['position'] = 1;
                        $bros = $this->get('table')->getRepository()->createQueryBuilder('bro')
                            ->select()
                            ->where('bro.anr = :anr')
                            ->setParameter(':anr', $entity->get('anr'))
                            ->andWhere('bro.uuid <> :uuid')
                            ->setParameter(':uuid', (string)$entity->get('uuid'))
                            ->andWhere('bro.position <= :pos')
                            ->setParameter(':pos', $entity->get('position'))
                            ->andWhere('bro.position IS NOT NULL')
                            ->getQuery()
                            ->getResult();
                        foreach ($bros as $b) {
                            $b->set('position', $b->get('position') + 1);
                            $this->get('table')->save($b, false);
                        }
                        break;
                    case AbstractEntity::IMP_POS_END:
                        $pos = $this->get('table')->getRepository()->createQueryBuilder('bro')
                            ->select('MAX(bro.position)')
                            ->where('bro.anr = :anr')
                            ->setParameter(':anr', $entity->get('anr'))
                            ->andWhere('bro.position IS NOT NULL')
                            ->getQuery()->getSingleScalarResult();
                        $data['position'] = $pos;
                        $bros = $this->get('table')->getRepository()->createQueryBuilder('bro')
                            ->select()
                            ->where('bro.anr = :anr')
                            ->setParameter(':anr', $entity->get('anr'))
                            ->andWhere('bro.uuid <> :uuid')
                            ->setParameter(':uuid', (string)$entity->get('uuid'))
                            ->andWhere('bro.position >= :pos')
                            ->setParameter(':pos', $entity->get('position'))
                            ->andWhere('bro.position IS NOT NULL')
                            ->getQuery()
                            ->getResult();
                        foreach ($bros as $b) {
                            $b->set('position', $b->get('position') - 1);
                            $this->get('table')->save($b, false);
                        }
                        break;
                    case AbstractEntity::IMP_POS_AFTER:
                        if (!empty($data['previous'])) {
                            $prev = $this->get('table')->getEntity([
                                'anr' => $entity->get('anr')->getId(),
                                'uuid' => $data['previous']
                            ]);
                            if ($prev && $prev->get('position') > 0
                                && $prev->get('anr')->getId() === $entity->get('anr')->getId()
                            ) {
                                $data['position'] = $prev->get('position')
                                    + ($entity->get('position') > $prev->get('position') ? 1 : 0);
                                $bros = $this->get('table')->getRepository()->createQueryBuilder('bro')
                                    ->select()
                                    ->where('bro.anr = :anr')
                                    ->setParameter(':anr', $entity->get('anr'))
                                    ->andWhere('bro.uuid <> :uuid')
                                    ->setParameter(':uuid', (string)$entity->get('uuid'))
                                    ->andWhere('bro.position '
                                        . ($entity->get('position') > $data['position'] ? '>' : '<') . '= :pos1')
                                    ->setParameter(':pos1', $data['position'])
                                    ->andWhere('bro.position '
                                        . ($entity->get('position') > $data['position'] ? '<' : '>') . ' :pos2')
                                    ->setParameter(':pos2', $entity->get('position'))
                                    ->andWhere('bro.position IS NOT NULL')
                                    ->getQuery()
                                    ->getResult();
                                $val = $entity->get('position') > $data['position'] ? 1 : -1;
                                foreach ($bros as $b) {
                                    $b->set('position', $b->get('position') + $val);
                                    $this->get('table')->save($b, false);
                                }
                            }
                        }
                        break;
                    default:
                        break;
                }
            }
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
