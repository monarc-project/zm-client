<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service\AnrRecommendationRiskService;
use Monarc\FrontOffice\Validator\InputValidator\RecommendationRisk\ValidateRecommendationRiskDataInputValidator;

class ApiAnrRecommendationsRisksValidateController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private AnrRecommendationRiskService $anrRecommendationRiskService,
        private ValidateRecommendationRiskDataInputValidator $validateRecommendationRiskDataInputValidator
    ) {}

    public function patch($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrRecommendationRiskService->validateFor($anr, (int)$id, $data);

        return $this->getSuccessfulJsonResponse();
    }
}
