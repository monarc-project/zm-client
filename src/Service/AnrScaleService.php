<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\ScaleService;

/**
 * This class is the service that handles scales within an ANR. This is a simple CRUD service.
 * Note that the scales are not editable after the ANR has started being evaluated.
 * @see anrCheckStartedService
 * @package Monarc\FrontOffice\Service
 */
class AnrScaleService extends ScaleService
{
    protected $forbiddenFields = [];

    /** @var AnrCheckStartedService */
    protected $anrCheckStartedService;

    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null)
    {
        $scales = parent::getList($page, $limit, $order, $filter, $filterAnd);

        /** @var AnrCheckStartedService $anrCheckStartedService */
        $anrCheckStartedService = $this->get('anrCheckStartedService');

        // Return both the scales, and also whether we can modify them
        return [$scales, $anrCheckStartedService->canChange($filterAnd['anr'])];
    }

    public function create($data, $last = true)
    {
        $this->validateScaleEditable($data['anr']);

        return parent::create($data, $last);
    }

    public function patch($id, $data)
    {
        $this->validateScaleEditable($data['anr']);

        return parent::patch($id, $data);
    }

    public function update($id, $data)
    {
        $this->validateScaleEditable($data['anr']);

        return parent::patch($id, $data);
    }

    /**
     * @throws Exception
     */
    private function validateScaleEditable(int $anrId): void
    {
        /** @var AnrCheckStartedService $anrCheckStartedService */
        $anrCheckStartedService = $this->get('anrCheckStartedService');
        if (!$anrCheckStartedService->canChange($anrId)) {
            throw new Exception('Scale is not editable', 412);
        }
    }
}
