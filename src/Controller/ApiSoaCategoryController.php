<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\InputFormatter\SoaCategory\GetSoaCategoriesInputFormatter;
use Monarc\Core\Validator\InputValidator\SoaCategory\PostSoaCategoryDataInputValidator;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service\SoaCategoryService;

class ApiSoaCategoryController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private SoaCategoryService $soaCategoryService,
        private GetSoaCategoriesInputFormatter $getSoaCategoriesInputFormatter,
        private PostSoaCategoryDataInputValidator $postSoaCategoryDataInputValidator
    ) {
    }

    public function getList()
    {
        return $this->getPreparedJsonResponse([
            'categories' => $this->soaCategoryService->getList(
                $this->getFormattedInputParams($this->getSoaCategoriesInputFormatter)
            ),
        ]);
    }

    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getPreparedJsonResponse($this->soaCategoryService->getSoaCategoryData($anr, (int)$id));
    }

    /**
     * @param array $data
     */
    public function create($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->validatePostParams($this->postSoaCategoryDataInputValidator, $data);

        return $this->getSuccessfulJsonResponse([
            'id' => $this->soaCategoryService->create(
                $anr,
                $this->postSoaCategoryDataInputValidator->getValidData()
            )->getId(),
        ]);
    }

    /**
     * @param array $data
     */
    public function update($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->validatePostParams($this->postSoaCategoryDataInputValidator, $data);

        $this->soaCategoryService->update($anr, (int)$id, $this->postSoaCategoryDataInputValidator->getValidData());

        return $this->getSuccessfulJsonResponse();
    }

    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->soaCategoryService->delete($anr, (int)$id);

        return $this->getSuccessfulJsonResponse();
    }
}
