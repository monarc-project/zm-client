<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */
namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\InputFormatter\Referential\GetReferentialInputFormatter;
use Monarc\Core\Validator\InputValidator\Referential\PostReferentialDataInputValidator;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service\AnrReferentialService;

class ApiAnrReferentialsController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private AnrReferentialService $anrReferentialService,
        private GetReferentialInputFormatter $getReferentialInputFormatter,
        private PostReferentialDataInputValidator $postReferentialDataInputValidator
    ) {
    }

    public function getList()
    {
        $formatterParams = $this->getFormattedInputParams($this->getReferentialInputFormatter);

        return $this->getPreparedJsonResponse([
            'referentials' => $this->anrReferentialService->getList($formatterParams),
        ]);
    }

    /**
     * @param string $id
     */
    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getPreparedJsonResponse($this->anrReferentialService->getReferentialData($anr, $id));
    }

    /**
     * @param array $data
     */
    public function create($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->validatePostParams($this->postReferentialDataInputValidator, $data);


        return $this->getSuccessfulJsonResponse([
            'id' => $this->anrReferentialService->create(
                $anr,
                $this->postReferentialDataInputValidator->getValidData()
            )->getUuid(),
        ]);
    }

    /**
     * @param string $id
     * @param array $data
     */
    public function update($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->validatePostParams($this->postReferentialDataInputValidator, $data);

        $this->anrReferentialService->update($anr, $id, $this->postReferentialDataInputValidator->getValidData());

        return $this->getSuccessfulJsonResponse();
    }

    /**
     * @param string $id
     */
    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->anrReferentialService->delete($anr, $id);

        return $this->getSuccessfulJsonResponse();
    }
}
