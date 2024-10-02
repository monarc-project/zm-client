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
use Monarc\FrontOffice\Service\AnrService;
use Monarc\FrontOffice\Validator\InputValidator\Anr\CreateAnrDataInputValidator;

class ApiAnrController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private CreateAnrDataInputValidator $createAnrDataInputValidator,
        private AnrService $anrService
    ) {
    }

    public function getList()
    {
        $result = $this->anrService->getList();

        return $this->getPreparedJsonResponse([
            'count' => \count($result),
            'anrs' => $result,
        ]);
    }

    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getSuccessfulJsonResponse($this->anrService->getAnrData($anr));
    }

    /**
     * @param array $data
     */
    public function create($data)
    {
        $this->validatePostParams($this->createAnrDataInputValidator, $data);

        $anr = $this->anrService->createBasedOnModel($this->createAnrDataInputValidator->getValidData());

        return $this->getSuccessfulJsonResponse(['id' => $anr->getId()]);
    }

    /**
     * @param int $id
     * @param array $data
     */
    public function patch($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrService->patch($anr, $data);

        return $this->getSuccessfulJsonResponse(['id' => $id]);
    }

    public function delete(mixed $id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrService->delete($anr);

        return $this->getSuccessfulJsonResponse();
    }
}
