<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractController;
use MonarcCore\Model\Entity\AbstractEntity;
use MonarcFO\Service\AnrObjectService;
use Zend\View\Model\JsonModel;

/**
 * Api Anr Library Controller
 *
 * Class ApiAnrLibraryController
 * @package MonarcFO\Controller
 */
class ApiAnrLibraryController extends AbstractController
{
    protected $name = 'categories';

    protected $dependencies = ['anr', 'parent'];

    /**
     * @inheritdoc
     */
    public function getList()
    {
        $anrId = $this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }

        /** @var AnrObjectService $service */
        $service = $this->getService();
        $objectsCategories = $service->getCategoriesLibraryByAnr($anrId);

        $this->formatDependencies($objectsCategories, $this->dependencies);

        $fields = ['id', 'label1', 'label2', 'label3', 'label4', 'position', 'objects', 'child'];
        $objectsCategories = $this->recursiveArray($objectsCategories, null, 0, $fields);

        return new JsonModel([
            $this->name => $objectsCategories
        ]);
    }

    /**
     * @inheritdoc
     */
    public function create($data)
    {
        $anrId = $this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        if (!isset($data['objectId'])) {
            throw new \MonarcCore\Exception\Exception('objectId is missing');
        }

        /** @var ObjectService $service */
        $service = $this->getService();
        $id = $service->attachObjectToAnr($data['objectId'], $anrId, null, null, AbstractEntity::FRONT_OFFICE);

        return new JsonModel([
            'status' => 'ok',
            'id' => $id,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function delete($id)
    {
        $anrId = $this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }

        /** @var ObjectService $service */
        $service = $this->getService();
        $service->detachObjectToAnr($id, $anrId);

        return new JsonModel([
            'status' => 'ok'
        ]);
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        return $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        return $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        return $this->methodNotAllowed();
    }
}