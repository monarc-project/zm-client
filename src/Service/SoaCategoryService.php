<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\InputFormatter\FormattedInputParams;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Table\ReferentialTable;
use Monarc\FrontOffice\Table\SoaCategoryTable;

class SoaCategoryService
{
    public function __construct(private SoaCategoryTable $soaCategoryTable, private ReferentialTable $referentialTable)
    {
    }

    public function getList(FormattedInputParams $params): array
    {
        $result = [];
        /** @var Entity\SoaCategory $soaCategory */
        foreach ($this->soaCategoryTable->findByParams($params) as $soaCategory) {
            $result[] = $this->prepareSoaCategoryDataResult($soaCategory, true);
        }

        return $result;
    }

    public function getSoaCategoryData(Entity\Anr $anr, int $id): array
    {
        /** @var Entity\SoaCategory $soaCategory */
        $soaCategory = $this->soaCategoryTable->findByIdAndAnr($id, $anr);

        return $this->prepareSoaCategoryDataResult($soaCategory);
    }

    public function create(Entity\Anr $anr, array $data, bool $saveInDb = true): Entity\SoaCategory
    {
        /** @var Entity\Referential $referential */
        $referential = $data['referential'] instanceof Entity\Referential
            ? $data['referential']
            : $this->referentialTable->findByUuidAndAnr($data['referential'], $anr);

        /** @var Entity\SoaCategory $soaCategory */
        $soaCategory = (new Entity\SoaCategory())->setAnr($anr)->setLabels($data)->setReferential($referential);

        $this->soaCategoryTable->save($soaCategory, $saveInDb);

        return $soaCategory;
    }

    public function createList(Entity\Anr $anr, array $data): array
    {
        $createdCategories = [];
        foreach ($data as $datum) {
            $createdCategories[] = $this->create($anr, $datum, false);
        }
        $this->soaCategoryTable->flush();

        $createdIds = [];
        foreach ($createdCategories as $category) {
            $createdIds[] = $category->getId();
        }

        return $createdIds;
    }

    public function update(Entity\Anr $anr, int $id, array $data): Entity\SoaCategory
    {
        /** @var Entity\SoaCategory $soaCategory */
        $soaCategory = $this->soaCategoryTable->findByIdAndAnr($id, $anr);

        $this->soaCategoryTable->save($soaCategory->setLabels($data));

        return $soaCategory;
    }

    public function delete(Entity\Anr $anr, int $id): void
    {
        /** @var Entity\SoaCategory $soaCategory */
        $soaCategory = $this->soaCategoryTable->findByIdAndAnr($id, $anr);

        $this->soaCategoryTable->remove($soaCategory);
    }

    private function prepareSoaCategoryDataResult(
        Entity\SoaCategory $soaCategory,
        bool $includeReferential = false
    ): array {
        $result = array_merge(['id' => $soaCategory->getId()], $soaCategory->getLabels());
        if ($includeReferential) {
            $result['referential'] = array_merge(
                ['uuid' => $soaCategory->getReferential()->getUuid()],
                $soaCategory->getReferential()->getLabels()
            );
        }

        return $result;
    }
}
