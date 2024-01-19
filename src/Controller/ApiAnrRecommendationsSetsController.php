<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Service\AnrRecommendationSetService;
use Monarc\FrontOffice\Validator\InputValidator\RecommendationSet\PostRecommendationSetDataInputValidator;

class ApiAnrRecommendationsSetsController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private AnrRecommendationSetService $anrRecommendationSetService,
        private PostRecommendationSetDataInputValidator $postRecommendationSetDataInputValidator
    ) {}

    public function getList()
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $recommendationSetsList = $this->anrRecommendationSetService->getList($anr);

        return $this->getPreparedJsonResponse([
            'count' => \count($recommendationSetsList),
            'recommendations-sets' => $recommendationSetsList,
        ]);
    }

    /**
     * @param string $id
     */
    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getPreparedJsonResponse($this->anrRecommendationSetService->getRecommendationSetData($anr, $id));
    }

    /**
     * @param array $data
     */
    public function create($data)
    {
        $this->validatePostParams($this->postRecommendationSetDataInputValidator, $data);

        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $recommendationSet = $this->anrRecommendationSetService->create(
            $anr,
            $this->postRecommendationSetDataInputValidator->getValidData()
        );

        return $this->getSuccessfulJsonResponse(['id' => $recommendationSet->getUuid()]);
    }

    /**
     * @param string $id
     * @param array $data
     */
    public function patch($id, $data)
    {
        $this->validatePostParams($this->postRecommendationSetDataInputValidator, $data);

        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrRecommendationSetService->patch(
            $anr,
            $id,
            $this->postRecommendationSetDataInputValidator->getValidData()
        );

        return $this->getSuccessfulJsonResponse();
    }

    /**
     * @param string $id
     */
    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrRecommendationSetService->delete($anr, $id);

        return $this->getSuccessfulJsonResponse();
    }
}
