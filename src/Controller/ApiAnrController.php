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
use Monarc\FrontOffice\Service\AnrService;
use Monarc\FrontOffice\Validator\InputValidator\Anr\CreateAnrDataInputValidator;

class ApiAnrController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    private CreateAnrDataInputValidator $createAnrDataInputValidator;

    private AnrService $anrService;

    public function __construct(CreateAnrDataInputValidator $createAnrDataInputValidator, AnrService $anrService)
    {
        $this->createAnrDataInputValidator = $createAnrDataInputValidator;
        $this->anrService = $anrService;
    }

    public function getList()
    {
        $page = $this->params()->fromQuery('page');
        $limit = $this->params()->fromQuery('limit');
        $order = $this->params()->fromQuery('order');
        $filter = $this->params()->fromQuery('filter');

        $result = $this->anrService->getList($page, $limit, $order, $filter);
        // protected $dependencies = ['referentials'];
        if (count($this->dependencies)) {
            foreach ($result as $key => $entity) {
                $this->formatDependencies($result[$key], $this->dependencies);
            }
        }

        return $this->getPreparedJsonResponse([
            'count' => \count($result),
            'anrs' => $result,
        ]);
    }

    /**
     * @param array $data
     */
    public function create($data)
    {
        $this->validatePostParams($this->createAnrDataInputValidator, $data);

        $anr = $this->anrService->createFromModelToClient($this->createAnrDataInputValidator->getValidData());

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
}
