<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Model\Entity\Anr;
use Monarc\FrontOffice\Service\AnrObjectService;
use Laminas\View\Model\JsonModel;

/**
 * TODO: refactor me...
 */
class ApiAnrLibraryController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    protected $name = 'categories';

    protected $dependencies = ['anr', 'parent'];

    public function getList()
    {
        // TODO: Attach the middleware.
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        /** @var AnrObjectService $service */
        $service = $this->getService();
        $objectsCategories = $service->getLibraryTreeStructure($anr);

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
        // TODO: Anr object is from the attribute.
        $anrId = $this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Monarc\Core\Exception\Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        if (!isset($data['objectId'])) {
            throw new \Monarc\Core\Exception\Exception('objectId is missing');
        }

        /** @var AnrObjectService $service */
        $service = $this->getService();
        $id = $service->attachObjectToAnr($data['objectId'], $anrId);

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
            throw new \Monarc\Core\Exception\Exception('Anr id missing', 412);
        }

        /** @var AnrObjectService $service */
        $service = $this->getService();
        $service->detachObjectFromAnr($id, $anrId);

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
