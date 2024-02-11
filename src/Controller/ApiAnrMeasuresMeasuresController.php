<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Service\AnrMeasureMeasureService;

class ApiAnrMeasuresMeasuresController extends ApiAnrAbstractController
{
    use ControllerRequestResponseHandlerTrait;

    protected $name = 'measuresmeasures';
    protected $dependencies = ['anr', 'father', 'child'];

    public function __construct(AnrMeasureMeasureService $anrMeasureMeasureService)
    {
        parent::__construct($anrMeasureMeasureService);
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
        $fatherId = $this->params()->fromQuery('fatherId');
        $childId = $this->params()->fromQuery('childId');
        $filterAnd = ['anr' => $anrId];

        if ($fatherId) {
            $filterAnd['father'] = $fatherId;
        }
        if ($childId) {
            $filterAnd['child'] = $childId;
        }

        $service = $this->getService();

        $entities = $service->getList($page, $limit, $order, $filter, $filterAnd);
        if (count($this->dependencies)) {
            foreach ($entities as $key => $entity) {
                $this->formatDependencies($entities[$key], $this->dependencies);
            }
        }

        return $this->getPreparedJsonResponse([
            'count' => $service->getFilteredCount($filter, $filterAnd),
            $this->name => $entities
        ]);
    }

    public function deleteList($data)
    {
        if (empty($data)) { //we delete one measuremeasure
            $anrId = (int)$this->params()->fromRoute('anrid');
            $fatherId = $this->params()->fromQuery('father');
            $childId = $this->params()->fromQuery('child');

            $this->getService()->delete(['anr' => $anrId, 'father' => $fatherId, 'child' => $childId]);

            return $this->getSuccessfulJsonResponse();
        }

        return parent::deleteList($data);
    }
}
