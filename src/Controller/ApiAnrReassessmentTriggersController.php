<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\InputFormatter\ReassessmentTrigger\GetReassessmentTriggersInputFormatter;
use Monarc\Core\Service\ReassessmentTriggerService as CoreReassessmentTriggerService;
use Monarc\Core\Validator\InputValidator\ReassessmentTrigger\PatchReassessmentTriggerDataInputValidator;
use Monarc\Core\Validator\InputValidator\ReassessmentTrigger\PostReassessmentTriggerDataInputValidator;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\ReassessmentTrigger;
use Monarc\FrontOffice\Service\ReassessmentTriggerService;

class ApiAnrReassessmentTriggersController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private GetReassessmentTriggersInputFormatter $getReassessmentTriggersInputFormatter,
        private ReassessmentTriggerService $reassessmentTriggerService,
        private CoreReassessmentTriggerService $coreReassessmentTriggerService,
        private PostReassessmentTriggerDataInputValidator $postReassessmentTriggerDataInputValidator,
        private PatchReassessmentTriggerDataInputValidator $patchReassessmentTriggerDataInputValidator
    ) {
    }

    public function getList()
    {
        $formattedParams = $this->getFormattedInputParams($this->getReassessmentTriggersInputFormatter);
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getPreparedJsonResponse([
            'count' => $this->reassessmentTriggerService->getCount($formattedParams),
            'reassessmentTriggers' => array_map(
                [$this, 'prepareReassessmentTriggerData'],
                $this->reassessmentTriggerService->getList($formattedParams)
            ),
            'availableReassessmentTriggers' => $this->coreReassessmentTriggerService->getSelectionData(
                $anr->getLanguageCode()
            ),
        ]);
    }

    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getPreparedJsonResponse(
            $this->prepareReassessmentTriggerData($this->reassessmentTriggerService->get($anr, (int)$id))
        );
    }

    public function create($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->validatePostParams($this->postReassessmentTriggerDataInputValidator, $data);

        return $this->getSuccessfulJsonResponse($this->prepareReassessmentTriggerData(
            $this->reassessmentTriggerService->create(
                $anr,
                $this->postReassessmentTriggerDataInputValidator->getValidData()
            )
        ));
    }

    public function update($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->validatePostParams($this->patchReassessmentTriggerDataInputValidator, $data);

        return $this->getSuccessfulJsonResponse($this->prepareReassessmentTriggerData(
            $this->reassessmentTriggerService->update(
                $anr,
                (int)$id,
                $this->patchReassessmentTriggerDataInputValidator->getValidData()
            )
        ));
    }

    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->reassessmentTriggerService->delete($anr, (int)$id);

        return $this->getSuccessfulJsonResponse();
    }

    private function prepareReassessmentTriggerData(ReassessmentTrigger $reassessmentTrigger): array
    {
        return [
            'id' => $reassessmentTrigger->getId(),
            'triggerType' => $reassessmentTrigger->getTriggerType(),
            'description' => $reassessmentTrigger->getDescription(),
            'isActive' => $reassessmentTrigger->isActive(),
            'position' => $reassessmentTrigger->getPosition(),
        ];
    }
}
