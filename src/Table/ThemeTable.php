<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\EntityManager;
use Monarc\Core\Table\AbstractTable;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Theme;

class ThemeTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, string $entityName = Theme::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    public function findByAnrAndLabel(Anr $anr, string $labelKey, string $labelValue): ?Theme
    {
        return $this->getRepository()->createQueryBuilder('t')
            ->where('t.anr = :anr')
            ->andWhere('t.' . $labelKey . ' = :' . $labelKey)
            ->setParameter('anr', $anr)
            ->setParameter($labelKey, $labelValue)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
