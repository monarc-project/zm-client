<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFrontOfficeTests\Unit;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Monarc\Core\Entity\DeliveriesModels;
use Monarc\Core\Table\DeliveriesModelsTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\DeliveriesModelsService;
use Monarc\FrontOffice\Entity\Delivery;
use PHPUnit\Framework\TestCase;

/** @coversNothing */
class DeliveryModelMappingTest extends TestCase
{
    public function testSharedTemplatesPersistAndReloadWithoutAnAnalysisColumn(): void
    {
        $manager = $this->manager();
        try {
            $metadata = $manager->getClassMetadata(DeliveriesModels::class);
            self::assertFalse($metadata->hasAssociation('anr'));
            self::assertFalse($metadata->hasField('anr'));
            (new SchemaTool($manager))->createSchema([$metadata]);
            $template = new DeliveriesModels();
            $template->setCategory(1)->setEditable(true)
                ->setDescriptions(['description1' => 'Global template']);
            $manager->persist($template);
            $manager->flush();
            $id = $template->getId();
            $manager->clear();
            $table = new DeliveriesModelsTable($manager);
            self::assertSame('Global template', $table->findById($id)->getDescription(1));
            self::assertArrayNotHasKey('anr_id', $manager->getConnection()->createSchemaManager()
                ->listTableColumns('deliveries_models'));
        } finally {
            $manager->close();
        }
    }

    public function testGeneratedDeliveriesRetainTheirSeparateAnalysisAssociation(): void
    {
        $manager = $this->manager();
        try {
            $template = $manager->getClassMetadata(DeliveriesModels::class);
            $delivery = $manager->getClassMetadata(Delivery::class);
            self::assertSame('deliveries_models', $template->getTableName());
            self::assertSame('deliveries', $delivery->getTableName());
            self::assertFalse($template->hasAssociation('anr'));
            self::assertTrue($delivery->hasAssociation('anr'));
            self::assertSame('anr_id', $delivery->associationMappings['anr']['joinColumns'][0]['name']);
        } finally {
            $manager->close();
        }
    }

    public function testSharedEntityAndTableRemainDeclaredByTheCoreModule(): void
    {
        $config = require dirname(__DIR__, 3) . '/zm-core/config/module.config.php';
        $factories = $config['service_manager']['factories'];
        self::assertArrayNotHasKey(DeliveriesModels::class, $factories);
        self::assertSame(\Laminas\Di\Container\AutowireFactory::class, $factories[DeliveriesModelsService::class]);
        self::assertArrayHasKey(DeliveriesModelsTable::class, $factories);
    }

    private function manager(): EntityManager
    {
        $config = new Configuration();
        $config->setMetadataDriverImpl(new AnnotationDriver(new AnnotationReader()));
        $config->setProxyDir(sys_get_temp_dir());
        $config->setProxyNamespace('FrontOfficeDeliveryModelProxies');
        $config->setAutoGenerateProxyClasses(true);

        return EntityManager::create(['driver' => 'pdo_sqlite', 'memory' => true], $config);
    }
}
