<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\AbstractController;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\DeliveriesModelsService;
use Monarc\FrontOffice\Export\Controller\Traits\ExportResponseControllerTrait;

class ApiDeliveriesModelsController extends AbstractController
{
    use ControllerRequestResponseHandlerTrait;
    use ExportResponseControllerTrait;

    public function __construct(DeliveriesModelsService $deliveriesModelsService)
    {
        parent::__construct($deliveriesModelsService);
    }

    public function create($data)
    {
        $service = $this->getService();
        $file = $this->request->getFiles()->toArray();
        for ($i = 1; $i <= 4; ++$i) {
            unset($data['path' . $i]);
            if (!empty($file['file'][$i])) {
                $file['file'][$i]['name'] = $data['category'] . ".docx";
                $data['path' . $i] = $file['file'][$i];
            }
        }
        $service->create($data);

        return $this->getSuccessfulJsonResponse();
    }

    public function getList()
    {
        $page = $this->params()->fromQuery('page');
        $limit = $this->params()->fromQuery('limit');
        $order = $this->params()->fromQuery('order');
        $filter = $this->params()->fromQuery('filter');

        $service = $this->getService();

        $entities = $service->getList($page, $limit, $order, $filter);

        $pathModel = getenv('APP_CONF_DIR') ?: '';
        foreach ($entities as $k => $v) {
            for ($i = 1; $i <= 4; $i++) {
                $entities[$k]['filename' . $i] = '';
                if (!empty($entities[$k]['path' . $i])) {
                    // $name = explode('_',pathinfo($entities[$k]['path'.$i],PATHINFO_BASENAME));
                    // unset($name[0]);
                    $currentPath = $pathModel . $entities[$k]['path' . $i];
                    if (!file_exists($currentPath)) {
                        $entities[$k]['filename' . $i] = '';
                        $entities[$k]['path' . $i] = '';
                    } else {
                        $entities[$k]['filename' . $i] = pathinfo($entities[$k]['path' . $i], PATHINFO_BASENAME);
                        $entities[$k]['path' . $i] = './api/deliveriesmodels/' . $v['id'] . '?lang=' . $i;
                    }
                }
            }
        }

        return $this->getPreparedJsonResponse([
            'count' => \count($entities),
            'deliveriesmodels' => $entities,
        ]);
    }

    public function get($id)
    {
        $entity = $this->getService()->getEntity($id);
        if (!empty($entity)) {
            $lang = $this->params()->fromQuery('lang', 1);
            $pathModel = getenv('APP_CONF_DIR') ?: '';
            $currentPath = $pathModel . $entity['path' . $lang];
            if (isset($entity['path' . $lang]) && file_exists($currentPath)) {
                $name = pathinfo($currentPath)['basename'];

                $fileContents = file_get_contents($currentPath);
                if ($fileContents !== false) {
                    return $this->prepareWordExportResponse($name, $fileContents);
                }
            }
        }

        throw new Exception('Document template not found');
    }

    public function update($id, $data)
    {
        $service = $this->getService();
        $file = $this->request->getFiles()->toArray();

        for ($i = 1; $i <= 4; ++$i) {
            unset($data['path' . $i]);
            if (!empty($file['file'][$i])) {
                $data['path' . $i] = $file['file'][$i];
            }
        }
        $service->update($id, $data);

        return $this->getSuccessfulJsonResponse();
    }

    public function patch($id, $data)
    {
        $service = $this->getService();
        $file = $this->request->getFiles()->toArray();
        for ($i = 1; $i <= 4; ++$i) {
            unset($data['path' . $i]);
            if (!empty($file['file'][$i])) {
                $data['path' . $i] = $file['file'][$i];
            }
        }
        $service->patch($id, $data);

        return $this->getSuccessfulJsonResponse();
    }
}
