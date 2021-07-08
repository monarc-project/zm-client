<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Model\Table;

use Doctrine\ORM\EntityManager;
use Monarc\Core\Model\Table\TranslationTable as CoreTranslationTable;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Translation;

class TranslationTable extends CoreTranslationTable
{
    public function __construct(EntityManager $entityManager, $entityName = Translation::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    /**
     * @return Translation[]
     */
    public function findByAnr(Anr $anr): array
    {
        return $this->getRepository()->createQueryBuilder('t')
            ->where('t.anr = :anr')
            ->setParameter('anr', $anr)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Translation[]
     */
    public function findByAnrIndexedByKey(Anr $anr): array
    {
        return $this->getRepository()->createQueryBuilder('t', 't.key')
            ->where('t.anr = :anr')
            ->setParameter('anr', $anr)
            ->getQuery()
            ->getResult();
    }

    public function findByAnrKeyAndLanguage(Anr $anr, string $key, string $lang): Translation
    {
        return $this->getRepository()->createQueryBuilder('t')
            ->where('t.anr = :anr')
            ->andWhere('t.key = :key')
            ->andWhere('t.lang = :lang')
            ->setParameter('key', $key)
            ->setParameter('lang', $lang)
            ->setParameter('anr', $anr)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
