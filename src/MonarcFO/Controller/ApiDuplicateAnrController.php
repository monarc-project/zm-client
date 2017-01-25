<?php
namespace MonarcFO\Controller;

use MonarcFO\Model\Entity\Object;
use MonarcFO\Service\AnrService;
use Zend\View\Model\JsonModel;

/**
 * Api Duplicate Anr Controller
 *
 * Class ApiDuplicateAnrController
 * @package MonarcFO\Controller
 */
class ApiDuplicateAnrController extends \MonarcCore\Controller\AbstractController
{
    protected $name = 'anrs';

    /**
     * Create
     *
     * @param mixed $data
     * @return JsonModel
     * @throws \Exception
     */
    public function create($data)
    {
        /** @var AnrService $service */
        $service = $this->getService();

        if (!isset($data['anr'])) {
            throw new \Exception('Anr missing', 412);
        }

        $id = $service->duplicateAnr(intval($data['anr']), Object::SOURCE_CLIENT, null, $data);

        return new JsonModel([
            'status' => 'ok',
            'id' => $id,
        ]);
    }
}