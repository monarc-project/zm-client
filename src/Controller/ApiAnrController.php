<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\AbstractController;
use Monarc\FrontOffice\Service\AnrService;
use Laminas\View\Model\JsonModel;

/**
 * Api Anr Controller
 *
 * Class ApiAnrController
 * @package Monarc\FrontOffice\Controller
 */
class ApiAnrController extends AbstractController
{
    protected $name = 'anrs';
    protected $dependencies = ['referentials'];

    /**
     * @inheritdoc
     */
    public function getList()
    {
        $page = $this->params()->fromQuery('page');
        $limit = $this->params()->fromQuery('limit');
        $order = $this->params()->fromQuery('order');
        $filter = $this->params()->fromQuery('filter');

        /** @var AnrService $service */
        $service = $this->getService();
        $entities = $service->getList($page, $limit, $order, $filter);
        if (count($this->dependencies)) {
            foreach ($entities as $key => $entity) {
                $this->formatDependencies($entities[$key], $this->dependencies);
            }
        }

        return new JsonModel([
            'count' => count($entities),
            $this->name => $entities
        ]);
    }

    /**
     * @inheritdoc
     */
    public function create($data)
    {
        /** @var AnrService $service */
        $service = $this->getService();

        if (!isset($data['model'])) {
            throw new \Monarc\Core\Exception\Exception('Model missing', 412);
        }

        $newAnr = $service->createFromModelToClient($data);

        return new JsonModel([
            'status' => 'ok',
            'id' => $newAnr->getId(),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        /** @var AnrService $service */
        $service = $this->getService();

        if (isset($data['referentials'])) {
            $service->updateReferentials($data);
            unset($data['referentials']);
        }

        $service->patch($id, $data);

        return new JsonModel([
            'status' => 'ok',
            'id' => $id,
        ]);
    }
}
