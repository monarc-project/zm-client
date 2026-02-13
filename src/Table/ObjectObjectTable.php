<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManager;
use Monarc\Core\Table\AbstractTable;
use Monarc\Core\Table\Interfaces\PositionUpdatableTableInterface;
use Monarc\Core\Table\Traits\PositionIncrementTableTrait;
use Monarc\FrontOffice\Entity\MonarcObject;
use Monarc\FrontOffice\Entity\ObjectObject;

class ObjectObjectTable extends AbstractTable implements PositionUpdatableTableInterface
{
    use PositionIncrementTableTrait;

    public function __construct(EntityManager $entityManager, string $entityName = ObjectObject::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    public function deleteLinksByParentObject(MonarcObject $object, bool $saveInDb = false): void
    {
        $childrenObjectsLinks = $this->getRepository()->createQueryBuilder('oo')
            ->innerJoin('oo.parent', 'parent')
            ->where('parent.uuid = :parentUuid')
            ->andWhere('parent.anr = :anr')
            ->setParameter('parentUuid', $object->getUuid())
            ->setParameter('anr', $object->getAnr())
            ->getQuery()
            ->getResult();

        if (!empty($childrenObjectsLinks)) {
            foreach ($childrenObjectsLinks as $childObjectLink) {
                $object->removeChildLink($childObjectLink);
                $this->entityManager->remove($childObjectLink);
            }
            if ($saveInDb) {
                $this->flush();
            }
        }
    }

    public function findByParentObjectAndPosition(MonarcObject $parentObject, int $position): ?ObjectObject
    {
        return $this->getRepository()->createQueryBuilder('oo')
            ->innerJoin('oo.parent', 'parent')
            ->where('parent.uuid = :parentUuid')
            ->andWhere('parent.anr = :anr')
            ->andWhere('oo.position = :position')
            ->setParameter('parentUuid', $parentObject->getUuid())
            ->setParameter('anr', $parentObject->getAnr())
            ->setParameter('position', $position)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByParentAndChild(MonarcObject $parentObject, MonarcObject $childObject): ?ObjectObject
    {
        return $this->getRepository()->createQueryBuilder('oo')
            ->innerJoin('oo.parent', 'parent')
            ->innerJoin('oo.child', 'child')
            ->where('parent.uuid = :parentUuid')
            ->andWhere('parent.anr = :anr')
            ->andWhere('child.uuid = :childUuid')
            ->setParameter('parentUuid', $parentObject->getUuid())
            ->setParameter('anr', $parentObject->getAnr())
            ->setParameter('childUuid', $childObject->getUuid())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
