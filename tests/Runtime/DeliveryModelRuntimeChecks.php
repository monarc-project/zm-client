<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFrontOfficeTests\Runtime;

use Doctrine\ORM\Tools\SchemaValidator;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use Laminas\Router\RouteMatch;
use Monarc\Core\Entity\DeliveriesModels;
use Monarc\Core\Service\DeliveriesModelsService;
use Monarc\Core\Table\DeliveriesModelsTable;
use Monarc\FrontOffice\Controller\ApiDeliveriesModelsController;
use PhpOffice\PhpWord\TemplateProcessor;
use Psr\Container\ContainerInterface;
use RuntimeException;

/** Local session-only fixtures; installed rows and files are fingerprinted before/after. */
class DeliveryModelRuntimeChecks
{
    public static function run(ContainerInterface $container, string &$phase): array
    {
        $phase = 'delivery model metadata/schema/DI';
        $manager = $container->get('doctrine.entitymanager.orm_cli');
        $connection = $manager->getConnection();
        $metadata = $manager->getClassMetadata(DeliveriesModels::class);
        self::check((new SchemaValidator($manager))->validateClass($metadata) === [], 'template mapping');
        self::check(!$metadata->hasAssociation('anr'), 'templates are global');
        $columns = $connection->createSchemaManager()->listTableColumns('deliveries_models');
        self::check(!isset($columns['anr_id']), 'existing migration removed template anr_id');
        $table = $container->get(DeliveriesModelsTable::class);
        $service = $container->get(DeliveriesModelsService::class);
        $controller = $container->get('ControllerManager')->get(ApiDeliveriesModelsController::class);
        $rowsBefore = $connection->fetchAllAssociative('SELECT * FROM deliveries_models ORDER BY id');
        $rowHash = hash('sha256', json_encode($rowsBefore, JSON_THROW_ON_ERROR));
        $fileHashes = [];
        $documents = 0;
        $unavailableFiles = [];
        $phase = 'installed template downloads and DOCX generation';
        foreach ($table->findAll() as $deliveryModel) {
            foreach (range(1, 4) as $language) {
                $relativePath = $deliveryModel->getPath($language);
                if ($relativePath === null || $relativePath === '') {
                    continue;
                }
                try {
                    $download = $service->getDownload($deliveryModel->getId(), $language);
                } catch (\Monarc\Core\Exception\Exception $error) {
                    self::check($error->getCode() === 404, 'unavailable installed file returns 404');
                    $unavailableFiles[] = ['id' => $deliveryModel->getId(), 'language' => $language];
                    continue;
                }
                $path = $service->getGenerationPath($deliveryModel->getId(), $language, $deliveryModel->getCategory());
                $fileHashes[$path] = hash_file('sha256', $path);
                self::check(hash('sha256', $download['contents']) === $fileHashes[$path], 'download bytes');
                $output = tempnam(sys_get_temp_dir(), 'php84-document-');
                try {
                    (new TemplateProcessor($path))->saveAs($output);
                    self::check(filesize($output) > 0, 'DOCX generated from installed template');
                    ++$documents;
                } finally {
                    unlink($output);
                }
            }
        }
        $manager->clear();
        $temporaryTable = false;
        try {
            $phase = 'isolated delivery model CRUD';
            $definition = $connection->createSchemaManager()->introspectTable('deliveries_models');
            foreach ($connection->getDatabasePlatform()->getCreateTableSQL($definition) as $sql) {
                $connection->executeStatement(str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $sql));
            }
            $temporaryTable = true;
            $id = self::request($controller, 'POST', data: [
                'category' => 1,
                'editable' => 0,
                'description1' => 'Runtime template',
                'path1' => '/ignored-input.docx',
            ])['id'];
            self::request($controller, 'PUT', $id, ['description2' => 'English']);
            self::request($controller, 'PATCH', $id, ['description1' => null]);
            $data = $service->getDeliveryModelData($id);
            self::check($data['description1'] === null && $data['description2'] === 'English', 'partial/null fields');
            self::check($data['editable'] === false && $data['path1'] === '', 'omitted fields and ignored paths');
            for ($index = 1; $index < 5; ++$index) {
                self::request($controller, 'POST', data: ['category' => 1]);
            }
            self::request($controller, 'PATCH', $id, ['category' => 1]);
            try {
                self::request($controller, 'POST', data: ['category' => 1]);
                throw new RuntimeException('Category capacity was not enforced');
            } catch (\Monarc\Core\Exception\Exception $error) {
                self::check($error->getCode() === 412, 'full category is rejected');
            }
            $list = self::request($controller, 'GET', query: ['category' => 1, 'limit' => 1]);
            self::check($list['count'] === 5 && count($list['deliveriesmodels']) === 1, 'list/count pagination');
            self::request($controller, 'DELETE', $id);
            self::check(self::request($controller, 'GET')['count'] === 4, 'delete one record');
        } finally {
            $manager->clear();
            if ($temporaryTable) {
                $connection->executeStatement('DROP TEMPORARY TABLE deliveries_models');
            }
        }
        $phase = 'installed templates unchanged';
        self::check($rowHash === hash('sha256', json_encode(
            $connection->fetchAllAssociative('SELECT * FROM deliveries_models ORDER BY id'),
            JSON_THROW_ON_ERROR
        )), 'installed rows unchanged');
        foreach ($fileHashes as $path => $hash) {
            self::check(hash_file('sha256', $path) === $hash, 'installed files unchanged');
        }

        return [
            'metadataSchemaDi' => 'passed',
            'isolatedCrudCapacityListCount' => 'passed',
            'installedRows' => count($rowsBefore),
            'installedFiles' => count($fileHashes),
            'unavailableInstalledFiles' => $unavailableFiles,
            'generatedDocuments' => $documents,
            'installedRowsAndFilesUnchanged' => true,
            'multipartReplacement' => 'requires separate real HTTP upload verification',
        ];
    }

    private static function request(
        ApiDeliveriesModelsController $controller,
        string $method,
        ?int $id = null,
        array $data = [],
        array $query = []
    ): array {
        $request = (new ServerRequest([], [], '/api/deliveriesmodels', $method))
            ->withAttribute(RouteMatch::class, new RouteMatch($id === null ? [] : ['id' => $id]))
            ->withParsedBody($data)->withQueryParams($query)->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode($data, JSON_THROW_ON_ERROR)));
        $response = $controller->handle($request);
        self::check($response->getStatusCode() === 200, 'controller status');

        return json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    private static function check(bool $success, string $message): void
    {
        if (!$success) {
            throw new RuntimeException('Delivery model runtime check failed: ' . $message);
        }
    }
}
