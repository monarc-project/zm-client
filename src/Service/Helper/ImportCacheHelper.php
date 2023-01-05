<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service\Helper;

use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Table\SoaCategoryTable;
use Monarc\FrontOffice\Model\Table\ThemeTable;

class ImportCacheHelper
{
    private ThemeTable $themeTable;

    private SoaCategoryTable $soaCategoryTable;

    private array $arrayCache = [];

    public function __construct(
        ThemeTable $themeTable,
        SoaCategoryTable $soaCategoryTable
    ) {
        $this->themeTable = $themeTable;
        $this->soaCategoryTable = $soaCategoryTable;
    }

    public function prepareThemesCacheData(Anr $anr): void
    {
        if (!isset($this->arrayCache['themes_by_labels'])) {
            foreach ($this->themeTable->findByAnr($anr) as $theme) {
                $this->addItemToArrayCache('themes_by_labels', $theme, $theme->getLabel($anr->getLanguage()));
            }
        }
    }

    public function prepareSoaCategoriesCacheData(Anr $anr): void
    {
        if (!isset($this->arrayCache['soa_categories_by_ref_and_label'])) {
            foreach ($this->soaCategoryTable->findByAnr($anr) as $soaCategory) {
                $this->addItemToArrayCache(
                    'soa_categories_by_ref_and_label',
                    $soaCategory,
                    $soaCategory->getReferential()->getUuid() . '_' . $soaCategory->getLabel($anr->getLanguage())
                );
            }
        }
    }

    public function addItemToArrayCache(string $cacheKey, $value, $itemKey = null): void
    {
        if ($itemKey === null) {
            $this->arrayCache[$cacheKey][] = $value;
        } else {
            $this->arrayCache[$cacheKey][$itemKey] = $value;
        }
    }

    /**
     * @return mixed
     */
    public function getItemFromArrayCache(string $cacheKey, $itemKey = null)
    {
        if ($itemKey === null) {
            return $this->arrayCache[$cacheKey] ?? null;
        }

        return $this->arrayCache[$cacheKey][$itemKey] ?? null;
    }
}
