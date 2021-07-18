<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Doctrine\ORM\EntityNotFoundException;
use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Entity\ScaleSuperClass;
use Monarc\Core\Model\Table\ScaleTable as CoreScaleTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\FrontOffice\Model\Entity\Scale;

/**
 * Class ScaleTable
 * @package Monarc\FrontOffice\Model\Table
 */
class ScaleTable extends CoreScaleTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, $connectedUserService);

        $this->entityClass = Scale::class;
    }

    public function findByAnrAndType(AnrSuperClass $anr, int $type): Scale
    {
        $scale = $this->getRepository()
            ->createQueryBuilder('s')
            ->where('s.anr = :anr')
            ->andWhere('s.type = :type')
            ->setParameter('anr', $anr)
            ->setParameter('type', $type)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($scale === null) {
            throw new EntityNotFoundException(
                sprintf('Scale of type "%d" doesn\'t exist in anr ID: "%d"', $type, $anr->getId())
            );
        }

        return $scale;
    }
}
