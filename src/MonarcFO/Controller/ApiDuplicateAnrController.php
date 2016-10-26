<?php
namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractController;
use MonarcFO\Service\AnrService;
use Zend\View\Model\JsonModel;

class ApiDuplicateAnrController extends AbstractController
{
    protected $name = 'anrs';

    public function create($data)
    {
        /** @var AnrService $service */
        $service = $this->getService();

        if (!isset($data['anr'])) {
            throw new \Exception('Anr missing', 412);
        }

        $id = $service->duplicateAnr(intval($data['anr']));

        return new JsonModel(
            array(
                'status' => 'ok',
                'id' => $id,
            )
        );
    }
}

