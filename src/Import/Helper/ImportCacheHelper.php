<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Helper;

class ImportCacheHelper
{
    private array $arrayCache = [];

    public function isCacheKeySet(string $cacheKey): bool
    {
        return isset($this->arrayCache[$cacheKey]);
    }

    public function isItemInArrayCache(string $cacheKey, $itemKey): bool
    {
        return isset($this->arrayCache[$cacheKey][$itemKey]);
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
     * @return array|null|mixed
     */
    public function getItemFromArrayCache(string $cacheKey, $itemKey = null)
    {
        if ($itemKey === null) {
            return $this->arrayCache[$cacheKey] ?? null;
        }

        return $this->arrayCache[$cacheKey][$itemKey] ?? null;
    }

    /** Sets a single array cache value. */
    public function setArrayCacheValue(string $cacheKey, $value): void
    {
        $this->arrayCache[$cacheKey] = $value;
    }

    /** Returns the array cache value or null if not set. */
    public function getValueFromArrayCache(string $cacheKey)
    {
        return $this->arrayCache[$cacheKey] ?? null;
    }
}
