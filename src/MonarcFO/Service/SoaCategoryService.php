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
class SoaCategoryService extends \MonarcCore\Service\AbstractService
{
    protected $table;
    protected $entity;
    protected $anrTable;
    protected $userAnrTable;
    protected $filterColumns = ['label1', 'label2', 'label3', 'label4', 'code', 'status'];

    protected $dependencies = ['anr'];
    protected $forbiddenFields = [];

    /**
    * @inheritdoc
    */
    public function patch($id, $data)
    {
        // Filter unwanted fields
        $this->filterPatchFields($data);
        parent::patch($id, $data);
    }

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
        if ($order == "code" || $order == "-code") {
            $desc = ($order == "-code");
            usort($data, function ($a, $b) use ($re, $desc) {
                $a_match = (intval($a['code']));
                $b_match = (intval($b['code']));
                if ($a_match && $b_match) {
                    return $desc ? (intval($b['code']) - intval($a['code'])) : (intval($a['code']) - intval($b['code']));
                }  else {
                    return $desc ? strcmp($b_match, $a_match) : strcmp($a_match, $b_match);
                }
            });
        }
        return array_slice($data, ($page - 1) * $limit, $limit, false);
    }
}
