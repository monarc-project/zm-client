<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\InputFormatter\Recommendation\GetRecommendationsInputFormatter;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service\AnrRecommendationService;
use Monarc\FrontOffice\Validator\InputValidator\Recommendation\PatchRecommendationDataInputValidator;
use Monarc\FrontOffice\Validator\InputValidator\Recommendation\PostRecommendationDataInputValidator;

class ApiAnrRecommendationsController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private AnrRecommendationService $anrRecommendationService,
        private GetRecommendationsInputFormatter $getRecommendationsInputFormatter,
        private PostRecommendationDataInputValidator $postRecommendationDataInputValidator,
        private PatchRecommendationDataInputValidator $patchRecommendationDataInputValidator
    ) {
    }

    public function getList()
    {
        $formattedParams = $this->getFormattedInputParams($this->getRecommendationsInputFormatter);

        return $this->getPreparedJsonResponse([
            'count' => $this->anrRecommendationService->getCount($formattedParams),
            'recommendations' => $this->anrRecommendationService->getList($formattedParams)
        ]);
    }

    /**
     * @param string $id
     */
    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getPreparedJsonResponse($this->anrRecommendationService->getRecommendationData($anr, $id));
    }

    public function create($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $isBatchData = $this->isBatchData($data);
        $this->validatePostParams($this->postRecommendationDataInputValidator, $data, $isBatchData);

        $result = $isBatchData ? $this->anrRecommendationService->createList(
            $anr,
            $this->postRecommendationDataInputValidator->getValidDataSets()
        ) : $this->anrRecommendationService->create(
            $anr,
            $this->postRecommendationDataInputValidator->getValidData()
        );

        return $this->getSuccessfulJsonResponse(['id' => $isBatchData ? $result : $result->getUuid()]);
    }

    /**
     * @param string $id
     * @param array $data
     */
    public function patch($id, $data)
    {
        $this->validatePostParams($this->patchRecommendationDataInputValidator, $data);
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrRecommendationService->patch($anr, $id, $this->patchRecommendationDataInputValidator->getValidData());

        return $this->getSuccessfulJsonResponse();
    }

    /**
     * @param string $id
     */
    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrRecommendationService->delete($anr, $id);

        return $this->getSuccessfulJsonResponse();
    }
}
