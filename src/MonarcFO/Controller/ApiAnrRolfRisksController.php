<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
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

    protected $dependencies = ['tags', 'measures'];

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

    public function update($id, $data)
    {
      $anr = $this->params()->fromRoute("anrid");
      $measures= array();
      foreach ($data['measures'] as $measure) {
        $measures[] = ['anr' => $anr, 'uuid' => $measure];
      }
      $data['measures'] = $measures;
      return parent::update($id, $data);
    }

    public function patch($id, $data)
    {
      $anr = $this->params()->fromRoute("anrid");
      $measures= array();
      foreach ($data['measures'] as $measure) {
        $measures[] = ['anr' => $anr, 'uuid' => $measure];
      }
      $data['measures'] = $measures;
      return parent::patch($id, $data);
    }
}
