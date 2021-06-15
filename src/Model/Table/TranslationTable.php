<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Model\Table;

use Doctrine\ORM\EntityManager;
use Monarc\Core\Model\Table\AbstractTable;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Translation;

class TranslationTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager)
    {
        parent::__construct($entityManager, Translation::class);
    }

    /**
     * @return Translation[]
     */
    public function findByAnrAndTypesIndexedByKey(Anr $anr, array $types): array
    {
        $queryBuilder = $this->getRepository()->createQueryBuilder('t', 't.key');

        return $queryBuilder
            ->where('t.anr = :anr')
            ->andWhere($queryBuilder->expr()->in('t.type', $types))
            ->setParameter('anr', $anr)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Translation[]
     */
    public function findByAnrAndTypesAndLanguageIndexedByKey(Anr $anr, array $types, string $lang): array
    {
        $queryBuilder = $this->getRepository()->createQueryBuilder('t', 't.key');

        return $queryBuilder
            ->where('t.anr = :anr')
            ->andWhere($queryBuilder->expr()->in('t.type', $types))
            ->andWhere('t.lang = :lang')
            ->setParameter('anr', $anr)
            ->setParameter('lang', $lang)
            ->getQuery()
            ->getResult();
    }

    public function deleteListByKey(array $keys): void
    {
        $queryBuilder = $this->getRepository()->createQueryBuilder('t');
        $queryBuilder
            ->delete()
            ->Where($queryBuilder->expr()->in('t.key', $keys))
            ->getQuery()
            ->getResult();
    }
}
