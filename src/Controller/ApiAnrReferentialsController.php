<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Service\AnrReferentialService;

class ApiAnrReferentialsController extends ApiAnrAbstractController
{
    protected $dependencies = ['anr', 'measures'];

    public function __construct(AnrReferentialService $anrReferentialService)
    {
        parent::__construct($anrReferentialService);
    }

    public function getList()
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }
        $page = $this->params()->fromQuery('page');
        $limit = $this->params()->fromQuery('limit');
        $order = $this->params()->fromQuery('order');
        $filter = $this->params()->fromQuery('filter');
        $filterAnd = ['anr' => $anrId];

        $service = $this->getService();

        $entities = $service->getList($page, $limit, $order, $filter, $filterAnd);
        foreach ($entities as $key => $entity) {
            $this->formatDependencies($entities[$key], $this->dependencies);
        }

        return $this->getPreparedJsonResponse([
            'count' => $service->getFilteredCount($filter, $filterAnd),
            'referentials' => $entities,
        ]);
    }

    public function get($id)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        $entity = $this->getService()->getEntity(['anr' => $anrId, 'uuid' => $id]);

        $this->formatDependencies($entity, $this->dependencies);

        return $this->getPreparedJsonResponse($entity);
    }

    public function update($id, $data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        $newId = ['anr'=> $anrId, 'uuid' => $data['uuid']];

        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        $this->getService()->update($newId, $data);

        return $this->getSuccessfulJsonResponse();
    }

    public function delete($id)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        $newId = ['anr'=> $anrId, 'uuid' => $id];

        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }

        $this->getService()->delete($newId);

        return $this->getSuccessfulJsonResponse();
    }
}
