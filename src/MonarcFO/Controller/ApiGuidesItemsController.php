<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractController;
use Zend\View\Model\JsonModel;

/**
 * Api Guides Items Controller
 *
 * Class ApiGuidesItemsController
 * @package MonarcFO\Controller
 */
class ApiGuidesItemsController extends AbstractController
{
    protected $name = 'guides-items';

    protected $dependencies = [];

    /**
     * @inheritdoc
     */
    public function getList()
    {
        $page = $this->params()->fromQuery('page');
        $limit = $this->params()->fromQuery('limit');
        $order = $this->params()->fromQuery('order');
        $filter = $this->params()->fromQuery('filter');
        $guide = $this->params()->fromQuery('guide');
        if (!is_null($guide)) {
            $filterAnd = ['guide' => (int)$guide];
        } else {
            $filterAnd = [];
        }

        $service = $this->getService();

        $entities = $service->getList($page, $limit, $order, $filter, $filterAnd);
        if (count($this->dependencies)) {
            foreach ($entities as $key => $entity) {
                $this->formatDependencies($entities[$key], $this->dependencies);
            }
        }

        return new JsonModel([
            'count' => $service->getFilteredCount($filter, $filterAnd),
            $this->name => $entities
        ]);
    }
}