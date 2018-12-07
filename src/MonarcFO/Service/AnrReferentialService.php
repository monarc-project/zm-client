<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractService;

/**
 * AnrReferential Service
 *
 * Class AnrReferentialService
 * @package MonarcFO\Service
 */
class AnrReferentialService extends AbstractService
{
    protected $dependencies = ['anr', 'amvs'];
    protected $filterColumns = ['uniqid', 'label1', 'label2', 'label3', 'label4'];
    protected $forbiddenFields = ['anr'];
    protected $userAnrTable;
    protected $selfCoreService;
    /**
     * @inheritdoc
     */
    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null)
    {
        file_put_contents('php://stderr', print_r('FO::ReferentialService::getList', TRUE).PHP_EOL);
        $data = $this->get('table')->fetchAllFiltered(
            array_keys($this->get('entity')->getJsonArray()),
            1,
            0,
            $this->parseFrontendOrder($order),
            $this->parseFrontendFilter($filter, $this->filterColumns),
            $filterAnd
        );

        return array_slice($data, ($page - 1) * $limit, $limit, false);
    }

    /**
     * Fetches and returns the list of referentials from the common database.
     * @param int $filter Keywords to search
     * @return array An array of available referentials from the common database (knowledge base)
     */
    public function getCommonReferentials($filter, $order)
    {

        // Fetch the referentials from the common database
        $selfCoreService = $this->get('selfCoreService');
        $referentials = $selfCoreService->getList(1, 25, $order, $filter, null);

        return $referentials;
    }
}
