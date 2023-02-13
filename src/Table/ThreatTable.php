<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\EntityManager;
use Monarc\Core\Model\Entity\ThreatSuperClass;
use Monarc\Core\Table\AbstractTable;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Threat;

class ThreatTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, string $entityName = Threat::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    public function isThreatsEvaluationStarted(Anr $anr): bool
    {
        return (bool)$this->getRepository()->createQueryBuilder('t')
            ->select('COUNT(t.uuid)')
            ->where('t.anr = :anr')
            ->setParameter(':anr', $anr)
            ->andWhere('t.qualification != -1')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function existsWithAnrAndCode(Anr $anr, string $code): bool
    {
        return $this->getRepository()->createQueryBuilder('t')
            ->where('t.anr = :anr')
            ->andWhere('t.code = :code')
            ->setParameter('anr', $anr)
            ->setParameter('code', $code)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult() !== null;
    }

    // TODO: ....
    public function saveEntity(ThreatSuperClass $threat, bool $flushAll = true): void
    {
        $em = $this->getDb()->getEntityManager();
        $em->persist($threat);
        if ($flushAll) {
            $em->flush();
        }
    }
}
