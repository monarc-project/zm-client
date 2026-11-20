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
use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Controller\ApiAnrInterviewsController;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Interview;
use Monarc\FrontOffice\Export\Service\AnrExportService;
use Monarc\FrontOffice\Import\Processor\InterviewImportProcessor;
use Monarc\FrontOffice\Service\AnrInterviewService;
use Monarc\FrontOffice\Service\AnrService;
use Monarc\FrontOffice\Service\DeliverableGenerationService;
use Monarc\FrontOffice\Table\AnrTable;
use Monarc\FrontOffice\Table\InterviewTable;
use Psr\Container\ContainerInterface;

/** All writes use a connection-local temporary table; installed interview rows remain untouched. */
class InterviewRuntimeChecks
{
    public static function run(ContainerInterface $container, string &$phase): array
    {
        $phase = 'interview metadata and services';
        $manager = $container->get('doctrine.entitymanager.orm_cli');
        $connection = $manager->getConnection();
        self::check(
            (new SchemaValidator($manager))->validateClass($manager->getClassMetadata(Interview::class)) === [],
            'interview mapping'
        );
        self::check(
            !$manager->getClassMetadata(Interview::class)->associationMappings['anr']['joinColumns'][0]['nullable'],
            'interview requires an analysis in metadata'
        );
        self::check(
            $connection->createSchemaManager()->listTableColumns('interviews')['anr_id']->getNotnull(),
            'installed interview anr_id is NOT NULL'
        );
        $interviewTable = $container->get(InterviewTable::class);
        $service = $container->get(AnrInterviewService::class);
        $controllers = $container->get('ControllerManager');
        $controller = $controllers->get(ApiAnrInterviewsController::class);
        $importer = $container->get(InterviewImportProcessor::class);
        $exporter = $container->get(AnrExportService::class);
        $documentService = $container->get(DeliverableGenerationService::class);
        $container->get(AnrService::class);
        $anrIds = $connection->fetchFirstColumn('SELECT id FROM anrs ORDER BY id LIMIT 2');
        self::check(count($anrIds) === 2, 'two local analyses required');
        $anrs = $container->get(AnrTable::class);
        $anr = $anrs->findById((int)$anrIds[0]);
        $other = $anrs->findById((int)$anrIds[1]);
        $original = hash('sha256', json_encode(
            $connection->fetchAllAssociative('SELECT * FROM interviews ORDER BY id')
        ));
        $definition = $connection->createSchemaManager()->introspectTable('interviews');
        foreach ($definition->getForeignKeys() as $key) {
            $definition->removeForeignKey($key->getName());
        }
        $manager->clear();
        // Reattach the two real analyses after clearing cached installed interviews.
        $anr = $anrs->findById((int)$anrIds[0]);
        $other = $anrs->findById((int)$anrIds[1]);
        $createdTemporaryTable = false;
        try {
            foreach ($connection->getDatabasePlatform()->getCreateTableSQL($definition) as $sql) {
                $connection->executeStatement(str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $sql));
                $createdTemporaryTable = true;
            }
            $phase = 'interview rejects missing analysis';
            try {
                $connection->executeStatement('INSERT INTO interviews (anr_id) VALUES (NULL)');
                throw new \RuntimeException('An interview without an analysis was accepted.');
            } catch (\Doctrine\DBAL\Exception\NotNullConstraintViolationException $error) {
                // The database requires the analysis independently of request validation.
            }
            $phase = 'interview CRUD';
            $id = self::request($controller, $anr, 'POST', null, [
                'date' => 'Q4 / free-text date',
                'service' => 'Runtime people',
                'content' => 'Runtime interview',
                'anr' => $other->getId(),
                'creator' => 'Ignored',
            ])['id'];
            self::check($interviewTable->findById($id)->getAnr()->getId() === $anr->getId(), 'body ANR ignored');
            self::check(self::request($controller, $anr, 'GET')['count'] === 1, 'list/count');
            self::request($controller, $anr, 'PATCH', $id, ['content' => 'Patched']);
            self::check(
                self::request($controller, $anr, 'GET', $id)['date'] === 'Q4 / free-text date',
                'partial update'
            );
            self::request($controller, $anr, 'PUT', $id, [
                'date' => 'Full date',
                'service' => 'Full people',
                'content' => 'Full content',
            ]);
            self::check(self::request($controller, $anr, 'GET', $id)['content'] === 'Full content', 'full update');
            $phase = 'interview scoped missing record';
            try {
                $service->getInterviewData($other, $id);
                throw new \RuntimeException('Foreign analysis interview was accepted.');
            } catch (EntityNotFoundException $error) {
                // The inherited lookup uses both id and anr_id.
            }
            $phase = 'interview copy';
            $service->copy($anr, $other);
            self::check((int)$connection->fetchOne('SELECT COUNT(*) FROM interviews') === 1, 'copy defers flush');
            $interviewTable->flush();
            $copy = $interviewTable->findByAnr($other)[0];
            self::check($copy->getId() !== $id && $copy->getContent() === 'Full content', 'fresh copy identity');
            $phase = 'interview import';
            $scheduled = count($manager->getUnitOfWork()->getScheduledEntityInsertions());
            $importer->processInterviewsData($other, [['date' => 'Valid'], 'non-record entry']);
            self::check(
                count($manager->getUnitOfWork()->getScheduledEntityInsertions()) === $scheduled + 1,
                'import skips non-record entries and retains exported field values'
            );
            $importer->processInterviewsData($other, [['date' => null, 'service' => '', 'content' => '0']]);
            $interviewTable->flush();
            self::check(count($interviewTable->findByAnr($other)) === 3, 'nullable import');
            $phase = 'interview export and document';
            $rows = (new \ReflectionMethod(AnrExportService::class, 'prepareInterviewsData'))->invoke($exporter, $anr);
            self::check(
                $rows === [[
                    'id' => $id,
                    'date' => 'Full date',
                    'service' => 'Full people',
                    'content' => 'Full content'
                ]],
                'export contract'
            );
            (new \ReflectionProperty($documentService, 'anr'))->setValue($documentService, $anr);
            (new \ReflectionProperty($documentService, 'currentLangAnrIndex'))
                ->setValue($documentService, $anr->getLanguage());
            $table = (new \ReflectionMethod($documentService, 'generateInterviewsTable'))->invoke($documentService);
            self::check(count($table->getRows()) === 2, 'document header and interview row');
            $phase = 'interview deletion';
            self::request($controller, $anr, 'DELETE', $id);
            self::check(self::request($controller, $anr, 'GET')['count'] === 0, 'delete');
            self::check(count($interviewTable->findByAnr($other)) === 3, 'foreign interviews retained');
        } finally {
            $manager->clear();
            if ($createdTemporaryTable) {
                $connection->executeStatement('DROP TEMPORARY TABLE interviews');
            }
        }
        self::check($original === hash('sha256', json_encode(
            $connection->fetchAllAssociative('SELECT * FROM interviews ORDER BY id')
        )), 'installed interview data unchanged');

        return [
            'crud' => 'passed',
            'scope' => 'passed',
            'requiredAnalysis' => 'metadata/schema checked; NULL rejected',
            'copyImportExportDocument' => 'passed',
            'installedDataUnchanged' => true
        ];
    }

    private static function request(
        ApiAnrInterviewsController $controller,
        Anr $anr,
        string $method,
        ?int $id = null,
        ?array $data = null
    ): array {
        $params = ['anrid' => $anr->getId()];
        if ($id !== null) {
            $params['id'] = $id;
        }
        $request = (new ServerRequest([], [], '/api/client-anr/' . $anr->getId() . '/interviews', $method))
            ->withAttribute(RouteMatch::class, new RouteMatch($params))->withAttribute('anr', $anr)
            ->withParsedBody($data)->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode($data ?? [], JSON_THROW_ON_ERROR)));
        $response = $controller->handle($request);
        self::check($response->getStatusCode() === 200, 'interview response status');

        return json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    private static function check(bool $success, string $message): void
    {
        if (!$success) {
            throw new \RuntimeException('Interview runtime check failed: ' . $message);
        }
    }
}
