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
    public function deleteAllByParent(MonarcObject $monarcObject): void
    {
        $childrenObjects = $this->getRepository()->createQueryBuilder('oo')
            ->innerJoin('oo.parent', 'parent')
            ->where('parent.uuid = :parentUuuid')
            ->andWhere('parent.anr = :parentAnr')
            ->setParameter('parentUuuid', $monarcObject->getUuid())
            ->setParameter('parentAnr', $monarcObject->getAnr())
            ->getQuery()
            ->getResult();

        if (!empty($childrenObjects)) {
            foreach ($childrenObjects as $childObject) {
                $this->getDb()->getEntityManager()->remove($childObject);
            }
            $this->getDb()->flush();
        }
    }

    public function findMaxPositionByAnrAndParent(Anr $anr, MonarcObject $parent): int
    {
        return (int)$this->getRepository()->createQueryBuilder('oo')
            ->select('MAX(oo.position)')
            ->innerJoin('oo.parent', 'parent')
            ->where('oo.anr = :anr')
            ->andWhere('parent.uuid = :parentUuid')
            ->andWhere('parent.anr = :parentAnr')
            ->setParameter('anr', $anr)
            ->setParameter('parentUuid', $parent->getUuid())
            ->setParameter('parentAnr', $anr)
            ->getQuery()
            ->getSingleScalarResult();
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
