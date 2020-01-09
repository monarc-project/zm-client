<?php

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Model\Entity\ObjectCategorySuperClass;
use Monarc\Core\Service\ObjectCategoryService;
use Monarc\FrontOffice\Model\Entity\AnrObjectCategory;
use Monarc\FrontOffice\Model\Table\AnrObjectCategoryTable;

class AnrObjectCategoryService extends ObjectCategoryService
{
    protected function unlinkCategoryFromAnr(ObjectCategorySuperClass $objectCategory): void
    {
        /** @var AnrObjectCategoryTable $anrObjectCategoryTable */
        $anrObjectCategoryTable = $this->get('anrObjectCategoryTable');

        $anrObjectCategory = $anrObjectCategoryTable->findOneByAnrAndObjectCategory(
            $objectCategory->getAnr(),
            $objectCategory
        );
        if ($anrObjectCategory !== null) {
            $anrObjectCategoryTable->delete($anrObjectCategory->getId());
        }
    }

    protected function linkCategoryToAnr(ObjectCategorySuperClass $objectCategory): void
    {
        /** @var AnrObjectCategoryTable $anrObjectCategoryTable */
        $anrObjectCategoryTable = $this->get('anrObjectCategoryTable');
        if ($anrObjectCategoryTable
                ->findOneByAnrAndObjectCategory($objectCategory->getAnr(), $objectCategory) === null
        ) {
            $anrObjectCategory = new AnrObjectCategory();
            $anrObjectCategory->setAnr($objectCategory->getAnr())->setCategory($objectCategory);

            $anrObjectCategory->setDbAdapter($anrObjectCategoryTable->getDb());
            $anrObjectCategory->exchangeArray(['implicitPosition' => 2]);

            $anrObjectCategoryTable->save($anrObjectCategory);
        }
    }
}
