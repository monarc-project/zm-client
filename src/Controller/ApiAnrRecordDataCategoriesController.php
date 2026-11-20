<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Service\AnrRecordDataCategoryService;
use Monarc\FrontOffice\InputFormatter\Record\GetRecordDataCategoriesInputFormatter;

class ApiAnrRecordDataCategoriesController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private GetRecordDataCategoriesInputFormatter $getRecordDataCategoriesInputFormatter,
        private AnrRecordDataCategoryService $anrRecordDataCategoryService
    ) {
    }

    public function getList()
    {
        $params = $this->getFormattedInputParams($this->getRecordDataCategoriesInputFormatter);

        return $this->getPreparedJsonResponse([
            'count' => $this->anrRecordDataCategoryService->getCount($params),
            'record-data-categories' => $this->anrRecordDataCategoryService->getList($params),
        ]);
    }
}
