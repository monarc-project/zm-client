<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFrontOfficeTests\Unit\Import;

use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Monarc\FrontOffice\Import\Helper\ImportCacheHelper
 */
class ImportCacheHelperTest extends TestCase
{
    public function testKeyedAndAppendedItemsRemainIsolatedByCacheName(): void
    {
        $cache = new ImportCacheHelper();
        self::assertFalse($cache->isCacheKeySet('objects'));
        self::assertNull($cache->getItemFromArrayCache('objects', 'uuid'));
        $object = new \stdClass();
        $cache->addItemToArrayCache('objects', $object, 'uuid');
        $cache->addItemToArrayCache('names', 'first');
        $cache->addItemToArrayCache('names', 'second');

        self::assertTrue($cache->isItemInArrayCache('objects', 'uuid'));
        self::assertSame($object, $cache->getItemFromArrayCache('objects', 'uuid'));
        self::assertSame(['first', 'second'], $cache->getItemFromArrayCache('names'));
    }

    public function testSingleValuesPreserveFalseZeroAndEmptyValues(): void
    {
        $cache = new ImportCacheHelper();
        foreach ([false, 0, '', []] as $value) {
            $cache->setArrayCacheValue('mode', $value);
            self::assertTrue($cache->isCacheKeySet('mode'));
            self::assertSame($value, $cache->getValueFromArrayCache('mode'));
        }
        self::assertNull($cache->getValueFromArrayCache('missing'));
    }
}
