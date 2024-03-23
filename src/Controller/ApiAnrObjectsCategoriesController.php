<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\InputFormatter\ObjectCategory\ObjectCategoriesInputFormatter;
use Monarc\Core\Validator\InputValidator\ObjectCategory\PostObjectCategoryDataInputValidator;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service\AnrObjectCategoryService;

class ApiAnrObjectsCategoriesController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private AnrObjectCategoryService $anrObjectCategoryService,
        private ObjectCategoriesInputFormatter $objectCategoriesInputFormatter,
        private PostObjectCategoryDataInputValidator $postObjectCategoryDataInputValidator
    ) {
    }

    public function getList()
    {
        $formattedParams = $this->getFormattedInputParams($this->objectCategoriesInputFormatter);
        $this->objectCategoriesInputFormatter->prepareCategoryFilter();

        return $this->getPreparedJsonResponse([
            'count' => $this->anrObjectCategoryService->getCount($formattedParams),
            'categories' => $this->anrObjectCategoryService->getList($formattedParams),
        ]);
    }

    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getPreparedJsonResponse($this->anrObjectCategoryService->getObjectCategoryData($anr, (int)$id));
    }

    /**
     * @param array $data
     */
    public function create($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $category = $this->anrObjectCategoryService->create($anr, $data);

        return $this->getSuccessfulJsonResponse(['categ' => ['id' => $category->getId()]]);
    }

    /**
     * @param array $data
     */
    public function update($id, $data)
    {
        $this->validatePostParams($this->postObjectCategoryDataInputValidator, $data);

        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrObjectCategoryService->update($anr, (int)$id, $data);

        return $this->getSuccessfulJsonResponse();
    }

    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrObjectCategoryService->delete($anr, (int)$id);

        return $this->getSuccessfulJsonResponse();
    }
}
