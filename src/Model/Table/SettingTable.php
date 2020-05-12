<?php declare(strict_types = 1);

namespace Monarc\FrontOffice\Model\Table;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityNotFoundException;
use Monarc\FrontOffice\Model\Entity\Setting;

class SettingTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager)
    {
        parent::__construct($entityManager, Setting::class);
    }

    public function findByName(string $name): Setting
    {
        $setting = $this->getRepository()
            ->createQueryBuilder('s')
            ->where('s.name = :name')
            ->setParameter('name', $name)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($setting === null) {
            throw new EntityNotFoundException(sprintf('Setting with name "%s" has not been found', $name));
        }

        return $setting;
    }
}
