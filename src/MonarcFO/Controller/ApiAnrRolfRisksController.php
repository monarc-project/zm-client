<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api ANR Rolf Risks Controller
 *
 * Class ApiAnrRolfRisksController
 * @package MonarcFO\Controller
 */
class ApiAnrRolfRisksController extends ApiAnrAbstractController
{
    protected $name = 'risks';

    protected $dependencies = ['categories', 'tags'];

    /**
     * @inheritdoc
     */
    public function getList()
    {
        $page = $this->params()->fromQuery('page');
        $limit = $this->params()->fromQuery('limit');
        $order = $this->params()->fromQuery('order');
        $filter = $this->params()->fromQuery('filter');
        $category = $this->params()->fromQuery('category');
        $tag = $this->params()->fromQuery('tag');
        $anr = $this->params()->fromRoute("anrid");

        /** @var RolfRiskService $service */
        $service = $this->getService();

        $rolfRisks = $service->getListSpecific($page, $limit, $order, $filter, $category, $tag, $anr);
        foreach ($rolfRisks as $key => $rolfRisk) {

            $rolfRisk['categories']->initialize();
            $rolfCategories = $rolfRisk['categories']->getSnapshot();
            $rolfRisks[$key]['categories'] = [];
            foreach ($rolfCategories as $rolfCategory) {
                $rolfRisks[$key]['categories'][] = $rolfCategory->getJsonArray();
            }

            $rolfRisk['tags']->initialize();
            $rolfTags = $rolfRisk['tags']->getSnapshot();
            $rolfRisks[$key]['tags'] = [];
            foreach ($rolfTags as $rolfTag) {
                $rolfRisks[$key]['tags'][] = $rolfTag->getJsonArray();
            }
        }

        return new JsonModel([
            'count' => $service->getFilteredSpecificCount($page, $limit, $order, $filter, $category, $tag, $anr),
            $this->name => $rolfRisks
        ]);
    }
}