<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\InputFormatter\Object\GetObjectInputFormatter;
use Monarc\FrontOffice\Import\Service\ObjectImportService;
use Monarc\FrontOffice\InputFormatter\Object\GetObjectsInputFormatter;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service\AnrObjectService;
use Monarc\FrontOffice\Validator\InputValidator\Object\PostObjectDataInputValidator;

class ApiAnrObjectsController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    private AnrObjectService $anrObjectService;

    private ObjectImportService $objectImportService;

    private GetObjectsInputFormatter $getObjectsInputFormatter;

    private GetObjectInputFormatter $getObjectInputFormatter;

    private PostObjectDataInputValidator $postObjectDataInputValidator;

    public function __construct(
        AnrObjectService $anrObjectService,
        ObjectImportService $objectImportService,
        GetObjectsInputFormatter $getObjectsInputFormatter,
        GetObjectInputFormatter $getObjectInputFormatter,
        PostObjectDataInputValidator $postObjectDataInputValidator
    ) {
        $this->anrObjectService = $anrObjectService;
        $this->objectImportService = $objectImportService;
        $this->getObjectsInputFormatter = $getObjectsInputFormatter;
        $this->getObjectInputFormatter = $getObjectInputFormatter;
        $this->postObjectDataInputValidator = $postObjectDataInputValidator;
    }

    public function getList()
    {
        $formattedInputParams = $this->getFormattedInputParams($this->getObjectsInputFormatter);

        return $this->getPreparedJsonResponse([
            'count' => $this->anrObjectService->getCount($formattedInputParams),
            'objects' => $this->anrObjectService->getList($formattedInputParams),
        ]);
    }

    /**
     * @param string $id
     */
    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $formattedInputParams = $this->getFormattedInputParams($this->getObjectInputFormatter);

        return $this->getPreparedJsonResponse($this->anrObjectService->getObjectData($anr, $id, $formattedInputParams));
    }

    /**
     * @param array $data
     */
    public function create($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        if (!empty($data['mosp'])) {
            $monarcObject = $this->objectImportService->importFromArray($anr, $data);

            return $monarcObject !== null
                ? $this->getSuccessfulJsonResponse(['id' => $monarcObject->getUuid()])
                : $this->getSuccessfulJsonResponse();
        }

        $isBatchData = $this->isBatchData($data);
        $this->validatePostParams($this->postObjectDataInputValidator, $data, $isBatchData);

        $result = $isBatchData
            ? $this->anrObjectService->createList($anr, $this->postObjectDataInputValidator->getValidDataSets())
            : $this->anrObjectService->create($anr, $this->postObjectDataInputValidator->getValidData());

        return $this->getSuccessfulJsonResponse(['id' => $isBatchData ? $result : $result->getUuid()]);
    }

    /**
     * @param string $id
     * @param array $data
     */
    public function update($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->validatePostParams($this->postObjectDataInputValidator, $data);

        $this->anrObjectService->update($anr, $id, $this->postObjectDataInputValidator->getValidData());

        return $this->getSuccessfulJsonResponse();
    }

    /**
     * @param string $id
     */
    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrObjectService->delete($anr, $id);

        return $this->getSuccessfulJsonResponse();
    }

    /**
     * Called to validate the library objects detaching possibility.
     */
    public function parentsAction()
    {
        $anr = $this->getRequest()->getAttribute('anr');
        $objectUuid = $this->params()->fromRoute('id');

        return $this->getPreparedJsonResponse($this->anrObjectService->getParentsInAnr($anr, $objectUuid));
    }
}
