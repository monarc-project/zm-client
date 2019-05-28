<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractService;

/**
 * AnrRecommandationSet Service
 *
 * Class AnrRecommandationSetService
 * @package MonarcFO\Service
 */
class AnrRecommandationSetService extends AbstractService
{
    protected $dependencies = ['anr'];
    protected $filterColumns = ['uuid', 'label1', 'label2', 'label3', 'label4'];
    protected $userAnrTable;
    protected $selfCoreService;

    /**
     * @inheritdoc
     */
    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null)
    {
        $data = $this->get('table')->fetchAllFiltered(
            array_keys($this->get('entity')->getJsonArray()),
            1,
            0,
            $this->parseFrontendOrder($order),
            $this->parseFrontendFilter($filter, $this->filterColumns),
            $filterAnd
        );

        return $data;
        //return array_slice($data, ($page - 1) * $limit, $limit, false);
    }
}
