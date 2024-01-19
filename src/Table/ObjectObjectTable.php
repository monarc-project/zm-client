<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\EntityManager;
use Monarc\Core\Table\AbstractTable;
use Monarc\Core\Table\Interfaces\PositionUpdatableTableInterface;
use Monarc\Core\Table\Traits\PositionIncrementTableTrait;
use Monarc\FrontOffice\Model\Entity\MonarcObject;
use Monarc\FrontOffice\Model\Entity\ObjectObject;

class ObjectObjectTable extends AbstractTable implements PositionUpdatableTableInterface
{
    use PositionIncrementTableTrait;

    public function __construct(EntityManager $entityManager, string $entityName = ObjectObject::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    /**
     * @param MonarcObject $monarcObject
     */
    public function deleteAllByParent(MonarcObject $monarcObject): void
    {
        $childrenObjects = $this->getRepository()->createQueryBuilder('oo')
            ->innerJoin('oo.parent', 'parent')
            ->where('parent.uuid = :parentUuuid')
            ->andWhere('parent.anr = :parentAnr')
            ->setParameter('parentUuuid', $monarcObject->getUuid())
            ->setParameter('parentAnr', $monarcObject->getAnr())
            ->getQuery()
            ->getResult();

        if (!empty($childrenObjects)) {
            foreach ($childrenObjects as $childObject) {
                $this->entityManager->remove($childObject);
            }
            $this->flush();
        }
    }
}
