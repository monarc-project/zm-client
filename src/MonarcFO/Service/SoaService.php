<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

/**
 * Soa Service
 *
 * Class SoaService
 * @package MonarcFO\Service
 */
 class SoaService extends \MonarcCore\Service\AbstractService
 {
  protected $table;
  protected $entity;
  protected $anrTable;
  protected $userAnrTable;
  protected $dependencies = ['anr', 'measure'];

  /**
 * @inheritdoc
 */
public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null)
{
    list($filterJoin,$filterLeft,$filtersCol) = $this->get('entity')->getFiltersForService();
    return $this->get('table')->fetchAllFiltered(
        array_keys($this->get('entity')->getJsonArray()),
        $page,
        $limit,
        $this->parseFrontendOrder($order),
        $this->parseFrontendFilter($filter, $filtersCol),
        $filterAnd,
        $filterJoin,
        $filterLeft
    );
}
}
