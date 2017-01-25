<?php
namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractController;
use MonarcCore\Service\ObjectCategoryService;
use Zend\View\Model\JsonModel;

/**
 * Api Anr Library Category Controller
 *
 * Class ApiAnrLibraryCategoryController
 * @package MonarcFO\Controller
 */
class ApiAnrLibraryCategoryController extends AbstractController
{
    protected $name = 'categories';

    /**
     * Patch
     *
     * @param mixed $id
     * @param mixed $data
     * @return JsonModel
     */
    public function patch($id, $data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');

        $data['anr'] = $anrId;

        /** @var ObjectCategoryService $service */
        $service = $this->getService();
        $service->patchLibraryCategory($id, $data);

        return new JsonModel(['status' => 'ok']);
    }

    public function getList()
    {
        return $this->methodNotAllowed();
    }

    public function get($id)
    {
        return $this->methodNotAllowed();
    }

    public function create($data)
    {
        return $this->methodNotAllowed();
    }

    public function update($id, $data)
    {
        return $this->methodNotAllowed();
    }

    public function delete($id)
    {
        return $this->methodNotAllowed();

    }
}