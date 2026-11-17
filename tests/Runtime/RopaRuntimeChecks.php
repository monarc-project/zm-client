<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFrontOfficeTests\Runtime;

use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\Tools\SchemaValidator;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use Laminas\Router\RouteMatch;
use Monarc\FrontOffice\Controller;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Service;
use Monarc\FrontOffice\Table;
use Psr\Container\ContainerInterface;

/** All writes use connection-local temporary ROPA tables; installed rows are fingerprinted before and after. */
class RopaRuntimeChecks
{
    private const TABLES = ['records', 'record_actors', 'record_data_categories', 'record_international_transfers',
        'record_personal_data', 'record_processors', 'record_recipients', 'records_record_joint_controllers',
        'records_record_processors', 'records_record_recipients', 'record_personal_data_record_data_categories'];

    public static function run(ContainerInterface $container, string &$phase): array
    {
        $phase = 'ROPA metadata and dependency resolution';
        $manager = $container->get('doctrine.entitymanager.orm_cli');
        $connection = $manager->getConnection();
        foreach (['Record', 'RecordActor', 'RecordDataCategory', 'RecordPersonalData', 'RecordProcessor',
            'RecordRecipient', 'RecordInternationalTransfer'] as $name) {
            $metadata = $manager->getClassMetadata('Monarc\\FrontOffice\\Entity\\' . $name);
            self::check((new SchemaValidator($manager))->validateClass($metadata) === [], 'ROPA association mapping');
            self::check(!$metadata->associationMappings['anr']['joinColumns'][0]['nullable'], 'required ANR mapping');
            $container->get('Monarc\\FrontOffice\\Table\\' . $name . 'Table');
            $container->get('Monarc\\FrontOffice\\Service\\Anr' . $name . 'Service');
        }
        $controllers = $container->get('ControllerManager');
        foreach (['ApiAnrRecordsController', 'ApiAnrRecordActorsController', 'ApiAnrRecordDataCategoriesController',
            'ApiAnrRecordPersonalDataController', 'ApiAnrRecordProcessorsController',
            'ApiAnrRecordRecipientsController',
            'ApiAnrRecordInternationalTransfersController', 'ApiAnrRecordsImportController',
            'ApiAnrRecordsExportController', 'ApiAnrRecordDuplicateController'] as $controller) {
            $controllers->get('Monarc\\FrontOffice\\Controller\\' . $controller);
        }
        $container->get(Service\AnrService::class);
        $container->get(\Monarc\FrontOffice\Import\Service\InstanceImportService::class);
        $exporter = $container->get(\Monarc\FrontOffice\Export\Service\AnrExportService::class);
        $document = $container->get(Service\DeliverableGenerationService::class);
        $recordImportProcessor = $container->get(\Monarc\FrontOffice\Import\Processor\RecordImportProcessor::class);
        $records = $container->get(Table\RecordTable::class);
        $importRecord = static function (Entity\Anr $anr, array $data) use ($recordImportProcessor, $records): ?int {
            $importedRecords = $recordImportProcessor->processRecordsData($anr, [$data]);
            $records->flush();

            return isset($importedRecords[0]) ? $importedRecords[0]->getId() : null;
        };
        $service = $container->get(Service\AnrRecordService::class);
        $recordExportService = $container->get(\Monarc\FrontOffice\Export\Service\RecordExportService::class);
        $exportRecord = static function (Entity\Record $record) use ($recordExportService): array {
            [, $content] = $recordExportService->export($record->getAnr(), $record->getId());

            return json_decode($content, true, 512, JSON_THROW_ON_ERROR)[0];
        };
        $anrs = $container->get(Table\AnrTable::class);
        $anrIds = $connection->fetchFirstColumn('SELECT id FROM anrs ORDER BY id LIMIT 2');
        self::check(count($anrIds) === 2, 'two local analyses');
        $before = [];
        $definitions = [];
        foreach (self::TABLES as $table) {
            $before[$table] = self::fingerprint($connection, $table);
            $definition = $connection->createSchemaManager()->introspectTable($table);
            foreach ($definition->getForeignKeys() as $key) {
                $definition->removeForeignKey($key->getName());
            }
            $definitions[$table] = $definition;
        }
        $readable = 0;
        foreach ($manager->getRepository(Entity\Record::class)->findAll() as $record) {
            $service->serialize($record);
            ++$readable;
        }
        $manager->clear();
        $created = [];
        try {
            foreach ($definitions as $table => $definition) {
                foreach ($connection->getDatabasePlatform()->getCreateTableSQL($definition) as $sql) {
                    $connection->executeStatement(str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $sql));
                    if (!in_array($table, $created, true)) {
                        $created[] = $table;
                    }
                }
            }
            $anr = $anrs->findById((int)$anrIds[0]);
            $other = $anrs->findById((int)$anrIds[1]);
            $phase = 'ROPA complete import and API reads';
            $data = [
                'name' => 'Runtime record',
                'purposes' => '0',
                'security_measures' => 'Encryption',
                'controller' => ['name' => 'Controller', 'contact' => 'controller@example.invalid'],
                'representative' => ['name' => 'Representative'],
                'data_protection_officer' => ['name' => 'DPO'],
                'joint_controllers' => [['name' => 'Joint controller']],
                'processors' => [[
                    'name' => 'Processor',
                    'activities' => 'Hosting',
                    'security_measures' => 'Backups',
                    'representative' => ['name' => 'Processor representative'],
                    'data_protection_officer' => ['name' => 'Processor DPO']
                ]],
                'recipients' => [['name' => 'Recipient', 'type' => 'internal', 'description' => 'Employees']],
                'personal_data' => [[
                    'data_subject' => 'People',
                    'data_categories' => [['name' => 'Contact details']],
                    'retention_period' => 0,
                    'retention_period_mode' => 'day(s)'
                ]],
                'international_transfers' => [['organisation' => 'Provider', 'country' => 'Luxembourg']]
            ];
            $id = $importRecord($anr, $data);
            $controller = $controllers->get(Controller\ApiAnrRecordsController::class);
            $row = self::request($controller, $anr, 'GET', $id);
            self::check(
                $row['controller']['label'] === 'Controller' && count($row['personalData']) === 1,
                'nested API representation'
            );
            self::request($controller, $anr, 'PATCH', $id, ['label' => 'Runtime edited', 'anr' => $other->getId()]);
            self::check($records->findById($id)->getAnr()->getId() === $anr->getId(), 'body ANR ignored');
            $phase = 'ROPA denied foreign analysis access';
            try {
                self::request($controller, $other, 'GET', $id);
                throw new \RuntimeException('Foreign ROPA record accepted');
            } catch (EntityNotFoundException $error) {
                // The normal id-plus-ANR lookup denies foreign records.
            }
            $phase = 'ROPA duplicate with linked personal data and transfers';
            $copyId = $service->duplicate($anr, $id, 'Runtime duplicate');
            self::check(
                $copyId !== $id && $records->findById($copyId)->getPersonalData()->count() === 1,
                'fresh record and child identities'
            );
            $phase = 'ROPA analysis cloning';
            $service->copy($anr, $other);
            self::check(count($records->findByAnr($other)) === 0, 'analysis copy defers flush');
            $records->flush();
            self::check(count($records->findByAnr($other)) === 2, 'both linked records copied');
            $copy = current(array_filter(
                $records->findByAnr($other),
                fn (Entity\Record $record): bool => $record->getLabel() === 'Runtime edited'
            ));
            self::check($copy->getController()->getAnr()->getId() === $other->getId(), 'actors remapped to target ANR');
            self::check($copy->getPersonalData()->first()->getRecord() === $copy, 'personal data parent remapped');
            $phase = 'ROPA analysis export and reimport';
            $export = $exportRecord($records->findById($id));
            $fromAnalysis = (new \ReflectionMethod($exporter, 'prepareGdprRecordsData'))->invoke($exporter, $anr);
            self::check(count($fromAnalysis) === 2, 'analysis export includes records');
            $importId = $importRecord($other, $export);
            self::check($export === $exportRecord($records->findById($importId)), 'export roundtrip');
            self::check($importRecord($other, []) === null, 'missing main record skipped');
            $phase = 'ROPA document sections';
            foreach (['anr' => $anr, 'currentLangAnrIndex' => $anr->getLanguage()] as $field => $value) {
                (new \ReflectionProperty($document, $field))->setValue($document, $value);
            }
            foreach (['generateTableRecordGDPR', 'generateTableRecordActors', 'generateTableRecordPersonalData',
                'generateTableRecordRecipients', 'generateTableRecordInternationalTransfers',
                'generateTableRecordProcessors', 'generateTableAllRecordsGDPR'] as $method) {
                $xml = (new \ReflectionMethod($document, $method))->invoke($document, $id);
                self::check(is_string($xml) && str_contains($xml, '<w:'), 'ROPA report output');
            }
            $phase = 'ROPA delete with Doctrine-owned children';
            self::request($controller, $anr, 'DELETE', $id);
            self::check(count($records->findByAnr($anr)) === 1, 'other source record retained');
            self::request($controller, $anr, 'DELETE', $copyId);
            self::check(count($records->findByAnr($anr)) === 0, 'source records removed');
            self::check(count($records->findByAnr($other)) === 3, 'target analysis data retained');
            foreach ([Entity\RecordPersonalData::class, Entity\RecordInternationalTransfer::class] as $childClass) {
                self::check(
                    $manager->getRepository($childClass)->count(['anr' => $anr]) === 0,
                    'Doctrine removes owned children with the source records'
                );
            }
            foreach ([Entity\RecordActor::class, Entity\RecordProcessor::class, Entity\RecordRecipient::class,
                Entity\RecordDataCategory::class] as $referenceClass) {
                self::check(
                    $manager->getRepository($referenceClass)->count(['anr' => $anr]) > 0,
                    'shared reference records are retained'
                );
            }
        } finally {
            $manager->clear();
            foreach (array_reverse($created) as $table) {
                $connection->executeStatement('DROP TEMPORARY TABLE ' . $table);
            }
        }
        $phase = 'ROPA installed data preservation';
        foreach (self::TABLES as $table) {
            self::check($before[$table] === self::fingerprint($connection, $table), 'installed ROPA rows unchanged');
        }

