<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Validator\InputValidator\InstanceRiskOp\PatchInstanceRiskOpDataInputValidator;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service\AnrInstanceRiskOpService;
use Monarc\FrontOffice\Validator\InputValidator\InstanceRiskOp\PostSpecificInstanceRiskOpDataInputValidator;
use Monarc\FrontOffice\Validator\InputValidator\InstanceRiskOp\UpdateInstanceRiskOpDataInputValidator;

class ApiAnrInstancesRisksOpController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private AnrInstanceRiskOpService $anrInstanceRiskOpService,
        private PostSpecificInstanceRiskOpDataInputValidator $postSpecificInstanceRiskOpDataInputValidator,
        private UpdateInstanceRiskOpDataInputValidator $updateInstanceRiskOpDataInputValidator,
        private PatchInstanceRiskOpDataInputValidator $patchInstanceRiskOpDataInputValidator
    ) {
    }

    public function create($data)
    {
        $this->validatePostParams($this->postSpecificInstanceRiskOpDataInputValidator, $data);

        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $operationalInstanceRisk = $this->anrInstanceRiskOpService->createSpecificOperationalInstanceRisk(
            $anr,
            $this->postSpecificInstanceRiskOpDataInputValidator->getValidData()
        );

        return $this->getSuccessfulJsonResponse(['id' => $operationalInstanceRisk->getId()]);
    }

    /**
     * @param array $data
     */
    public function update($id, $data)
    {
        $this->validatePostParams($this->updateInstanceRiskOpDataInputValidator, $data);

        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $instanceRiskOp = $this->anrInstanceRiskOpService->update(
            $anr,
            (int)$id,
            $this->updateInstanceRiskOpDataInputValidator->getValidData()
        );

        return $this->getPreparedJsonResponse([
            'cacheBrutRisk' => $instanceRiskOp->getCacheBrutRisk(),
            'cacheNetRisk' => $instanceRiskOp->getCacheNetRisk(),
            'cacheTargetedRisk' => $instanceRiskOp->getCacheTargetedRisk(),
        ]);
    }
    /**
     * @param array $data
     */
    public function patch($id, $data)
    {
        $this->validatePostParams($this->patchInstanceRiskOpDataInputValidator, $data);
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $instanceRiskOp = $this->anrInstanceRiskOpService->updateScaleValue(
            $anr,
            (int)$id,
            $this->patchInstanceRiskOpDataInputValidator->getValidData()
        );

        return $this->getPreparedJsonResponse([
            'cacheBrutRisk' => $instanceRiskOp->getCacheBrutRisk(),
            'cacheNetRisk' => $instanceRiskOp->getCacheNetRisk(),
            'cacheTargetedRisk' => $instanceRiskOp->getCacheTargetedRisk(),
        ]);
    }

    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrInstanceRiskOpService->delete($anr, (int)$id);

        return $this->getSuccessfulJsonResponse();
    }
}
