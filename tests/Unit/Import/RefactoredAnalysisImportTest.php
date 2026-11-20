<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFrontOfficeTests\Unit\Import;

use Doctrine\ORM\Tools\SchemaTool;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Tests\Fixture\GuideTestEnvironment;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Import\Processor;
use Monarc\FrontOffice\Import\Service\InstanceImportService;
use Monarc\FrontOffice\Table;
use MonarcFrontOfficeTests\Fixture\RopaTestEnvironment;
use PHPUnit\Framework\TestCase;

/** @coversNothing */
class RefactoredAnalysisImportTest extends TestCase
{
    private RopaTestEnvironment $env;
    private Table\QuestionTable $questionTable;
    private Table\InterviewTable $interviewTable;
    private InstanceImportService $instanceImportService;
    private int $flushCount = 0;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../Fixture/RopaTestEnvironment.php';
        require_once __DIR__ . '/../../../../zm-core/tests/Fixture/GuideTestEnvironment.php';
        $connectedUserService = $this->createMock(ConnectedUserService::class);
        $connectedUserService->method('getConnectedUser')->willReturn(GuideTestEnvironment::user());
        $this->env = new RopaTestEnvironment($connectedUserService);
        $metadata = array_map(
            fn (string $class) => $this->env->manager->getClassMetadata($class),
            [Entity\Question::class, Entity\QuestionChoice::class, Entity\Interview::class]
        );
        foreach ((new SchemaTool($this->env->manager))->getCreateSchemaSql($metadata) as $sql) {
            $sql = preg_replace('/^(CREATE INDEX) (\w+) ON (\w+)/', '$1 $3_$2 ON $3', $sql);
            $this->env->manager->getConnection()->executeStatement($sql);
        }
        $this->questionTable = new Table\QuestionTable($this->env->manager);
        $questionChoiceTable = new Table\QuestionChoiceTable($this->env->manager);
        $this->interviewTable = new Table\InterviewTable($this->env->manager);
        $cache = new ImportCacheHelper();
        $anrTable = $this->createMock(Table\AnrTable::class);
        $anrTable->method('flush')->willReturnCallback(function (): void {
            if ($this->flushCount++ === 0) {
                self::assertSame(0, $this->questionTable->countAll());
                self::assertSame(0, $this->interviewTable->countAll());
                self::assertSame(0, $this->env->recordTable->countAll());
                self::assertFalse($this->env->manager->getConnection()->isTransactionActive());
            }
            $this->env->manager->flush();
        });
        $this->instanceImportService = (new \ReflectionClass(InstanceImportService::class))
            ->newInstanceWithoutConstructor();
        foreach ([
            'anrTable' => $anrTable,
            'importCacheHelper' => $cache,
            'questionImportProcessor' => new Processor\QuestionImportProcessor(
                $this->questionTable,
                $questionChoiceTable,
                $cache,
                $connectedUserService
            ),
            'interviewImportProcessor' => new Processor\InterviewImportProcessor(
                $this->interviewTable,
                $connectedUserService
            ),
            'recordImportProcessor' => $this->env->recordImportProcessor,
            'anrMethodStepImportProcessor' => $this->createMock(Processor\AnrMethodStepImportProcessor::class),
            'anrInstanceMetadataFieldImportProcessor' =>
                $this->createMock(Processor\AnrInstanceMetadataFieldImportProcessor::class),
        ] as $name => $value) {
            (new \ReflectionProperty($this->instanceImportService, $name))
                ->setValue($this->instanceImportService, $value);
        }
    }

    protected function tearDown(): void
    {
        $this->env->manager->close();
    }

    public function testMainImportStagesAllDomainsAndFinalizesChoiceResponsesAtTheEnd(): void
    {
        $instances = $this->createMock(Processor\InstanceImportProcessor::class);
        $instances->method('processInstancesData')->willReturn([]);
        $this->setInstanceProcessor($instances);
        [$ids, $errors] = $this->import();
        self::assertSame([], $ids);
        self::assertSame([], $errors);
        self::assertSame(2, $this->flushCount);
        $question = $this->questionTable->findOrderedQuestions($this->env->anr)[0];
        self::assertSame((string)$question->getQuestionChoices()->first()->getId(), $question->getResponse());
        self::assertSame('Imported interview', $this->interviewTable->findByAnr($this->env->anr)[0]->getContent());
        $records = $this->env->recordTable->findByAnr($this->env->anr);
        self::assertCount(2, $records);
        self::assertSame($records[0]->getController(), $records[1]->getController());
        self::assertSame(1, $this->env->recordProcessorTable->countAll());
    }

    public function testLaterImportFailureDoesNotWriteStagedQuestionsOrInterviews(): void
    {
        $instances = $this->createMock(Processor\InstanceImportProcessor::class);
        $instances->method('processInstancesData')->willThrowException(new \RuntimeException('Later import failed'));
        $this->setInstanceProcessor($instances);
        try {
            $this->import();
            self::fail('The later processor must fail.');
        } catch (\RuntimeException $error) {
            self::assertSame('Later import failed', $error->getMessage());
        }
        self::assertSame(0, $this->flushCount);
        self::assertSame(0, $this->questionTable->countAll());
        self::assertSame(0, $this->interviewTable->countAll());
        self::assertSame(0, $this->env->recordTable->countAll());
        self::assertNotEmpty($this->env->manager->getUnitOfWork()->getScheduledEntityInsertions());
        self::assertFalse($this->env->manager->getConnection()->isTransactionActive());
    }

    public function testLegacyAnalysisUsesTheSameProcessorsAndFinalFlush(): void
    {
        $instances = $this->createMock(Processor\InstanceImportProcessor::class);
        $instances->method('processInstancesData')->willReturn([]);
        $this->setInstanceProcessor($instances);
        self::assertSame([[], []], $this->import(legacy: true));
        self::assertSame(2, $this->flushCount);
        self::assertSame(1, $this->questionTable->countAll());
        self::assertSame(1, $this->interviewTable->countAll());
        self::assertSame(2, $this->env->recordTable->countAll());
    }

    public function testFailureInTheLastRecordLeavesEveryDomainUnwritten(): void
    {
        $instances = $this->createMock(Processor\InstanceImportProcessor::class);
        $instances->method('processInstancesData')->willReturn([]);
        $this->setInstanceProcessor($instances);
        try {
            $this->import(invalidRecord: true);
            self::fail('The last record must fail.');
        } catch (\TypeError $error) {
            self::assertStringContainsString('setLabel', $error->getMessage());
        }
        self::assertSame(0, $this->flushCount);
        self::assertSame(0, $this->questionTable->countAll());
        self::assertSame(0, $this->interviewTable->countAll());
        self::assertSame(0, $this->env->recordTable->countAll());
        self::assertSame(0, $this->env->recordActorTable->countAll());
        self::assertFalse($this->env->manager->getConnection()->isTransactionActive());
    }

    private function setInstanceProcessor(Processor\InstanceImportProcessor $processor): void
    {
        (new \ReflectionProperty($this->instanceImportService, 'instanceImportProcessor'))
            ->setValue($this->instanceImportService, $processor);
    }

    private function import(bool $legacy = false, bool $invalidRecord = false): array
    {
        $path = tempnam(sys_get_temp_dir(), 'php84-analysis-import-');
        try {
            $data = [
                'type' => 'anr',
                'monarc_version' => '2.13.1',
                'languageCode' => $this->env->anr->getLanguageCode(),
                'withEval' => false,
                'withMethodSteps' => true,
                'method' => ['questions' => [[
                    'id' => 100,
                    'type' => 2,
                    'label' => 'Imported question',
                    'response' => '200',
                    'questionChoices' => [['id' => 200, 'label' => 'Selected choice']],
                ]]],
                'interviews' => [['content' => 'Imported interview']],
                'gdprRecords' => [
                    RopaTestEnvironment::recordData(),
                    $invalidRecord ? ['name' => []] : RopaTestEnvironment::recordData(),
                ],
                'instances' => [],
                'anrInstanceMetadataFields' => [],
            ];
            if ($legacy) {
                $data['monarc_version'] = '2.13.0';
                $data['method']['interviews'] = $data['interviews'];
                $data['records'] = $data['gdprRecords'];
                unset($data['interviews'], $data['gdprRecords']);
            }
            file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR));

            return $this->instanceImportService->importFromFile($this->env->anr, ['file' => [[
                'error' => UPLOAD_ERR_OK,
                'name' => 'analysis.json',
                'tmp_name' => $path,
            ]]]);
        } finally {
            unlink($path);
        }
    }
}
