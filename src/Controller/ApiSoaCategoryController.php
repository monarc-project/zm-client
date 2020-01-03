<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Exception\Exception;
use Zend\View\Model\JsonModel;

/**
 * Api ANR Categories Controller
 *
 * Class ApiAnrCategoriesController
 * @package Monarc\FrontOffice\Controller
 */
class ApiSoaCategoryController extends ApiAnrAbstractController
{
    protected $name = 'categories';
    protected $dependencies = ['anr', 'referential'];

    /**
     * @inheritdoc
     */
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
        $status = $this->params()->fromQuery('status', 1);
        $referential = $this->params()->fromQuery('referential');

        $filterAnd = ['anr' => $anrId];
        if ($status === 'all') {
            $filterAnd['status'] = (int)$status;
        }

        if ($referential) {
            $filterAnd['r.anr'] = $anrId;
            $filterAnd['r.uuid'] = $referential;
        }

        $service = $this->getService();

        $entities = $service->getList($page, $limit, $order, $filter, $filterAnd);
        if (count($this->dependencies)) {
            foreach ($entities as $key => $entity) {
                $this->formatDependencies($entities[$key], $this->dependencies);
            }
        }

        return new JsonModel(array(
            'count' => $service->getFilteredCount($filter, $filterAnd),
            $this->name => $entities
        ));
    }
}
