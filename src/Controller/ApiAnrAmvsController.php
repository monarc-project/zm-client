<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Validator\InputValidator\Amv\PostAmvDataInputValidator;
use Monarc\FrontOffice\InputFormatter\Amv\GetAmvsInputFormatter;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Service\AnrAmvService;

class ApiAnrAmvsController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private AnrAmvService $anrAmvService,
        private GetAmvsInputFormatter $getAmvsInputFormatter,
        private PostAmvDataInputValidator $postAmvDataInputValidator
    ) {
    }

    public function getList()
    {
        $formattedParams = $this->getFormattedInputParams($this->getAmvsInputFormatter);

        return $this->getPreparedJsonResponse([
            'count' => $this->anrAmvService->getCount($formattedParams),
            'amvs' => $this->anrAmvService->getList($formattedParams)
        ]);
    }

    /**
     * @param string $id
     */
    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getPreparedJsonResponse($this->anrAmvService->getAmvData($anr, $id));
    }


    /**
     * @param array $data
     */
    public function create($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        if ($this->isBatchData($data)) {
            $result = $this->anrAmvService->createAmvItems($anr, $data);
        } else {
            $this->validatePostParams($this->postAmvDataInputValidator, $data);
            $result = $this->anrAmvService->create($anr, $data)->getUuid();
        }

        return $this->getSuccessfulJsonResponse(['id' => $result]);
    }

    /**
     * @param string $id
     * @param array $data
     */
    public function update($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrAmvService->update($anr, $id, $data);

        return $this->getSuccessfulJsonResponse();
    }

    /**
     * @param string $id
     * @param array $data
     */
    public function patch($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrAmvService->patch($anr, $id, $data);

        return $this->getSuccessfulJsonResponse();
    }

    public function patchList($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrAmvService->createLinkedAmvs($anr, $data['fromReferential'], $data['toReferential']);

        return $this->getSuccessfulJsonResponse();
    }

    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrAmvService->delete($anr, $id);

        return $this->getSuccessfulJsonResponse();
    }

    public function deleteList($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrAmvService->deleteList($anr, $data);

        return $this->getSuccessfulJsonResponse();
    }
}
