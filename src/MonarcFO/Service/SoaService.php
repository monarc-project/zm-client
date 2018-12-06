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
  protected $measureService;
  protected $dependencies = ['anr', 'measure'];

  /**
   * @inheritdoc
   */
  public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null)
  {
      list($filterJoin,$filterLeft,$filtersCol) = $this->get('entity')->getFiltersForService();

      $data = $this->get('table')->fetchAllFiltered(
          array_keys($this->get('entity')->getJsonArray()),
          $page,
          0,
          $this->parseFrontendOrder($order),
          $this->parseFrontendFilter($filter, $filtersCol),
          $filterAnd,
          $filterJoin,
          $filterLeft
      );

      if ($order == "measure" || $order == "-measure") {
          $desc = ($order == "-measure");


          // Codes might be in xx.xx.xx format which need a numerical sorting instead of an alphabetical one
          $re = '/^([0-9]+\.)+[0-9]+$/m';
          usort($data, function ($a, $b) use ($re, $desc) {
              $a['measure']->code = trim($a['measure']->code);
              $b['measure']->code = trim($b['measure']->code);
              $a_match = (preg_match($re, $a['measure']->code) > 0);
              $b_match = (preg_match($re, $b['measure']->code) > 0);

              if ($a_match && $b_match) {
                  $a_values = explode('.', $a['measure']->code);
                  $b_values = explode('.', $b['measure']->code);

                  if (count($a_values) < count($b_values)) {
                      return $desc ? 1 : -1;
                  } else if (count($a_values) > count($b_values)) {
                      return $desc ? -1 : 1;
                  } else {
                      for ($i = 0; $i < count($a_values); ++$i) {
                          if ($a_values[$i] != $b_values[$i]) {
                              return $desc ? (intval($b_values[$i]) - intval($a_values[$i])) : (intval($a_values[$i]) - intval($b_values[$i]));
                          }
                      }
                      // If we reach here, all values are equal
                      return 0;
                  }


              } else if ($a_match && !$b_match) {
                  return $desc ? 1 : -1;
              } else if (!$a_match && $b_match) {
                  return $desc ? -1 : 1;
              } else {
                  return $desc ? strcmp($b_match, $a_match) : strcmp($a_match, $b_match);
              }
          });

      }

      return array_slice($data, ($page - 1) * $limit, $limit, false);

  }

  /**
   * @inheritdoc
   */
  public function getFilteredCount($filter = null, $filterAnd = null)
  {
      list($filterJoin,$filterLeft,$filtersCol) = $this->get('entity')->getFiltersForService();

      return $this->get('table')->countFiltered(
          $this->parseFrontendFilter($filter, $filtersCol),
          $filterAnd,
          $filterJoin,
          $filterLeft
      );
  }
}
