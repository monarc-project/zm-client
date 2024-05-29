<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Validator\InputValidator\MeasureLink\PostMeasureLinkDataInputValidator;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service\AnrMeasureLinkService;

class ApiAnrMeasuresLinksController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private AnrMeasureLinkService $anrMeasureLinkService,
        private PostMeasureLinkDataInputValidator $postMeasureLinkDataInputValidator
    ) {
    }

    public function getList()
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $measuresLinksData = $this->anrMeasureLinkService->getList($anr);

        return $this->getPreparedJsonResponse([
            'count' => \count($measuresLinksData),
            'measuresLinks' => $measuresLinksData,
        ]);
    }

    public function create($data)
    {
        $isBatchData = $this->isBatchData($data);
        $this->validatePostParams($this->postMeasureLinkDataInputValidator, $data, $isBatchData);

        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        if ($this->isBatchData($data)) {
            $this->anrMeasureLinkService->createList(
                $anr,
                $this->postMeasureLinkDataInputValidator->getValidDataSets()
            );
        } else {
            $this->anrMeasureLinkService->create($anr, $this->postMeasureLinkDataInputValidator->getValidData());
        }

        return $this->getSuccessfulJsonResponse();
    }

    public function deleteList($data)
    {
        $masterMeasureUuid = $this->params()->fromQuery('masterMeasureUuid');
        $linkedMeasureUuid = $this->params()->fromQuery('linkedMeasureUuid');
        $this->validatePostParams(
            $this->postMeasureLinkDataInputValidator,
            compact('masterMeasureUuid', 'linkedMeasureUuid')
        );

        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->anrMeasureLinkService->delete($anr, $masterMeasureUuid, $linkedMeasureUuid);

        return $this->getSuccessfulJsonResponse();
    }
}
