<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractService;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Referential;
use Monarc\FrontOffice\Model\Entity\SoaCategory;
use Monarc\FrontOffice\Model\Table\SoaCategoryTable;
use Monarc\FrontOffice\Service\Helper\ImportCacheHelper;

/**
 * @package Monarc\FrontOffice\Service
 */
class SoaCategoryService extends AbstractService
{
    protected $table;
    protected $entity;
    protected $anrTable;
    protected $userAnrTable;
    protected $filterColumns = ['label1', 'label2', 'label3', 'label4', 'status'];

    protected $dependencies = ['anr', 'referential'];
    protected $forbiddenFields = ['anr'];

    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        // Filter unwanted fields
        $this->filterPatchFields($data);
        parent::patch($id, $data);
    }

    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null)
    {
        [$filterJoin, $filterLeft, $filtersCol] = $this->get('entity')->getFiltersForService();

        return $this->get('table')->fetchAllFiltered(
            array_keys($this->get('entity')->getJsonArray()),
            $page,
            $limit,
            $this->parseFrontendOrder($order),
            $this->parseFrontendFilter($filter, $this->filterColumns),
            $filterAnd,
            $filterJoin,
            $filterLeft
        );
    }

    public function delete($id)
    {
        $table = $this->get('table');
        $categ = $table->getEntity($id);
        foreach ($categ->measures as $measure) {
            $measure->setCategory(null);
        }

        return parent::delete($id);
    }

    public function getOrCreateSoaCategory(
        ImportCacheHelper $importCacheHelper,
        Anr $anr,
        Referential $referential,
        string $labelValue
    ): SoaCategory {
        $languageIndex = $anr->getLanguage();
        $labelKey = 'label' . $languageIndex;

        $importCacheHelper->prepareSoaCategoriesCacheData($anr);

        $cacheKey = $referential->getUuid() . '_' . $labelValue;
        $soaCategory = $importCacheHelper->getItemFromArrayCache('soa_categories_by_ref_and_label', $cacheKey);
        if ($soaCategory !== null) {
            return $soaCategory;
        }

        $soaCategory = (new SoaCategory())
            ->setAnr($anr)
            ->setReferential($referential)
            ->setLabels([$labelKey => $labelValue]);

        /** @var SoaCategoryTable $soaCategoryTable */
        $soaCategoryTable = $this->get('table');
        $soaCategoryTable->saveEntity($soaCategory, false);

        $importCacheHelper->addItemToArrayCache('soa_categories_by_ref_and_label', $soaCategory, $cacheKey);

        return $soaCategory;
    }
}
