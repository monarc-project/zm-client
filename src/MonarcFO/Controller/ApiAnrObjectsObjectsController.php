<?php
namespace MonarcFO\Controller;

use MonarcCore\Model\Entity\AbstractEntity;
use MonarcCore\Service\ObjectObjectService;
use Zend\View\Model\JsonModel;

/**
 * Api ANR Objects Objects Controller
 *
 * Class ApiAnrObjectsController
 * @package MonarcFO\Controller
 */
class ApiAnrObjectsObjectsController extends ApiAnrAbstractController
{
    /**
     * Update
     *
     * @param mixed $id
     * @param mixed $data
     * @return JsonModel
     */
    public function update($id, $data)
    {
        // This works a little different that regular PUT calls - here we just expect a parameter "move" with the
        // value "up" or "down" to move the object. We can't edit any other field anyway.
        if (isset($data['move']) && in_array($data['move'], ['up', 'down'])) {
            /** @var ObjectObjectService $service */
            $service = $this->getService();
            $service->moveObject($id, $data['move']);
        }

        return new JsonModel(['status' => 'ok']);
    }

    /**
     * Create
     *
     * @param mixed $data
     * @return JsonModel
     * @throws \Exception
     */
    public function create($data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        $id = $this->getService()->create($data, true, AbstractEntity::FRONT_OFFICE);

        return new JsonModel([
            'status' => 'ok',
            'id' => $id,
        ]);
    }

    public function get($id)
    {
        return $this->methodNotAllowed();
    }

    public function getList()
    {
        return $this->methodNotAllowed();
    }

    public function patch($id, $data)
    {
        return $this->methodNotAllowed();
    }
}