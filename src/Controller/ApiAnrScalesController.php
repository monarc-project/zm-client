<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Validator\InputValidator\Scale\UpdateScalesDataInputValidator;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service\AnrScaleService;

class ApiAnrScalesController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private AnrScaleService $anrScaleService,
        private UpdateScalesDataInputValidator $updateScalesDataInputValidator
    ) {
    }

    public function getList()
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $scalesData = $this->anrScaleService->getList($anr);

        return $this->getPreparedJsonResponse([
            'count' => \count($scalesData),
            'scales' => $scalesData,
            'canChange' => !$this->anrScaleService->areScalesNotEditable($anr),
        ]);
    }

    /**
     * @param array $data
     */
    public function update($id, $data)
    {
        $this->validatePostParams($this->updateScalesDataInputValidator, $data);

        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrScaleService->update($anr, (int)$id, $this->updateScalesDataInputValidator->getValidData());

        return $this->getSuccessfulJsonResponse();
    }
}
