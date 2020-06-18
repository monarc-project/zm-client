<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Monarc\Core\Model\Table\AbstractEntityTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Soa;
use Monarc\FrontOffice\Model\Entity\SoaCategory;

/**
 * Class SoaTable
 * @package Monarc\FrontOffice\Model\Table
 */
class SoaTable extends AbstractEntityTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, Soa::class, $connectedUserService);
    }

    /**
     * @return Soa[]
     */
    public function findByAnrAndSoaCategory(Anr $anr, SoaCategory $soaCategory, array $order = [])
    {
        $queryBuilder = $this->getRepository()
            ->createQueryBuilder('s')
            ->innerJoin('s.measure', 'm')
            ->where('s.anr = :anr')
            ->andWhere('m.category = :category')
            ->setParameter('anr', $anr)
            ->setParameter('category', $soaCategory);

        if (!empty($order)) {
            foreach ($order as $filed => $direction) {
                $queryBuilder->addOrderBy($filed, $direction);
            }
        }

        return $queryBuilder->getQuery()->getResult();
    }
}
