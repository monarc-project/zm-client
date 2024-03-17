<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManager;
use Monarc\Core\Table\AbstractTable;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Delivery;

class DeliveryTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, string $entityName = Delivery::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    public function findLastByAnrAndDocType(Anr $anr, int $docType): Delivery
    {
        return $this->getRepository()->createQueryBuilder('d')
            ->where('d.anr = :anr')
            ->andWhere('d.docType = :docType')
            ->setParameter('anr', $anr)
            ->setParameter('docType', $docType)
            ->orderBy('createdAt', Criteria::DESC)
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();
    }
}
