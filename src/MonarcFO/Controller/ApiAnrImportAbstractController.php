<?php
namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractController;
use Zend\View\Model\JsonModel;

/**
 * Api Anr Import Abstract Controller
 *
 * Class ApiAnrImportAbstractController
 * @package MonarcFO\Controller
 */
abstract class ApiAnrImportAbstractController extends AbstractController
{
    /**
     * Create
     *
     * @param mixed $data
     * @return JsonModel
     * @throws \Exception
     */
    public function create($data)
    {
        $service = $this->getService();

        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Exception('Anr id missing', 412);
        }

        $files = $this->params()->fromFiles('file');
        if (empty($files)) {
            throw new \Exception('File missing', 412);
        }
        $data['file'] = $files;

        list($ids, $errors) = $service->importFromFile($anrId, $data);

        return new JsonModel([
            'status' => 'ok',
            'id' => $ids,
            'errors' => $errors,
        ]);
    }

    public function getList()
    {
        return $this->methodNotAllowed();
    }

    public function get($id)
    {
        return $this->methodNotAllowed();
    }

    public function delete($id)
    {
        return $this->methodNotAllowed();
    }

    public function deleteList($data)
    {
        return $this->methodNotAllowed();
    }

    public function update($id, $data)
    {
        return $this->methodNotAllowed();
    }

    public function patch($id, $data)
    {
        return $this->methodNotAllowed();
    }
}

