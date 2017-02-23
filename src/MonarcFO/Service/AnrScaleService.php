<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

use MonarcFO\Model\Entity\Scale;

/**
 * Anr Scale Service
 *
 * Class AnrScaleService
 * @package MonarcFO\Service
 */
class AnrScaleService extends \MonarcCore\Service\ScaleService
{
    protected $forbiddenFields = [];
    protected $AnrCheckStartedService;

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
        $anrId = isset($filterAnd['anr']) ? $filterAnd['anr'] : null;

        $scales = parent::getList($page, $limit, $order, $filter, $filterAnd);

        return [$scales, $this->get('AnrCheckStartedService')->canChange($anrId)];
    }

    /**
     * Create
     *
     * @param $data
     * @param bool $last
     * @return mixed
     * @throws \Exception
     */
    public function create($data, $last = true)
    {
        $anrId = isset($data['anr']) ? $data['anr'] : null;
        if ($this->get('AnrCheckStartedService')->canChange($anrId)) {
            return parent::create($data,$last);
        } else {
            throw new \Exception('Scale is not editable', 412);
        }
    }

    /**
     * Patch
     *
     * @param $id
     * @param $data
     * @throws \Exception
     */
    public function patch($id, $data)
    {
        $anrId = isset($data['anr']) ? $data['anr'] : null;
        if ($this->get('AnrCheckStartedService')->canChange($anrId)) {
            return parent::patch($id, $data);
        } else {
            throw new \Exception('Scale is not editable', 412);
        }
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
        $anrId = isset($data['anr']) ? $data['anr'] : null;
        if ($this->get('AnrCheckStartedService')->canChange($anrId)) {
            return \MonarcCore\Service\AbstractService::patch($id, $data);
        } else {
            throw new \Exception('Scale is not editable', 412);
        }
    }
}