<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

use MonarcFO\Service\AbstractService;

/**
 * Anr Recommandation Service
 *
 * Class AnrRecommandationService
 * @package MonarcFO\Service
 */
class AnrRecommandationService extends \MonarcCore\Service\AbstractService
{
    protected $filterColumns = ['code', 'description'];
    protected $dependencies = ['anr'];
    protected $anrTable;
    protected $userAnrTable;

    /**
     * Get List
     *
     * @param int $page
     * @param int $limit
     * @param null $order
     * @param null $filter
     * @return mixed
     */
    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null)
    {
        $recos = $this->get('table')->fetchAllFiltered(
            array_keys($this->get('entity')->getJsonArray()),
            $page,
            $limit,
            $this->parseFrontendOrder($order),
            $this->parseFrontendFilter($filter, $this->filterColumns),
            $filterAnd
        );

        foreach ($recos as $key => $reco) {
            $recos[$key]['timerColor'] = $this->getDueDateColor($reco['duedate']);
            $recos[$key]['counterTreated'] = ($reco['counterTreated'] == 0) ? 'COMING' : '_SMILE_IN_PROGRESS (<span>' . $reco['counterTreated'] . '</span>)';
        }

        return $recos;
    }

    /**
     * Create
     *
     * @param $data
     * @param bool $last
     * @return mixed
     */
    public function create($data, $last = true)
    {
        $class = $this->get('entity');
        $entity = new $class();
        $entity->setLanguage($this->getLanguage());
        $entity->setDbAdapter($this->get('table')->getDb());
        $entity->anr = $data['anr'];

        $dependencies = (property_exists($this, 'dependencies')) ? $this->dependencies : [];
        $this->setDependencies($entity, $dependencies);

        $data['position'] = null;
        $entity->exchangeArray($data);

        $dependencies = (property_exists($this, 'dependencies')) ? $this->dependencies : [];
        $this->setDependencies($entity, $dependencies);

        /** @var AnrTable $table */
        $table = $this->get('table');

        return $table->save($entity, $last);
    }

    /**
     * Due date
     *
     * @param $dueDate
     * @return string
     */
    protected function getDueDateColor($dueDate)
    {
        if (empty($dueDate) || $dueDate == '0000-00-00') {
            return 'no-date';
        } else {
            $now = time();
            if ($dueDate instanceof \DateTime) {
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
                } else return "large";
            }
        }
    }

    /**
     * Patch
     *
     * @param $id
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    public function patch($id, $data)
    {
        if (!empty($data['duedate'])) {
            try {
                $data['duedate'] = new \DateTime($data['duedate']);
            } catch (\Exception $e) {
                throw new \Exception('Invalid date format', 412);
            }
        }

        parent::patch($id, $data);
    }

    /**
     * Update
     *
     * @param $id
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    public function update($id, $data)
    {
        if (!empty($data['duedate'])) {
            try {
                $data['duedate'] = new \DateTime($data['duedate']);
            } catch (\Exception $e) {
                throw new \Exception('Invalid date format', 412);
            }
        }

        parent::update($id, $data);
    }
}