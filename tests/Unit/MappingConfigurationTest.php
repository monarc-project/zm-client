<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFrontOfficeTests\Unit;

use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use DoctrineModule\Service\DriverFactory;
use Laminas\ServiceManager\ServiceManager;
use Doctrine\ORM\Tools\SchemaValidator;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Laminas\Stdlib\ArrayUtils;
use Monarc\Core\Tests\Fixture\MappingTestEnvironment;
use Monarc\Core\Tests\Fixture\MappingSnapshot;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class MappingConfigurationTest extends TestCase
{
    public function testNativeDriversPreserveEveryClassAndPersistedMapping(): void
    {
        require_once dirname(__DIR__, 3) . '/zm-core/tests/Fixture/MappingTestEnvironment.php';
        require_once dirname(__DIR__, 3) . '/zm-core/tests/Fixture/MappingSnapshot.php';
        $core = require dirname(__DIR__, 3) . '/zm-core/config/module.config.php';
        $office = require dirname(__DIR__, 2) . '/config/module.config.php';
        $drivers = ArrayUtils::merge($core['doctrine']['driver'], $office['doctrine']['driver']);
        $applicationDir = getenv('MONARC_TEST_APP_DIR') ?: dirname(__DIR__, 3) . '/MonarcAppFO';
        $baseline = json_decode(
            file_get_contents($applicationDir . '/tests/Fixture/attribute-mapping-fingerprints.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $defaults = require $applicationDir . '/vendor/doctrine/doctrine-orm-module/config/module.config.php';
        $drivers = ArrayUtils::merge($defaults['doctrine']['driver'], $drivers);
        $container = new ServiceManager(['services' => ['config' => ['doctrine' => ['driver' => $drivers]]]]);
        foreach (['orm_default', 'orm_cli', 'orm_cli_fo'] as $name) {
            self::assertSame(MappingDriverChain::class, $drivers[$name]['class']);
            $driver = (new DriverFactory($name))($container, 'doctrine.driver.' . $name);
            self::assertInstanceOf(MappingDriverChain::class, $driver);
            foreach ($driver->getDrivers() as $leaf) {
                self::assertInstanceOf(AttributeDriver::class, $leaf);
            }
            foreach ($drivers[$name]['drivers'] as $leaf) {
                self::assertSame(AttributeDriver::class, $drivers[$leaf]['class']);
            }
            $manager = MappingTestEnvironment::create($drivers, $name);
            try {
                self::assertSame(
                    $baseline[$name],
                    MappingSnapshot::fingerprints(MappingSnapshot::capture($manager)),
                    $name . ': full class discovery, declarations, lifecycle callbacks, indexes and SQL'
                );
            } finally {
                $manager->close();
            }
        }
    }

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
    public function testEveryDeclaredChainHasStrictMappingsAndPreservesItsPhysicalSchema(): void
    {
        require_once dirname(__DIR__, 3) . '/zm-core/tests/Fixture/MappingTestEnvironment.php';
        $core = require dirname(__DIR__, 3) . '/zm-core/config/module.config.php';
        $office = require dirname(__DIR__, 2) . '/config/module.config.php';
        $drivers = ArrayUtils::merge($core['doctrine']['driver'], $office['doctrine']['driver']);
        $applicationDir = getenv('MONARC_TEST_APP_DIR') ?: dirname(__DIR__, 3) . '/MonarcAppFO';
        $baseline = json_decode(
            file_get_contents($applicationDir . '/tests/Fixture/schema-mapping-fingerprints.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        // orm_cli_fo is a reusable driver chain, not an independently configured entity manager.
        foreach (['orm_default', 'orm_cli', 'orm_cli_fo'] as $name) {
            $manager = MappingTestEnvironment::create($drivers, $name);
            try {
                $metadata = $manager->getMetadataFactory()->getAllMetadata();
                self::assertCount($baseline[$name]['metadataCount'], $metadata);
                self::assertSame([], (new SchemaValidator($manager))->validateMapping(), $name);
                self::assertSame(
                    $baseline[$name]['physicalMappingSha256'],
                    MappingTestEnvironment::physicalMappingFingerprint($metadata),
                    $name . ' physical schema, ownership and persistence options'
                );
                foreach ($metadata as $mapping) {
                    self::assertFalse(method_exists($mapping->name, 'getDbAdapter'));
                    self::assertFalse(method_exists($mapping->name, '__get'));
                    self::assertFalse(method_exists($mapping->name, '__set'));
                }
                $manager->getProxyFactory()->generateProxyClasses($metadata);
            } finally {
                $manager->close();
            }
        }
    }
    public function testMeasureSelfLinksPreserveSymmetricCollectionBehavior(): void
    {
        require_once dirname(__DIR__, 3) . '/zm-core/tests/Fixture/MappingTestEnvironment.php';
        $core = require dirname(__DIR__, 3) . '/zm-core/config/module.config.php';
        $office = require dirname(__DIR__, 2) . '/config/module.config.php';
        $drivers = ArrayUtils::merge($core['doctrine']['driver'], $office['doctrine']['driver']);
        $manager = MappingTestEnvironment::create($drivers, 'orm_cli');
        try {
            $metadata = array_map($manager->getClassMetadata(...), [
                \Monarc\FrontOffice\Entity\Measure::class,
                \Monarc\FrontOffice\Entity\Amv::class,
                \Monarc\FrontOffice\Entity\Soa::class,
                \Monarc\FrontOffice\Entity\Anr::class,
                \Monarc\FrontOffice\Entity\Asset::class,
                \Monarc\FrontOffice\Entity\Threat::class,
                \Monarc\FrontOffice\Entity\Vulnerability::class,
            ]);
            foreach ((new \Doctrine\ORM\Tools\SchemaTool($manager))->getCreateSchemaSql($metadata) as $sql) {
                $sql = preg_replace('/^(CREATE INDEX) (\w+) ON (\w+)/', '$1 $3_$2 ON $3', $sql);
                $manager->getConnection()->executeStatement($sql);
            }
            $anr = new \Monarc\FrontOffice\Entity\Anr();
            (new \ReflectionProperty($anr, 'id'))->setValue($anr, 1);
            $manager->getUnitOfWork()->registerManaged($anr, ['id' => 1], []);
            $master = (new \Monarc\FrontOffice\Entity\Measure())->setAnr($anr)->setLabels([]);
            $linked = (new \Monarc\FrontOffice\Entity\Measure())->setAnr($anr)->setLabels([]);
            $master->addLinkedMeasure($linked);
            $manager->persist($master);
            $manager->flush();
            $masterId = $master->getId();
            $linkedId = $linked->getId();
            $manager->clear();
            $manager->getUnitOfWork()->registerManaged($anr, ['id' => 1], []);
            $reloaded = $manager->find(\Monarc\FrontOffice\Entity\Measure::class, $masterId);
            self::assertSame($linkedId, $reloaded->getLinkedMeasures()->first()->getId());
            self::assertSame($reloaded, $reloaded->getLinkedMeasures()->first()->getLinkedMeasures()->first());
            self::assertSame(2, (int)$manager->getConnection()->fetchOne('SELECT COUNT(*) FROM measures_measures'));
            $reloaded->removeLinkedMeasure($reloaded->getLinkedMeasures()->first());
            $manager->flush();
            self::assertSame(0, (int)$manager->getConnection()->fetchOne('SELECT COUNT(*) FROM measures_measures'));
            self::assertSame(2, (int)$manager->getConnection()->fetchOne('SELECT COUNT(*) FROM measures'));
        } finally {
            $manager->close();
        }
    }
}
