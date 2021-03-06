<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Table\AnrObjectCategoryTable as CoreAnrObjectCategoryTable;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\AnrObjectCategory;

/**
 * Class AnrObjectCategoryTable
 * @package Monarc\FrontOffice\Model\Table
 */
class AnrObjectCategoryTable extends CoreAnrObjectCategoryTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, $connectedUserService);
    }

    public function getEntityClass(): string
    {
        return AnrObjectCategory::class;
    }

    public function findMaxPositionByAnr(AnrSuperClass $anr): int
    {
        return (int)$this->getRepository()
            ->createQueryBuilder('aoc')
            ->select('MAX(aoc.position)')
            ->where('aoc.anr = :anr')
            ->setParameter('anr', $anr)
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function saveEntity(AnrObjectCategory $anrObjectCategory, bool $flushAll = true): void
    {
        $em = $this->getDb()->getEntityManager();
        $em->persist($anrObjectCategory);
        if ($flushAll) {
            $em->flush();
        }
    }
}
