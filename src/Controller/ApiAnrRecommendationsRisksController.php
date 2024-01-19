<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\InputFormatter\RecommendationRisk\GetRecommendationRisksInputFormatter;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Service\AnrRecommendationRiskService;
use Monarc\FrontOffice\Validator\InputValidator\RecommendationRisk\PatchRecommendationRiskDataInputValidator;
use Monarc\FrontOffice\Validator\InputValidator\RecommendationRisk\PostRecommendationRiskDataInputValidator;

class ApiAnrRecommendationsRisksController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private AnrRecommendationRiskService $anrRecommendationRiskService,
        private PostRecommendationRiskDataInputValidator $postRecommendationRiskDataInputValidator,
        private PatchRecommendationRiskDataInputValidator $patchRecommendationRiskDataInputValidator,
        private GetRecommendationRisksInputFormatter $getRecommendationRisksInputFormatter
    ) {}

    public function getList()
    {
        $formattedParams = $this->getFormattedInputParams($this->getRecommendationRisksInputFormatter);

        return $this->getPreparedJsonResponse([
            'count' => $this->anrRecommendationRiskService->getCount($formattedParams),
            'recommendations-risks' => $this->anrRecommendationRiskService->getList($formattedParams)
        ]);
    }

    /**
     * @param array $data
     */
    public function create($data)
    {
        $this->validatePostParams($this->postRecommendationRiskDataInputValidator, $data);

        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $recommendationRisk = $this->anrRecommendationRiskService->create(
            $anr,
            $this->postRecommendationRiskDataInputValidator->getValidData()
        );

        return $this->getSuccessfulJsonResponse(['id' => $recommendationRisk->getId()]);
    }

    /**
     * @param array $data
     */
    public function patch($id, $data)
    {
        $this->validatePostParams($this->patchRecommendationRiskDataInputValidator, $data);

        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->anrRecommendationRiskService->patch(
            $anr,
            (int)$id,
            $this->patchRecommendationRiskDataInputValidator->getValidData()
        );

        return $this->getSuccessfulJsonResponse();
    }

    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrRecommendationRiskService->delete($anr, (int)$id);

        return $this->getSuccessfulJsonResponse();
    }
}
