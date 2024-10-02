<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Validator\InputValidator\ObjectComposition\CreateDataInputValidator;
use Monarc\Core\Validator\InputValidator\ObjectComposition\MovePositionDataInputValidator;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service\AnrObjectObjectService;

class ApiAnrObjectsObjectsController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private AnrObjectObjectService $anrObjectObjectService,
        private CreateDataInputValidator $createDataInputValidator,
        private MovePositionDataInputValidator $movePositionDataInputValidator
    ) {
    }

    /**
     * @param array $data
     */
    public function create($data)
    {
        $this->validatePostParams($this->createDataInputValidator, $data);

        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $objectComposition = $this->anrObjectObjectService->create(
            $anr,
            $this->createDataInputValidator->getValidData()
        );

        return $this->getSuccessfulJsonResponse(['id' => $objectComposition->getId()]);
    }

    /**
     * @param array $data
     */
    public function update($id, $data)
    {
        $this->validatePostParams($this->movePositionDataInputValidator, $data);

        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrObjectObjectService->shiftPositionInComposition(
            $anr,
            (int)$id,
            $this->movePositionDataInputValidator->getValidData()
        );

        return $this->getSuccessfulJsonResponse();
    }

    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrObjectObjectService->delete($anr, (int)$id);

        return $this->getSuccessfulJsonResponse();
    }
}
