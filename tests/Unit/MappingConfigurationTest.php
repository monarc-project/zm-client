<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFrontOfficeTests\Unit;

use Laminas\Stdlib\ArrayUtils;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class MappingConfigurationTest extends TestCase
{
    public function testOfficeAndCoreDriversKeepSeparateExistingSourceDirectories(): void
    {
        $core = require dirname(__DIR__, 3) . '/zm-core/config/module.config.php';
        $office = require dirname(__DIR__, 2) . '/config/module.config.php';
        $drivers = ArrayUtils::merge($core['doctrine']['driver'], $office['doctrine']['driver']);
        $chain = $drivers['orm_cli']['drivers'];
        self::assertNotSame($chain['Monarc\\Core\\Entity'], $chain['Monarc\\FrontOffice\\Entity']);
        foreach ($drivers as $driver) {
            foreach ($driver['paths'] ?? [] as $path) {
                self::assertDirectoryExists($path);
            }
        }
    }
}
