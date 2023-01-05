<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Monarc\Core\Model\Entity\SoaCategorySuperClass;
use Monarc\Core\Model\Table\AbstractEntityTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Referential;
use Monarc\FrontOffice\Model\Entity\SoaCategory;

/**
 * Class CategoryTable
 * @package Monarc\FrontOffice\Model\Table
 */
class SoaCategoryTable extends AbstractEntityTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, SoaCategory::class, $connectedUserService);
    }

    /**
     * @return SoaCategory[]
     */
    public function findByAnr(Anr $anr): array
    {
        return $this->getRepository()->createQueryBuilder('sc')
            ->select('sc', 'ref')
            ->innerJoin('sc.referential', 'ref')
            ->where('sc.anr = :anr')
            ->setParameter('anr', $anr)
            ->getQuery()
            ->getResult();
    }

    public function saveEntity(SoaCategorySuperClass $soaCategory, bool $flushAll = true): void
    {
        $em = $this->getDb()->getEntityManager();
        $em->persist($soaCategory);
        if ($flushAll) {
            $em->flush();
        }
    }
}
