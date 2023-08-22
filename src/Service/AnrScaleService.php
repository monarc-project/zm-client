<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Service\ScaleService;

/** TODO:Drop the inheritance of core and implement methods here. */
class AnrScaleService extends ScaleService
{
    protected $forbiddenFields = [];

    /** @var AnrCheckStartedService */
    protected $anrCheckStartedService;

    public function __construct()
    {
    }

    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null)
    {
        $scales = parent::getList($page, $limit, $order, $filter, $filterAnd);

        /** @var AnrCheckStartedService $anrCheckStartedService */
        $anrCheckStartedService = $this->get('anrCheckStartedService');

        // Return both the scales, and also whether we can modify them
        return [$scales, $anrCheckStartedService->canChange($filterAnd['anr'])];
    }

    public function update(AnrSuperClass $anr, int $id, array $data)
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
