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

    public function deleteAllByFather(MonarcObject $monarcObject): void
    {
        $this->getRepository()->createQueryBuilder('oo')
            ->delete('oo')
            ->where('oo.father = :father')
            ->setParameter('father', $monarcObject)
            ->getQuery()
            ->execute();
    }

    public function findMaxPositionByAnrAndFather(Anr $anr, MonarcObject $father): int
    {
        return (int)$this->getRepository()
            ->createQueryBuilder('oo')
            ->select('MAX(oo.position)')
            ->where('oo.anr = :anr')
            ->andWhere('oo.father = :father')
            ->setParameter('anr', $anr)
            ->setParameter('father', $father)
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
