<?php
namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractController;
use MonarcFO\Service\AnrService;
use Zend\View\Model\JsonModel;

class ApiAnrController extends AbstractController
{
    protected $name = 'anrs';

    public function create($data)
    {
        /** @var AnrService $service */
        $service = $this->getService();

        if (!isset($data['model'])) {
            throw new \Exception('Model missing', 412);
        }

        $id = $service->createFromModelToClient($data['model']);

        return new JsonModel(
            array(
                'status' => 'ok',
                'id' => $id,
            )
        );
    }

    public function delete($id)
    {
        return $this->methodNotAllowed();
    }
}

