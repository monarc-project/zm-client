<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Entity\ObjectSuperClass;
use Monarc\Core\Model\Table\InstanceTable as CoreInstanceTable;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\Instance;

/**
 * Class InstanceTable
 * @package Monarc\FrontOffice\Model\Table
 */
class InstanceTable extends CoreInstanceTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, $connectedUserService);
    }

    public function getEntityClass(): string
    {
        return Instance::class;
    }

    /**
     * @return Instance[]
     */
    public function findByAnrAndObject(AnrSuperClass $anr, ObjectSuperClass $object)
    {
        return $this->getRepository()
            ->createQueryBuilder('i')
            ->innerJoin('i.object', 'obj')
            ->where('i.anr = :anr')
            ->andWhere('obj.uuid = :obj_uuid')
            ->andWhere('obj.anr = :obj_anr')
            ->setParameter('anr', $anr)
            ->setParameter('obj_uuid', $object->getUuid())
            ->setParameter('obj_anr', $object->getAnr())
            ->getQuery()
            ->getResult();
    }
}
