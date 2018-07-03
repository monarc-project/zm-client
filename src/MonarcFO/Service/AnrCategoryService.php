<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

/**

 * @package MonarcFO\Service
 */
class AnrCategoryService extends \MonarcCore\Service\AbstractService
{



      protected $table;
      protected $entity;
    protected $dependencies = ['anr'];
    protected $forbiddenFields = [];



    public function getList($page = 1, $limit = 25, $order , $filter = null, $filterAnd = null)
    {

        return $this->get('table')->fetchAllFiltered(
            array_keys($this->get('entity')->getJsonArray()),
            $page,
            $limit,
            $this->parseFrontendOrder($order),
            $this->parseFrontendFilter($filter, []),

            $filterAnd

        );
    }




}
