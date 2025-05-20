<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\InputFormatter\Measure\GetMeasuresInputFormatter;
use Monarc\FrontOffice\Validator\InputValidator\Measure\PostMeasureDataInputValidator;
use Monarc\FrontOffice\Validator\InputValidator\Measure\UpdateMeasureDataInputValidator;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service\AnrMeasureService;

class ApiAnrMeasuresController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private AnrMeasureService $anrMeasureService,
        private GetMeasuresInputFormatter $getMeasuresInputFormatter,
        private PostMeasureDataInputValidator $postMeasureDataInputValidator,
        private UpdateMeasureDataInputValidator $updateMeasureDataInputValidator
    ) {
    }

    public function getList()
    {
        $formattedParams = $this->getFormattedInputParams($this->getMeasuresInputFormatter);

        return $this->getPreparedJsonResponse([
            'count' => $this->anrMeasureService->getCount($formattedParams),
            'measures' => $this->anrMeasureService->getList($formattedParams),
        ]);
    }

    /**
     * @param string $id
     */
    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getPreparedJsonResponse($this->anrMeasureService->getMeasureData($anr, $id));
    }

    public function create($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $isBatchData = $this->isBatchData($data);
        $this->validatePostParams(
            $this->postMeasureDataInputValidator->setIncludeFilter(['anr' => $anr]),
            $data,
            $isBatchData
        );

        if ($this->isBatchData($data)) {
            return $this->getSuccessfulJsonResponse([
                'id' => $this->anrMeasureService
                    ->createList($anr, $this->postMeasureDataInputValidator->getValidDataSets()),
            ]);
        }

        return $this->getSuccessfulJsonResponse([
            'id' => $this->anrMeasureService
                ->create($anr, $this->postMeasureDataInputValidator->getValidData())->getUuid(),
        ]);
    }

    /**
     * @param string $id
     * @param array $data
     */
    public function update($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->validatePostParams(
            $this->updateMeasureDataInputValidator
                ->setIncludeFilter(['anr' => $anr])
                ->setExcludeFilter(['uuid' => $id]),
            $data
        );

        $this->anrMeasureService->update($anr, $id, $this->updateMeasureDataInputValidator->getValidData());

        return $this->getSuccessfulJsonResponse();
    }

    /**
     * @param string $id
     */
    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrMeasureService->delete($anr, $id);

        return $this->getSuccessfulJsonResponse();
    }

    public function deleteList($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrMeasureService->deleteList($anr, $data);

        return $this->getSuccessfulJsonResponse();
    }
}
