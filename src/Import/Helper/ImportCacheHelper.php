<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Helper;

use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Table;

class ImportCacheHelper
{
    private array $arrayCache = [];

    public function __construct(private Table\ThemeTable $themeTable, private Table\SoaCategoryTable $soaCategoryTable)
    {
    }

    public function prepareThemesCacheData(Entity\Anr $anr): void
    {
        if (!isset($this->arrayCache['themes_by_labels'])) {
            /** @var Entity\Theme $theme */
            foreach ($this->themeTable->findByAnr($anr) as $theme) {
                $this->addItemToArrayCache('themes_by_labels', $theme, $theme->getLabel($anr->getLanguage()));
            }
        }
    }

    public function prepareSoaCategoriesCacheData(Entity\Anr $anr): void
    {
        if (!isset($this->arrayCache['soa_categories_by_ref_and_label'])) {
            /** @var Entity\SoaCategory $soaCategory */
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