        return [
            'mappings' => 'clean',
            'controllersAndConsumers' => 'resolved',
            'crudScopeAndCleanup' => 'passed',
            'importExportRecordAndAnalysisCopies' => 'passed',
            'documentSections' => 'passed',
            'installedDataUnchanged' => true,
            'installedRecordsReadable' => $readable,
            'installedTables' => $before
        ];
    }

    private static function fingerprint($connection, string $table): array
    {
        $rows = $connection->fetchAllAssociative('SELECT * FROM ' . $table);
        usort($rows, static fn ($a, $b): int => strcmp(json_encode($a), json_encode($b)));

        return ['count' => count($rows), 'sha256' => hash('sha256', json_encode($rows))];
    }

    private static function request($controller, Entity\Anr $anr, string $method, ?int $id, ?array $data = null): array
    {
        $body = (new StreamFactory())->createStream(json_encode($data ?? [], JSON_THROW_ON_ERROR));
        $params = ['anrid' => $anr->getId()];
        if ($id !== null) {
            $params['id'] = $id;
        }
        $request = (new ServerRequest([], [], '/api/client-anr/' . $anr->getId() . '/records', $method))
            ->withParsedBody($data)->withHeader('Content-Type', 'application/json')->withBody($body)
            ->withAttribute(RouteMatch::class, new RouteMatch($params))->withAttribute('anr', $anr);
        $response = $controller->handle($request);
        self::check($response->getStatusCode() === 200, 'ROPA controller status');

        return json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    private static function check(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException('ROPA runtime check failed: ' . $message);
        }
    }
}
