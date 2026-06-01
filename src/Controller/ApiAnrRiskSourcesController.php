<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\InputFormatter\RiskSource\GetRiskSourcesInputFormatter;
use Monarc\Core\Validator\InputValidator\RiskSource\PatchRiskSourceDataInputValidator;
use Monarc\Core\Validator\InputValidator\RiskSource\PostRiskSourceDataInputValidator;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\RiskSource;
use Monarc\FrontOffice\Service\RiskSourceService;

class ApiAnrRiskSourcesController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private GetRiskSourcesInputFormatter $getRiskSourcesInputFormatter,
        private RiskSourceService $riskSourceService,
        private PostRiskSourceDataInputValidator $postRiskSourceDataInputValidator,
        private PatchRiskSourceDataInputValidator $patchRiskSourceDataInputValidator
    ) {
    }

    public function getList()
    {
        $formattedParams = $this->getFormattedInputParams($this->getRiskSourcesInputFormatter);

        return $this->getPreparedJsonResponse([
            'count' => $this->riskSourceService->getCount($formattedParams),
            'riskSources' => array_map(
                [$this, 'prepareRiskSourceData'],
                $this->riskSourceService->getList($formattedParams)
            ),
        ]);
    }

    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getPreparedJsonResponse(
            $this->prepareRiskSourceData($this->riskSourceService->get($anr, (int)$id))
        );
    }

    public function create($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->validatePostParams($this->postRiskSourceDataInputValidator, $data);

        return $this->getSuccessfulJsonResponse($this->prepareRiskSourceData(
            $this->riskSourceService->create($anr, $this->postRiskSourceDataInputValidator->getValidData())
        ));
    }

    public function update($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->validatePostParams($this->patchRiskSourceDataInputValidator, $data);

        return $this->getSuccessfulJsonResponse($this->prepareRiskSourceData(
            $this->riskSourceService->update($anr, (int)$id, $this->patchRiskSourceDataInputValidator->getValidData())
        ));
    }

    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->riskSourceService->delete($anr, (int)$id);

        return $this->getSuccessfulJsonResponse();
    }

    private function prepareRiskSourceData(RiskSource $riskSource): array
    {
        return [
            'id' => $riskSource->getId(),
            'label' => $riskSource->getLabel(),
            'isDefault' => $riskSource->isDefault(),
            'isActive' => $riskSource->isActive(),
        ];
    }
}
