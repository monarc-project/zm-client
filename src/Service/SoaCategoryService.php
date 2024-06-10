<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\InputFormatter\FormattedInputParams;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
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
            $result[] = $this->prepareSoaCategoryDataResult($soaCategory);
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

    private function prepareSoaCategoryDataResult(Entity\SoaCategory $soaCategory): array
    {
        return array_merge(['id' => $soaCategory->getId()], $soaCategory->getLabels());
    }

    // TODO: use the ReferentialImportProcessor::processSoaCategoryData() instead.
    public function getOrCreateSoaCategory(
        ImportCacheHelper $importCacheHelper,
        Entity\Anr $anr,
        Entity\Referential $referential,
        string $labelValue
    ): Entity\SoaCategory {
        $languageIndex = $anr->getLanguage();
        $labelKey = 'label' . $languageIndex;

        $this->prepareSoaCategoriesCacheData($importCacheHelper, $anr);

        $cacheKey = $referential->getUuid() . '_' . $labelValue;
        $soaCategory = $importCacheHelper->getItemFromArrayCache('soa_categories_by_ref_and_label', $cacheKey);
        if ($soaCategory !== null) {
            return $soaCategory;
        }

        $soaCategory = (new Entity\SoaCategory())
            ->setAnr($anr)
            ->setReferential($referential)
            ->setLabels([$labelKey => $labelValue]);

        $this->soaCategoryTable->save($soaCategory, false);

        $importCacheHelper->addItemToArrayCache('soa_categories_by_ref_and_label', $soaCategory, $cacheKey);

        return $soaCategory;
    }

    public function prepareSoaCategoriesCacheData(ImportCacheHelper $importCacheHelper, Entity\Anr $anr): void
    {
        if (!isset($this->arrayCache['soa_categories_by_ref_and_label'])) {
            /** @var Entity\SoaCategory $soaCategory */
            foreach ($this->soaCategoryTable->findByAnr($anr) as $soaCategory) {
                $importCacheHelper->addItemToArrayCache(
                    'soa_categories_by_ref_and_label',
                    $soaCategory,
                    $soaCategory->getReferential()->getUuid() . '_' . $soaCategory->getLabel($anr->getLanguage())
                );
            }
        }
    }
}
