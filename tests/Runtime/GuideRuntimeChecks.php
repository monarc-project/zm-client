<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFrontOfficeTests\Runtime;

use Monarc\Core\Exception\Exception;
use Monarc\Core\InputFormatter\Guide\GetGuidesInputFormatter;
use Monarc\Core\InputFormatter\Guide\GetGuideItemsInputFormatter;
use Monarc\Core\Service\GuideService;
use Monarc\Core\Service\GuideItemService;
use Monarc\Core\Service\QuestionService;
use Psr\Container\ContainerInterface;

/** Scoped local checks: session-only temporary tables protect all installed guide data. */
class GuideRuntimeChecks
{
    public static function run(ContainerInterface $container, string &$phase): array
    {
        $manager = $container->get('doctrine.entitymanager.orm_default');
        $connection = $manager->getConnection();
        foreach (['guides', 'guides_items'] as $name) {
            $phase = 'migrated schema ' . $name;
            $columns = $connection->createSchemaManager()->listTableColumns($name);
            self::assert(!isset($columns['anr_id']), $name . ' still contains anr_id');
            if ($name === 'guides_items') {
                self::assert($columns['guide_id']->getNotnull(), 'installed guide_id is NOT NULL');
            }
        }
        $metadata = $manager->getClassMetadata(\Monarc\Core\Entity\GuideItem::class);
        self::assert(!$metadata->associationMappings['guide']['joinColumns'][0]['nullable'], 'required guide mapping');
        $phase = 'office delivery model mapping';
        $templates = $container->get('doctrine.entitymanager.orm_cli');
        $templateClass = \Monarc\Core\Entity\DeliveriesModels::class;
        $templateService = $container->get(\Monarc\Core\Service\DeliveriesModelsService::class);
        $templateTable = $container->get(\Monarc\Core\Table\DeliveriesModelsTable::class);
        self::assert($templateTable->getEntityName() === $templateClass, 'delivery table entity');
        $templateMetadata = $templates->getClassMetadata($templateClass);
        $templateColumns = $templates->getConnection()->createSchemaManager()->listTableColumns('deliveries_models');
        self::assert(!$templateMetadata->hasAssociation('anr'), 'delivery templates have no analysis association');
        self::assert(!isset($templateColumns['anr_id']), 'delivery templates have no analysis column');
        $deliveryMetadata = $templates->getClassMetadata(\Monarc\FrontOffice\Entity\Delivery::class);
        $deliveryColumns = $templates->getConnection()->createSchemaManager()->listTableColumns('deliveries');
        self::assert($deliveryMetadata->hasAssociation('anr'), 'generated delivery analysis mapping');
        self::assert(isset($deliveryColumns['anr_id']), 'generated deliveries retain their analysis column');
        $phase = 'modern delivery model read';
        $templateData = $templateService->getList(
            (new \Monarc\Core\InputFormatter\DeliveryModel\GetDeliveryModelsInputFormatter())->format(['limit' => 1])
        );
        $prefix = 'Monarc\\FrontOffice';
        $guides = $container->get(GuideService::class);
        $items = $container->get(GuideItemService::class);
        $guideController = $container->get('ControllerManager')->get($prefix . '\\Controller\\ApiGuidesController');
        $itemController = $container->get('ControllerManager')->get($prefix . '\\Controller\\ApiGuidesItemsController');
        // Check the next migrated domain alongside the modern delivery path.
        $phase = 'modern question read';
        $params = (new \Monarc\Core\InputFormatter\Question\GetQuestionsInputFormatter())->format(['limit' => 1]);
        $questionsRead = $container->get(QuestionService::class)->getList($params);
        $manager->clear();
        $temporaryTables = [];
        try {
            foreach (['guides', 'guides_items'] as $table) {
                $phase = 'temporary fixture ' . $table;
                $definition = $connection->createSchemaManager()->introspectTable($table);
                // Temporary InnoDB tables cannot carry foreign keys. Services still validate the references.
                foreach ($definition->getForeignKeys() as $foreignKey) {
                    $definition->removeForeignKey($foreignKey->getName());
                }
                $statements = $connection->getDatabasePlatform()->getCreateTableSQL($definition);
                foreach ($statements as $sql) {
                    $connection->executeStatement(str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $sql));
                }
                $temporaryTables[] = $table;
            }
            $phase = 'reject null guide';
            try {
                $connection->executeStatement("INSERT INTO guides_items
                    (guide_id, position, description1, description2, description3, description4)
                    VALUES (NULL, 1, '', '', '', '')");
                throw new \RuntimeException('A guide item without a guide was accepted.');
            } catch (\Doctrine\DBAL\Exception\NotNullConstraintViolationException $error) {
                // The schema requires a guide independently of service validation.
            }
            $phase = 'read-only FrontOffice guides';
            $guide = $guides->create(['type' => 1, 'isWithItems' => 1, 'description3' => 'Runtime guide'])->getId();
            $item = $items->create(['guide' => $guide, 'description3' => 'Runtime item'])->getId();
            self::assert(
                self::decode($guideController->get($guide))['description3'] === 'Runtime guide',
                'FrontOffice guide read'
            );
            self::assert(
                self::decode($itemController->get($item))['guide']['id'] === $guide,
                'FrontOffice item read'
            );
            foreach ([$guideController, $itemController] as $controller) {
                foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
                    $phase = 'FrontOffice guide ' . $method . ' returns 405';
                    $data = ['description3' => 'Attempted write'];
                    $body = (new \Laminas\Diactoros\StreamFactory())->createStream(json_encode($data));
                    $request = (new \Laminas\Diactoros\ServerRequest([], [], '/api/guides', $method))
                        ->withParsedBody($data)->withHeader('Content-Type', 'application/json')->withBody($body)
                        ->withAttribute(\Laminas\Router\RouteMatch::class, new \Laminas\Router\RouteMatch([]));
                    $response = $controller->handle($request);
                    $phase .= ' (received ' . $response->getStatusCode() . ')';
                    self::assert(
                        $response->getStatusCode() === 405,
                        'FrontOffice guide writes are unsupported'
                    );
                }
            }
            $phase = 'read-only FrontOffice guides unchanged';
            self::assert($guides->getGuideData($guide)['description3'] === 'Runtime guide', 'guide unchanged');
            self::assert($items->getGuideItemData($item)['description3'] === 'Runtime item', 'item unchanged');
        } finally {
            $manager->clear();
            foreach (array_reverse($temporaryTables) as $table) {
                $connection->executeStatement('DROP TEMPORARY TABLE ' . $table);
            }
        }

        return [
            'guideCrud' => 'read-only', 'guideAnalysisColumns' => 'absent',
            'requiredGuide' => 'metadata/schema checked; NULL inserts rejected',
            'deliveryModelAnalysisColumn' => 'absent',
            'deliveryModelEntity' => $templateClass,
            'generatedDeliveryAnalysisColumn' => 'retained',
            'deliveryModelReadCount' => count($templateData),
            'questionReadCount' => count($questionsRead),
        ];
    }

    private static function create(object $controller, array $data): array
    {
        $request = (new \Laminas\Diactoros\ServerRequest([], [], '/api/guides', 'POST'))
            ->withParsedBody($data)->withHeader('Content-Type', 'application/json')
            ->withBody((new \Laminas\Diactoros\StreamFactory())->createStream(json_encode($data)))
            ->withAttribute(\Laminas\Router\RouteMatch::class, new \Laminas\Router\RouteMatch([]));

        return self::decode($controller->handle($request));
    }

    private static function decode(object $response): array
    {
        return json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    private static function assert(bool $condition, string $check): void
    {
        if (!$condition) {
            throw new \RuntimeException('Guide runtime check failed: ' . $check);
        }
    }
}
