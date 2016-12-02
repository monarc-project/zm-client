<?php
namespace MonarcFO\Controller;

use MonarcFO\Service\SnapshotService;
use Zend\View\Model\JsonModel;

class ApiSnapshotRestoreController extends \MonarcCore\Controller\AbstractController
{
    protected $name = 'snapshots';

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
        if (!isset($data['anr'])) {
            throw new \Exception('Anr missing', 412);
        }

        $anrId = $data['anr'];

        /** @var SnapshotService $service */
        $service = $this->getService();
        $service->restore($anrId);

        return new JsonModel(
            array(
                'status' => 'ok',
                'id' => $anrId,
            )
        );
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

