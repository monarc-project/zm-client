<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Validator\InputValidator\MeasureMeasure\PostMeasureMeasureDataInputValidator;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service\AnrMeasureMeasureService;

class ApiAnrMeasuresMeasuresController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private AnrMeasureMeasureService $anrMeasureMeasureService,
        private PostMeasureMeasureDataInputValidator $postMeasureMeasureDataInputValidator
    ) {
    }

    public function getList()
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $measuresLinksData = $this->anrMeasureMeasureService->getList($anr);

        return $this->getPreparedJsonResponse([
            'count' => \count($measuresLinksData),
            'measuresLinks' => $measuresLinksData,
        ]);
    }

    public function create($data)
    {
        $isBatchData = $this->isBatchData($data);
        $this->validatePostParams($this->postMeasureMeasureDataInputValidator, $data, $isBatchData);

        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        if ($this->isBatchData($data)) {
            $this->anrMeasureMeasureService->createList(
                $anr,
                $this->postMeasureMeasureDataInputValidator->getValidDataSets()
            );
        } else {
            $this->anrMeasureMeasureService->create($anr, $this->postMeasureMeasureDataInputValidator->getValidData());
        }

        return $this->getSuccessfulJsonResponse();
    }

    public function deleteList($data)
    {
        $masterMeasureUuid = $this->params()->fromQuery('masterMeasureUuid');
        $linkedMeasureUuid = $this->params()->fromQuery('linkedMeasureUuid');
        $this->validatePostParams(
            $this->postMeasureMeasureDataInputValidator,
            compact('masterMeasureUuid', 'linkedMeasureUuid')
        );

        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->anrMeasureMeasureService->delete($anr, $masterMeasureUuid, $linkedMeasureUuid);

        return $this->getSuccessfulJsonResponse();
    }
}
