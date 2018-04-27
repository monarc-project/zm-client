<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcFO\Model\Entity\Scale;

/**
 * This class is the service that handles scales within an ANR. This is a simple CRUD service.
 * Note that the scales are not editable after the ANR has started being evaluated.
 * @see AnrCheckStartedService
 * @package MonarcFO\Service
 */
class AnrScaleService extends \MonarcCore\Service\ScaleService
{
    protected $forbiddenFields = [];
    protected $AnrCheckStartedService;

    /**
     * @inheritdoc
     */
    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null)
    {
        $anrId = isset($filterAnd['anr']) ? $filterAnd['anr'] : null;
        $scales = parent::getList($page, $limit, $order, $filter, $filterAnd);

        // Return both the scales, and also whether or not we can modify them
        return [$scales, $this->get('AnrCheckStartedService')->canChange($anrId)];
    }

    /**
     * @inheritdoc
     */
    public function create($data, $last = true)
    {
        $anrId = isset($data['anr']) ? $data['anr'] : null;
        if ($this->get('AnrCheckStartedService')->canChange($anrId)) {
            return parent::create($data,$last);
        } else {
            throw new \MonarcCore\Exception\Exception('Scale is not editable', 412);
        }
    }

    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        $anrId = isset($data['anr']) ? $data['anr'] : null;
        if ($this->get('AnrCheckStartedService')->canChange($anrId)) {
            return parent::patch($id, $data);
        } else {
            throw new \MonarcCore\Exception\Exception('Scale is not editable', 412);
        }
    }

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        $anrId = isset($data['anr']) ? $data['anr'] : null;
        if ($this->get('AnrCheckStartedService')->canChange($anrId)) {
            return \MonarcCore\Service\AbstractService::patch($id, $data);
        } else {
            throw new \MonarcCore\Exception\Exception('Scale is not editable', 412);
        }
    }
}