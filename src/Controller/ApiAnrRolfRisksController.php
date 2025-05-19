<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\InputFormatter\RolfRisk\GetRolfRisksInputFormatter;
use Monarc\Core\Validator\InputValidator\RolfRisk\PostRolfRiskDataInputValidator;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service\AnrRolfRiskService;

class ApiAnrRolfRisksController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private AnrRolfRiskService $anrRolfRiskService,
        private GetRolfRisksInputFormatter $rolfRisksInputFormatter,
        private PostRolfRiskDataInputValidator $postRolfRiskDataInputValidator
    ) {
    }

    public function getList()
    {
        $formattedParams = $this->getFormattedInputParams($this->rolfRisksInputFormatter);

        return $this->getPreparedJsonResponse([
            'count' => $this->anrRolfRiskService->getCount($formattedParams),
            'risks' => $this->anrRolfRiskService->getList($formattedParams),
        ]);
    }

    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getPreparedJsonResponse($this->anrRolfRiskService->getRolfRiskData($anr, (int)$id));
    }

    /**
     * @param array $data
     */
    public function create($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $isBatchData = $this->isBatchData($data);
        $this->validatePostParams(
            $this->postRolfRiskDataInputValidator->setIncludeFilter(['anr' => $anr]),
            $data,
            $isBatchData
        );

        if ($this->isBatchData($data)) {
            return $this->getSuccessfulJsonResponse([
                'id' => $this->anrRolfRiskService
                    ->createList($anr, $this->postRolfRiskDataInputValidator->getValidDataSets()),
            ]);
        }

        return $this->getSuccessfulJsonResponse([
            'id' => $this->anrRolfRiskService
                ->create($anr, $this->postRolfRiskDataInputValidator->getValidData())->getId(),
        ]);
    }

    /**
     * @param array $data
     */
    public function update($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->validatePostParams(
            $this->postRolfRiskDataInputValidator
                ->setIncludeFilter(['anr' => $anr])
                ->setExcludeFilter(['id' => (int)$id]),
            $data
        );

        $this->anrRolfRiskService->update($anr, (int)$id, $this->postRolfRiskDataInputValidator->getValidData());

        return $this->getSuccessfulJsonResponse();
    }

    public function patchList($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->anrRolfRiskService->linkMeasuresToRisks($anr, $data['fromReferential'], $data['toReferential']);

        return $this->getSuccessfulJsonResponse();
    }

    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->anrRolfRiskService->delete($anr, (int)$id);

        return $this->getSuccessfulJsonResponse();
    }

    public function deleteList($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->anrRolfRiskService->deleteList($anr, $data);

        return $this->getSuccessfulJsonResponse();
    }
}
