<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
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

    protected $dependencies = ['tags'];

    /**
     * @inheritdoc
     */
    public function getList()
    {
        $page = $this->params()->fromQuery('page');
        $limit = $this->params()->fromQuery('limit');
        $order = $this->params()->fromQuery('order');
        $filter = $this->params()->fromQuery('filter');
        $tag = $this->params()->fromQuery('tag');
        $anr = $this->params()->fromRoute("anrid");

        /** @var RolfRiskService $service */
        $service = $this->getService();

        $rolfRisks = $service->getListSpecific($page, $limit, $order, $filter, $tag, $anr);
        foreach ($rolfRisks as $key => $rolfRisk) {

            $rolfRisk['tags']->initialize();
            $rolfTags = $rolfRisk['tags']->getSnapshot();
            $rolfRisks[$key]['tags'] = [];
            foreach ($rolfTags as $rolfTag) {
                $rolfRisks[$key]['tags'][] = $rolfTag->getJsonArray();
            }
        }

        return new JsonModel([
            'count' => $service->getFilteredSpecificCount($page, $limit, $order, $filter, $tag, $anr),
            $this->name => $rolfRisks
        ]);
    }
}
