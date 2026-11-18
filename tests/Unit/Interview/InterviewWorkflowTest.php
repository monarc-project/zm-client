<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFrontOfficeTests\Unit\Interview;

use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\Tools\SchemaValidator;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use Laminas\Router\RouteMatch;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Controller\ApiAnrInterviewsController;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Interview;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\InputFormatter\Interview\GetInterviewsInputFormatter;
use Monarc\FrontOffice\Import\Processor\InterviewImportProcessor;
use Monarc\FrontOffice\Validator\InputValidator\Interview\InterviewDataInputValidator;
use MonarcFrontOfficeTests\Fixture\InterviewTestEnvironment;
use PHPUnit\Framework\TestCase;

/** @coversNothing */
class InterviewWorkflowTest extends TestCase
{
    private InterviewTestEnvironment $env;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../Fixture/InterviewTestEnvironment.php';
        $identity = $this->createMock(ConnectedUserService::class);
        $identity->method('getConnectedUser')->willReturn(new User([
            'firstname' => 'Interview',
            'lastname' => 'Editor',
            'email' => 'interview@example.invalid',
            'creator' => 'PHP84-04',
            'role' => [],
        ]));
        $this->env = new InterviewTestEnvironment($identity);
    }

    protected function tearDown(): void
    {
        $this->env->manager->close();
    }

    public function testEntityMappingAndInheritedLookupContracts(): void
    {
        self::assertFalse(method_exists(Interview::class, 'setId'));
        self::assertFalse(method_exists(Interview::class, 'getInputFilter'));
        $metadata = $this->env->manager->getClassMetadata(Interview::class);
        self::assertSame([], (new SchemaValidator($this->env->manager))->validateClass($metadata));
        self::assertFalse($metadata->associationMappings['anr']['joinColumns'][0]['nullable']);
        foreach (['date', 'service', 'content'] as $field) {
            self::assertTrue($metadata->fieldMappings[$field]['nullable']);
            self::assertSame(255, $metadata->fieldMappings[$field]['length']);
        }
        self::assertSame(
            \Monarc\Core\Table\AbstractTable::class,
            (new \ReflectionMethod($this->env->interviewTable, 'findByAnr'))->getDeclaringClass()->getName()
        );
        self::assertSame(
            (new \ReflectionClass(\Monarc\Core\Entity\Traits\CreateEntityTrait::class))->getFileName(),
            (new \ReflectionMethod(Interview::class, 'getCreator'))->getFileName()
        );
    }

    public function testAngularCrudFreeTextDatePartialWritesAndAuditEmails(): void
    {
        $data = [
            'date' => '  November / Q4 2026  ',
            'service' => '  Department / People  ',
            'content' => '0',
            'anr' => 2,
            'creator' => 'spoofed',
            'id' => 999
        ];
        $created = $this->request('POST', null, $data);
        self::assertSame('ok', $created['status']);
        self::assertIsInt($created['id']);
        $id = $created['id'];
        $row = $this->request('GET', $id);
        self::assertSame($data['date'], $row['date']);
        self::assertSame($data['service'], $row['service']);
        self::assertSame('0', $row['content']);
        self::assertSame('interview@example.invalid', $row['creator']);
        self::assertNull($row['updatedAt']);
        self::assertSame($this->env->anr, $this->env->interviewTable->findById($id)->getAnr());
        $this->request('PATCH', $id, ['content' => 'Updated', 'anr' => 2]);
        $row = $this->request('GET', $id);
        self::assertSame($data['date'], $row['date']);
        self::assertSame('Updated', $row['content']);
        self::assertSame('interview@example.invalid', $row['updater']);
        $this->request('PUT', $id, ['date' => '2026-11-07', 'service' => 'People', 'content' => 'Full']);
        self::assertSame('Full', $this->request('GET', $id)['content']);
        self::assertSame(['status' => 'ok'], $this->request('DELETE', $id));
        self::assertSame(0, $this->request('GET')['count']);
    }

    /** @dataProvider invalidWrites */
    public function testInvalidPostAndPutInputsNeverMutate(array $data, bool $update): void
    {
        $id = $this->createInterview()->getId();
        try {
            $this->request($update ? 'PUT' : 'POST', $update ? $id : null, $update
                ? $data + ['date' => 'Valid', 'service' => 'Valid', 'content' => 'Valid']
                : $data);
            self::fail('Invalid input must fail before mutation.');
        } catch (Exception $error) {
            self::assertSame(400, $error->getCode());
        }
        self::assertSame(1, $this->env->interviewTable->countAll());
        self::assertSame('Existing', $this->request('GET', $id)['content']);
    }

    public static function invalidWrites(): array
    {
        return [
            [[], false], [['date' => 'date'], false], [['date' => null], true], [['service' => ''], true],
            [['content' => []], true], [['content' => false], true], [['date' => 0], true],
            [['content' => str_repeat('x', 256)], true],
        ];
    }

    /** @dataProvider invalidPatchFields */
    public function testPatchRejectsInvalidSuppliedFieldsWithoutRequiringOtherFields(array $data): void
    {
        $id = $this->createInterview()->getId();
        try {
            $this->request('PATCH', $id, $data);
            self::fail('Invalid interview PATCH must fail before mutation.');
        } catch (Exception $error) {
            self::assertSame(400, $error->getCode());
        }
        self::assertSame('Existing', $this->request('GET', $id)['content']);
    }

    public static function invalidPatchFields(): array
    {
        return [[['date' => null]], [['content' => []]], [['content' => str_repeat('x', 256)]]];
    }

    public function testPatchDoesNotRelaxFullPutRequirements(): void
    {
        $id = $this->createInterview()->getId();
        $this->request('PATCH', $id, ['content' => 'Retained']);
        try {
            $this->request('PUT', $id, ['content' => 'Invalid PUT']);
            self::fail('PUT still requires all three text fields.');
        } catch (Exception $error) {
            self::assertSame(400, $error->getCode());
        }
        self::assertSame('Retained', $this->request('GET', $id)['content']);
    }

    public function testQueriesShareScopeFilteringCountAndPagination(): void
    {
        $this->createInterview(['service' => 'Match one']);
        $this->createInterview(['service' => 'Match two']);
        $this->createInterview(['service' => 'Match foreign'], $this->env->other);
        $data = $this->request('GET', null, null, [
            'filter' => 'Match',
            'limit' => 1,
            'page' => 2,
            'order' => '-id',
            'anr' => 2,
        ]);
        self::assertSame(2, $data['count']);
        self::assertCount(1, $data['interviews']);
        self::assertSame('Match one', $data['interviews'][0]['service']);
        $formatter = new GetInterviewsInputFormatter();
        self::assertSame([], $formatter->format([])->getOrder());
        self::assertSame(['createdAt' => 'ASC'], $formatter->format(['order' => 'createdAt'])->getOrder());
    }

    public function testListQueriesRequireAnAnalysisFilter(): void
    {
        $params = (new GetInterviewsInputFormatter())->format([]);
        foreach (['getList', 'getCount'] as $method) {
            try {
                $this->env->anrInterviewService->$method($params);
                self::fail('Missing analysis scope was accepted');
            } catch (\LogicException $error) {
                self::assertSame('Analysis filter is required', $error->getMessage());
            }
        }
    }

    public function testAllReadsAndWritesUseIdAndAnalysisTogether(): void
    {
        $local = $this->createInterview()->getId();
        $foreign = $this->createInterview([], $this->env->other)->getId();
        foreach (['GET', 'PUT', 'PATCH', 'DELETE'] as $method) {
            try {
                $this->request($method, $foreign, ['date' => 'Date', 'service' => 'People', 'content' => 'Changed']);
                self::fail('A foreign analysis interview must be inaccessible.');
            } catch (EntityNotFoundException $error) {
                self::assertStringContainsString('analysis ID 1', $error->getMessage());
            }
        }
        $response = $this->controller()->handle($this->serverRequest('DELETE', data: [$local, $foreign]));
        self::assertSame(405, $response->getStatusCode());
        self::assertSame(2, $this->env->interviewTable->countAll());
        $this->request('DELETE', $local);
        self::assertSame(1, $this->env->interviewTable->countAll());
    }

    public function testLegacyNullDataAndAuditDatesRemainReadableAndCopyUsesFreshIds(): void
    {
        $this->env->manager->getConnection()->insert('interviews', ['id' => 900, 'anr_id' => 1]);
        $row = $this->request('GET', 900);
        foreach (['date', 'service', 'content', 'createdAt', 'updatedAt'] as $field) {
            self::assertNull($row[$field]);
        }
        self::assertSame('', $row['creator']);
        $this->env->anrInterviewService->copy($this->env->anr, $this->env->other);
        self::assertSame(1, $this->env->interviewTable->countAll());
        $this->env->interviewTable->flush();
        $copy = $this->env->interviewTable->findByAnr($this->env->other)[0];
        self::assertNotSame(900, $copy->getId());
        self::assertNull($copy->getDate());
        self::assertSame('', $copy->getCreator());
    }

    public function testImportsSkipInvalidRowsAndPreserveValidNullableText(): void
    {
        $processor = $this->importer();
        $processor->processInterviewsData($this->env->anr, [
            ['date' => 'Good', 'content' => 'Kept'],
            'invalid record',
            ['date' => null, 'service' => '', 'content' => '0', 'anr' => 2],
        ]);
        self::assertCount(2, $this->env->manager->getUnitOfWork()->getScheduledEntityInsertions());
        self::assertSame(0, $this->env->interviewTable->countAll());
        $this->env->interviewTable->flush();
        $rows = $this->request('GET')['interviews'];
        self::assertCount(2, $rows);
        $rows = array_column($rows, null, 'content');
        self::assertSame('Good', $rows['Kept']['date']);
        $row = $rows['0'];
        self::assertNull($row['date']);
        self::assertSame('', $row['service']);
        self::assertSame('0', $row['content']);
        self::assertSame('interview@example.invalid', $row['creator']);
    }

    public function testCreateCallsInputValidationExactlyOnce(): void
    {
        $data = ['date' => 'Date', 'service' => 'People', 'content' => 'Content'];
        $validator = $this->createMock(InterviewDataInputValidator::class);
        $validator->expects(self::once())->method('isValid')->with($data)->willReturn(true);
        $validator->expects(self::never())->method('validate');
        $validator->method('getValidData')->willReturn($data);
        $controller = new ApiAnrInterviewsController(
            new GetInterviewsInputFormatter(),
            $validator,
            $this->env->anrInterviewService
        );
        $controller->handle($this->serverRequest('POST', null, $data));
        self::assertSame(1, $this->env->interviewTable->countAll());
    }

    public function testRoutePermissionsAndFactoriesRetainTheExistingMiddlewareContract(): void
    {
        $config = require __DIR__ . '/../../../config/module.config.php';
        $router = \Laminas\Router\Http\TreeRouteStack::factory($config['router']);
        $request = (new \Laminas\Http\Request())->setUri('/api/client-anr/1/interviews/9');
        self::assertSame('monarc_api_global_client_anr/interviews', $router->match($request)->getMatchedRouteName());
        self::assertContains('monarc_api_global_client_anr/interviews', $config['roles']['userfo']);
        self::assertSame(
            \Monarc\Core\Table\Factory\ClientEntityManagerFactory::class,
            $config['service_manager']['factories'][\Monarc\FrontOffice\Table\InterviewTable::class]
        );
        self::assertSame(
            \Laminas\Di\Container\AutowireFactory::class,
            $config['service_manager']['factories'][\Monarc\FrontOffice\Service\AnrInterviewService::class]
        );
    }

    public function testExportImportAndCopyPreserveTextAndSourceAuditWithoutReusingIds(): void
    {
        $source = $this->createInterview(['date' => 'Q4', 'service' => 'People', 'content' => 'Exported content']);
        $source->setCreator('Original creator')->setUpdater('Original updater');
        $this->env->interviewTable->save($source);
        $exporterClass = \Monarc\FrontOffice\Export\Service\AnrExportService::class;
        $exporter = (new \ReflectionClass($exporterClass))->newInstanceWithoutConstructor();
        (new \ReflectionProperty($exporterClass, 'interviewTable'))->setValue($exporter, $this->env->interviewTable);
        $rows = (new \ReflectionMethod($exporterClass, 'prepareInterviewsData'))->invoke($exporter, $this->env->anr);
        self::assertSame(
            [['id' => $source->getId(), 'date' => 'Q4', 'service' => 'People', 'content' => 'Exported content']],
            $rows
        );
        $this->importer()->processInterviewsData($this->env->other, $rows);
        $this->env->interviewTable->flush();
        $imported = $this->env->interviewTable->findByAnr($this->env->other)[0];
        self::assertNotSame($source->getId(), $imported->getId());
        self::assertSame('Exported content', $imported->getContent());
        self::assertSame('interview@example.invalid', $imported->getCreator());
        $this->env->anrInterviewService->copy($this->env->anr, $this->env->other);
        $this->env->interviewTable->flush();
        $copies = $this->env->interviewTable->findByAnr($this->env->other);
        self::assertCount(2, $copies);
        $copy = $copies[1];
        self::assertNotSame($source->getId(), $copy->getId());
        self::assertSame('Original creator', $copy->getCreator());
        self::assertSame('Original updater', $copy->getUpdater());
    }

    public function testInterviewDocumentTableAndDocxIncludeAllThreeFields(): void
    {
        $this->createInterview(['content' => 'Foreign interview'], $this->env->other);
        $this->createInterview([
            'date' => 'Q4 interview',
            'service' => 'Department / People',
            'content' => 'Meeting notes',
        ]);
        $class = \Monarc\FrontOffice\Service\DeliverableGenerationService::class;
        $service = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
        $translateService = $this->createMock(\Monarc\Core\Service\TranslateService::class);
        $translateService->method('translate')->willReturnArgument(0);
        foreach ([
            'anr' => $this->env->anr,
            'anrInterviewService' => $this->env->anrInterviewService,
            'translateService' => $translateService,
            'currentLangAnrIndex' => 1
        ] as $name => $value) {
            (new \ReflectionProperty($class, $name))->setValue($service, $value);
        }
        $table = (new \ReflectionMethod($class, 'generateInterviewsTable'))->invoke($service);
        $templatePath = tempnam(sys_get_temp_dir(), 'php84-interview-template-');
        $outputPath = tempnam(sys_get_temp_dir(), 'php84-interview-document-');
        try {
            $template = new \PhpOffice\PhpWord\PhpWord();
            $template->addSection()->addText('${INTERVIEWS}');
            \PhpOffice\PhpWord\IOFactory::createWriter($template, 'Word2007')->save($templatePath);
            $processor = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);
            $processor->setComplexBlock('INTERVIEWS', $table);
            $processor->saveAs($outputPath);
            $archive = new \ZipArchive();
            self::assertTrue($archive->open($outputPath));
            try {
                $xml = $archive->getFromName('word/document.xml');
                self::assertIsString($xml);
                $dom = new \DOMDocument();
                self::assertTrue($dom->loadXML($xml));
                foreach (['Q4 interview', 'Department / People', 'Meeting notes'] as $text) {
                    self::assertStringContainsString($text, $dom->textContent);
                }
                self::assertStringNotContainsString('Foreign interview', $dom->textContent);
            } finally {
                $archive->close();
            }
        } finally {
            unlink($templatePath);
            unlink($outputPath);
        }
    }

    /** @dataProvider unsupportedMethods */
    public function testUnsupportedMethodsReturn405(string $method): void
    {
        self::assertSame(405, $this->controller()->handle($this->serverRequest($method))->getStatusCode());
    }

    public static function unsupportedMethods(): array
    {
        return [['PUT'], ['PATCH'], ['HEAD'], ['OPTIONS'], ['TRACE']];
    }

    private function createInterview(array $data = [], ?Anr $anr = null): Interview
    {
        return $this->env->anrInterviewService->create(
            $anr ?? $this->env->anr,
            $this->env->interviewDataInputValidator->validate($data + [
                'date' => 'Free date',
                'service' => 'People',
                'content' => 'Existing',
            ])
        );
    }

    private function importer(): InterviewImportProcessor
    {
        $connectedUserService = $this->createMock(ConnectedUserService::class);
        $connectedUserService->method('getConnectedUser')->willReturn(new User([
            'firstname' => 'Interview',
            'lastname' => 'Editor',
            'email' => 'interview@example.invalid',
            'creator' => 'PHP84',
            'role' => [],
        ]));

        return new InterviewImportProcessor($this->env->interviewTable, $connectedUserService);
    }

    private function controller(): ApiAnrInterviewsController
    {
        return new ApiAnrInterviewsController(
            new GetInterviewsInputFormatter(),
            $this->env->interviewDataInputValidator,
            $this->env->anrInterviewService
        );
    }

    private function serverRequest(
        string $method,
        ?int $id = null,
        ?array $data = null,
        array $query = []
    ): ServerRequest {
        $body = (new StreamFactory())->createStream(json_encode($data ?? [], JSON_THROW_ON_ERROR));
        $params = $id === null ? ['anrid' => 1] : ['anrid' => 1, 'id' => $id];
        return (new ServerRequest([], [], '/api/client-anr/1/interviews', $method))->withParsedBody($data)
            ->withHeader('Content-Type', 'application/json')->withBody($body)->withQueryParams($query)
            ->withAttribute(RouteMatch::class, new RouteMatch($params))
            ->withAttribute('anr', $this->env->anr);
    }

    private function request(string $method, ?int $id = null, ?array $data = null, array $query = []): array
    {
        $response = $this->controller()->handle($this->serverRequest($method, $id, $data, $query));
        return json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
