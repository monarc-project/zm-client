<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Monarc\Core\Model\Entity\ObjectObjectSuperClass;
use Monarc\Core\Model\Table\ObjectObjectTable as CoreObjectObjectTable;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\MonarcObject;
use Monarc\FrontOffice\Model\Entity\ObjectObject;

/**
 * Class ObjectObjectTable
 * @package Monarc\FrontOffice\Model\Table
 */
class ObjectObjectTable extends CoreObjectObjectTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, $connectedUserService);
    }

    public function getEntityClass(): string
    {
        return ObjectObject::class;
    }

    /**
     * Unfortunately, due to the 2 fields multi relation, we can't make it in a single query.
     *
     * @param MonarcObject $monarcObject
     */
    public function deleteAllByFather(MonarcObject $monarcObject): void
    {
        $childrenObjects = $this->getRepository()->createQueryBuilder('oo')
            ->innerJoin('oo.father', 'father')
            ->where('father.uuid = :fatherUuuid')
            ->andWhere('father.anr = :fatherAnr')
            ->setParameter('fatherUuuid', $monarcObject->getUuid())
            ->setParameter('fatherAnr', $monarcObject->getAnr())
            ->getQuery()
            ->getResult();

        if (!empty($childrenObjects)) {
            foreach ($childrenObjects as $childObject) {
                $this->getDb()->getEntityManager()->remove($childObject);
            }
            $this->getDb()->flush();
        }
    }

    public function findMaxPositionByAnrAndFather(Anr $anr, MonarcObject $father): int
    {
        return (int)$this->getRepository()->createQueryBuilder('oo')
            ->select('MAX(oo.position)')
            ->innerJoin('oo.father', 'father')
            ->where('oo.anr = :anr')
            ->andWhere('father.uuid = :fatherUuid')
            ->andWhere('father.anr = :fatherAnr')
            ->setParameter('anr', $anr)
            ->setParameter('fatherUuid', $father->getUuid())
            ->setParameter('fatherAnr', $anr)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return ObjectObject[]
     */
    public function findChildrenByFather(MonarcObject $father, array $order = []): array
    {
        $queryBuilder = $this->getRepository()->createQueryBuilder('oo')
            ->innerJoin('oo.father', 'father')
            ->where('father.uuid = :fatherUuid')
            ->andWhere('father.anr = :fatherAnr')
            ->setParameter('fatherUuid', $father->getUuid())
            ->setParameter('fatherAnr', $father->getAnr());

        if (!empty($order)) {
            foreach ($order as $field => $direction) {
                $queryBuilder->orderBy('oo.' . $field, $direction);
            }
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function saveEntity(ObjectObjectSuperClass $objectObject, bool $flushAll = true): void
    {
        $em = $this->getDb()->getEntityManager();
        $em->persist($objectObject);
        if ($flushAll) {
            $em->flush();
        }
    }
}
