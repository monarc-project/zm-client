<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractService;

/**
 * Soa Service
 *
 * Class SoaService
 * @package Monarc\FrontOffice\Service
 */
class SoaService extends AbstractService
{
    protected $table;
    protected $entity;
    protected $anrTable;
    protected $userAnrTable;
    protected $soaScaleCommentTable;
    protected $dependencies = ['anr', 'measure'];

    /**
     * @inheritdoc
     */
    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null)
    {
        list($filterJoin, $filterLeft, $filtersCol) = $this->get('entity')->getFiltersForService();

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
        if ($order == "m.code" || $order == "-m.code") {
            $desc = ($order == "-m.code");
            if (!$desc) {
                uasort($data, function ($a, $b) {
                    return strnatcmp($a['measure']->get('code'), $b['measure']->get('code'));
                });
            } else {
                uasort($data, function ($a, $b) {
                    return strnatcmp($b['measure']->get('code'), $a['measure']->get('code'));
                });
            }
        }
        if ($limit != 0) {
            return array_slice($data, ($page - 1) * $limit, $limit, false);
        }

        return $data;
    }

    /**
     * @inheritdoc
     */
    public function getFilteredCount($filter = null, $filterAnd = null)
    {
        list($filterJoin, $filterLeft, $filtersCol) = $this->get('entity')->getFiltersForService();

        return $this->get('table')->countFiltered(
            $this->parseFrontendFilter($filter, $filtersCol),
            $filterAnd,
            $filterJoin,
            $filterLeft
        );
    }

    public function patchSoa(int $id, array $data)
    {
        $table = $this->get('table');
        $soa = $table->findById($id);
        if (isset($data['remarks'])) {
            $soa->setRemarks($data['remarks']);
        }
        if (isset($data['evidences'])) {
            $soa->setEvidences($data['evidences']);
        }
        if (isset($data['actions'])) {
            $soa->setActions($data['actions']);
        }
        if (isset($data['EX'])) {
            $soa->setEx($data['EX']);
        }
        if (isset($data['LR'])) {
            $soa->setLr($data['LR']);
        }
        if (isset($data['CO'])) {
            $soa->setCo($data['CO']);
        }
        if (isset($data['BR'])) {
            $soa->setBr($data['BR']);
        }
        if (isset($data['BP'])) {
            $soa->setBp($data['BP']);
        }
        if (isset($data['RRA'])) {
            $soa->setRra($data['RRA']);
        }
        if (isset($data['soaScaleComment'])) {
            $soaScaleCommentTable = $this->get('soaScaleCommentTable');
            $soaScaleComment = $soaScaleCommentTable->findById($data['soaScaleComment']);
            $soa->setSoaScaleComment($soaScaleComment);
        }
        $table->saveEntity($soa);
    }
}
