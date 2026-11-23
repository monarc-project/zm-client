<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFrontOfficeTests\Unit\Record;

use Doctrine\ORM\Tools\SchemaValidator;
use Doctrine\ORM\EntityNotFoundException;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use Laminas\Router\RouteMatch;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Tests\Fixture\GuideTestEnvironment;
use Monarc\FrontOffice\Controller;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\InputFormatter\Record as Formatter;
use Monarc\FrontOffice\Service\DeliverableGenerationService;
use MonarcFrontOfficeTests\Fixture\RopaTestEnvironment;
use PHPUnit\Framework\TestCase;

/** @coversNothing */
class RopaWorkflowTest extends TestCase
{
    private RopaTestEnvironment $env;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../../zm-core/tests/Fixture/GuideTestEnvironment.php';
        require_once __DIR__ . '/../../Fixture/RopaTestEnvironment.php';
        $identity = $this->createMock(ConnectedUserService::class);
        $identity->method('getConnectedUser')->willReturn(GuideTestEnvironment::user());
        $this->env = new RopaTestEnvironment($identity);
    }

    protected function tearDown(): void
    {
        $this->env->manager->close();
    }

    public function testPlainEntitiesHaveValidMappingsAndGeneratedIdentifiers(): void
    {
        foreach (['Record', 'RecordActor', 'RecordDataCategory', 'RecordPersonalData', 'RecordProcessor',
            'RecordRecipient', 'RecordInternationalTransfer'] as $name) {
            $class = 'Monarc\\FrontOffice\\Entity\\' . $name;
            self::assertFalse(method_exists($class, 'setId'));
            self::assertFalse(method_exists($class, 'getInputFilter'));
            self::assertSame([], (new SchemaValidator($this->env->manager))
                ->validateClass($this->env->manager->getClassMetadata($class)));
            self::assertFalse((new \ReflectionProperty($class, 'id'))->hasDefaultValue());
            self::assertFalse($this->env->manager->getClassMetadata($class)
                ->associationMappings['anr']['joinColumns'][0]['nullable']);
        }
    }

    public function testCompleteRecordExportAndReimportPreserveEveryNestedField(): void
    {
        $source = RopaTestEnvironment::recordData();
        $id = $this->env->importRecord($this->env->anr, $source);
        self::assertIsInt($id);
        $record = $this->env->recordTable->findByIdAndAnr($id, $this->env->anr);
        $data = $this->env->exportRecord($record);
        self::assertSame($source['name'], $data['name']);
        self::assertSame($source['controller'], $data['controller']);
        self::assertSame('Staff', $data['recipients'][0]['description']);
        self::assertSame('internal', $data['recipients'][0]['type']);
        self::assertSame('Processor DPO', $data['processors'][0]['data_protection_officer']['name']);
        self::assertSame('Hosting', $data['processors'][0]['activities']);
        self::assertSame('month(s)', $data['personal_data'][0]['retention_period_mode']);
        self::assertSame('Contact details', $data['personal_data'][0]['data_categories'][0]['name']);
        $copy = $this->env->importRecord($this->env->other, $data);
        self::assertSame($data, $this->env->exportRecord($this->env->recordTable->findById($copy)));
        self::assertSame('guide@example.invalid', $record->getCreator());
        self::assertSame(1, $this->request('records', 'GET')['count']);
        self::assertCount(1, $this->request('records', 'GET', $id)['personalData']);
    }

    public function testAngularCrudUsesMiddlewareScopeIgnoresMetadataAndPreservesPartialNullEdits(): void
    {
        $actor = $this->request('actors', 'POST', null, ['label' => '0', 'contact' => null, 'anr' => 2]);
        $id = $this->request('records', 'POST', null, [
            'label' => '  Activity  ',
            'controller' => $actor,
            'creator' => 'spoofed',
            'anr' => 2,
            'id' => 999
        ])['id'];
        $row = $this->request('records', 'GET', $id);
        self::assertSame('  Activity  ', $row['label']);
        self::assertSame('0', $row['controller']['label']);
        self::assertNull($row['controller']['contact']);
        self::assertSame('guide@example.invalid', $row['creator']);
        self::assertSame(1, $this->env->recordTable->findById($id)->getAnr()->getId());
        $this->request('records', 'PUT', $id, ['purposes' => '0']);
        $saved = $this->request('records', 'PATCH', $id, ['purposes' => null, 'secMeasures' => '']);
        $row = $this->request('records', 'GET', $id);
        self::assertNull($row['purposes']);
        self::assertSame('', $row['secMeasures']);
        self::assertSame('0', $row['controller']['label']);
        self::assertSame('guide@example.invalid', $row['updater']);
        self::assertSame($row['updatedAt'], $saved['updatedAt']);
        $this->request('records', 'DELETE', $id);
        self::assertSame(0, $this->env->recordTable->countAll());
        self::assertSame(1, $this->env->recordActorTable->countAll());
    }

    public function testValidatedDataPreservesOmittedFieldsAndExplicitNullCanClearThem(): void
    {
        $id = $this->env->importRecord($this->env->anr, RopaTestEnvironment::recordData());
        $record = $this->env->recordTable->findById($id);
        $label = $record->getLabel();
        $controller = $record->getController();
        $jointControllers = $record->getJointControllers()->toArray();
        $recipients = $record->getRecipients()->toArray();
        $processors = $record->getProcessors()->toArray();

        $this->request('records', 'PUT', $id, ['purposes' => 'New purpose']);
        self::assertSame($label, $record->getLabel());
        self::assertSame($controller, $record->getController());
        self::assertSame($jointControllers, $record->getJointControllers()->toArray());
        self::assertSame($recipients, $record->getRecipients()->toArray());
        self::assertSame($processors, $record->getProcessors()->toArray());

        $this->request('records', 'PUT', $id, ['controller' => null, 'recipients' => null]);
        self::assertNull($record->getController());
        self::assertCount(0, $record->getRecipients());
        self::assertSame($label, $record->getLabel());
        self::assertSame('New purpose', $record->getPurposes());
    }

    public function testRecordPatchValidatesOnlySuppliedFieldsBeforeMutation(): void
    {
        $id = $this->env->importRecord($this->env->anr, RopaTestEnvironment::recordData());
        $record = $this->env->recordTable->findById($id);
        $label = $record->getLabel();
        $securityMeasures = $record->getSecMeasures();
        $this->request('records', 'PATCH', $id, ['purposes' => 'Updated purpose']);
        self::assertSame('Updated purpose', $record->getPurposes());
        self::assertSame($label, $record->getLabel());
        self::assertSame($securityMeasures, $record->getSecMeasures());
        try {
            $this->request('records', 'PATCH', $id, ['label' => [], 'purposes' => 'Invalid']);
            self::fail('Invalid record PATCH must fail before mutation.');
        } catch (Exception $error) {
            self::assertSame(400, $error->getCode());
        }
        self::assertSame('Updated purpose', $record->getPurposes());
        self::assertSame($label, $record->getLabel());
    }

    public function testForeignReferencesAreRejectedBeforeMutatingAnyFieldsOrLinks(): void
    {
        $id = $this->env->importRecord($this->env->anr, RopaTestEnvironment::recordData());
        $actor = $this->env->anrRecordActorService->create($this->env->other, ['label' => 'Foreign']);
        try {
            $this->request('records', 'PUT', $id, ['label' => 'Changed', 'controller' => ['id' => $actor->getId()]]);
            self::fail('Foreign reference accepted');
        } catch (EntityNotFoundException $error) {
            self::assertSame('Customer records', $this->env->recordTable->findById($id)->getLabel());
        }
        $this->expectException(EntityNotFoundException::class);
        $this->env->anrRecordService->getRecordData($this->env->other, $id);
    }

    public function testNestedCategoriesAreValidatedAndClearedWithoutRemovingSharedLinks(): void
    {
        $id = $this->env->importRecord($this->env->anr, RopaTestEnvironment::recordData());
        $record = $this->env->recordTable->findById($id);
        $pd = $record->getPersonalData()->first();
        $this->request('personalData', 'PUT', $pd->getId(), [
            'record' => $id,
            'dataCategories' => [['label' => 'New category']],
            'retentionPeriod' => '0',
            'retentionPeriodMode' => '2',
            'dataSubject' => null
        ]);
        self::assertSame('New category', $pd->getDataCategories()->first()->getLabel());
        self::assertSame(0, $pd->getRetentionPeriod());
        self::assertSame(2, $pd->getRetentionPeriodMode());
        self::assertNull($pd->getDataSubject());
        self::assertSame(2, $this->env->recordDataCategoryTable->countAll());
        $this->request('personalData', 'PUT', $pd->getId(), ['record' => $id, 'dataCategories' => []]);
        self::assertSame(0, $pd->getDataCategories()->count());
        self::assertSame(2, $this->env->recordDataCategoryTable->countAll());
        try {
            $this->request('personalData', 'PUT', $pd->getId(), [
                'record' => $id,
                'dataCategories' => [['label' => ['bad']]],
            ]);
            self::fail('Invalid nested category accepted');
        } catch (Exception $error) {
            self::assertSame(400, $error->getCode());
            self::assertSame(0, $pd->getDataCategories()->count());
        }
    }

    public function testLinkReplacementRemovesOwnedChildrenAndRetainsSharedActorsProcessorsAndRecipients(): void
    {
        $id = $this->env->importRecord($this->env->anr, RopaTestEnvironment::recordData());
        $record = $this->env->recordTable->findById($id);
        $copyId = $this->env->anrRecordService->duplicate($this->env->anr, $id, 'Duplicate');
        self::assertCount(1, $this->env->recordTable->findById($copyId)->getPersonalData());
        self::assertCount(1, $this->env->recordTable->findById($copyId)->getProcessors());
        $this->request('records', 'PUT', $id, [
            'personalData' => [],
            'internationalTransfers' => [],
            'processors' => [],
            'recipients' => [],
            'controller' => null
        ]);
        self::assertSame(0, $record->getPersonalData()->count());
        self::assertSame(1, $this->env->recordPersonalDataTable->countAll());
        self::assertSame(1, $this->env->recordInternationalTransferTable->countAll());
        self::assertSame(1, $this->env->recordProcessorTable->countAll());
        self::assertSame(1, $this->env->recordRecipientTable->countAll());
        self::assertNotNull($this->env->recordTable->findById($copyId)->getController());
        $this->env->anrRecordService->delete($this->env->anr, $id);
        $this->env->anrRecordService->delete($this->env->anr, $copyId);
        foreach (['recordTable', 'recordPersonalDataTable', 'recordInternationalTransferTable'] as $table) {
            self::assertSame(0, $this->env->$table->countAll());
        }
        self::assertSame(6, $this->env->recordActorTable->countAll());
        foreach (['recordProcessorTable', 'recordRecipientTable', 'recordDataCategoryTable'] as $table) {
            self::assertSame(1, $this->env->$table->countAll());
        }
    }

    public function testRecordWritesUseOneFinalFlushWithoutServiceTransactions(): void
    {
        $id = $this->env->importRecord($this->env->anr, RopaTestEnvironment::recordData());
        $listener = new class {
            public array $transactionLevels = [];

            public function preFlush(\Doctrine\ORM\Event\PreFlushEventArgs $event): void
            {
                $this->transactionLevels[] = $event->getObjectManager()
                    ->getConnection()->getTransactionNestingLevel();
            }
        };
        $this->env->manager->getEventManager()->addEventListener(['preFlush'], $listener);

        $record = $this->env->anrRecordService->create($this->env->anr, ['label' => 'New']);
        $this->env->anrRecordService->update($this->env->anr, $id, [
            'personalData' => [],
            'internationalTransfers' => [],
            'controller' => null,
            'processors' => [],
            'recipients' => [],
        ]);
        $copyId = $this->env->anrRecordService->duplicate($this->env->anr, $id, 'Copy');
        $this->env->anrRecordService->delete($this->env->anr, $id);
        $this->env->anrRecordService->delete($this->env->anr, $copyId);
        $this->env->anrRecordService->delete($this->env->anr, $record->getId());

        self::assertSame([0, 0, 0, 0, 0, 0], $listener->transactionLevels);
        self::assertSame(0, $this->env->recordPersonalDataTable->countAll());
        self::assertSame(0, $this->env->recordInternationalTransferTable->countAll());
        self::assertSame(6, $this->env->recordActorTable->countAll());
    }

    public function testRetainingOwnedChildrenDoesNotScheduleTheirDeletion(): void
    {
        $id = $this->env->importRecord($this->env->anr, RopaTestEnvironment::recordData());
        $record = $this->env->recordTable->findById($id);
        $personalDataId = $record->getPersonalData()->first()->getId();
        $transferId = $record->getInternationalTransfers()->first()->getId();
        $this->env->anrRecordService->update($this->env->anr, $id, [
            'personalData' => [$personalDataId],
            'internationalTransfers' => [$transferId],
        ]);
        $this->env->manager->clear();

        $record = $this->env->recordTable->findById($id);
        self::assertSame($personalDataId, $record->getPersonalData()->first()->getId());
        self::assertSame($transferId, $record->getInternationalTransfers()->first()->getId());
    }

    public function testNestedCreationCanDeferAllWritesUntilTheCallerFlushes(): void
    {
        $record = $this->env->anrRecordService->create($this->env->anr, ['label' => 'Activity']);
        $personalData = $this->env->anrRecordPersonalDataService->create($this->env->anr, [
            'record' => $record->getId(),
            'dataCategories' => [['label' => 'Pending category']],
        ], false);
        self::assertSame(0, $this->env->recordPersonalDataTable->countAll());
        self::assertSame(0, $this->env->recordDataCategoryTable->countAll());
        self::assertSame($record, $personalData->getRecord());

        $this->env->recordTable->flush();
        self::assertSame(1, $this->env->recordPersonalDataTable->countAll());
        self::assertSame(1, $this->env->recordDataCategoryTable->countAll());
        self::assertSame('Pending category', $personalData->getDataCategories()->first()->getLabel());
    }

    public function testAnalysisCopyHasFreshIdentitiesCorrectOwnersAndPreservedAuthors(): void
    {
        $id = $this->env->importRecord($this->env->anr, RopaTestEnvironment::recordData());
        $record = $this->env->recordTable->findById($id);
        $record->setCreator('Original author')->setUpdater('Original updater');
        $this->env->recordTable->save($record);
        $this->env->anrRecordService->copy($this->env->anr, $this->env->other);
        self::assertSame(1, count($this->env->recordTable->findByAnr($this->env->anr)));
        self::assertSame(0, count($this->env->recordTable->findByAnr($this->env->other)));
        $this->env->recordTable->flush();
        $copy = $this->env->recordTable->findByAnr($this->env->other)[0];
        self::assertNotSame($id, $copy->getId());
        self::assertSame('Original author', $copy->getCreator());
        self::assertSame('Original updater', $copy->getUpdater());
        self::assertNotSame($record->getController()->getId(), $copy->getController()->getId());
        self::assertSame($this->env->other, $copy->getController()->getAnr());
        self::assertSame($copy, $copy->getPersonalData()->first()->getRecord());
        self::assertSame($copy, $copy->getInternationalTransfers()->first()->getRecord());
        self::assertSame($this->env->other, $copy->getPersonalData()->first()->getDataCategories()->first()->getAnr());
        self::assertSame(
            $this->env->exportRecord($record),
            $this->env->exportRecord($copy)
        );
    }

    public function testProcessorPreloadsOnceAndReusesReferencesBeforeTheFinalFlush(): void
    {
        $data = RopaTestEnvironment::recordData();
        $logger = new \Doctrine\DBAL\Logging\DebugStack();
        $connection = $this->env->manager->getConnection();
        $connection->getConfiguration()->setSQLLogger($logger);
        $records = $this->env->recordImportProcessor->processRecordsData($this->env->anr, [$data, $data]);
        $connection->getConfiguration()->setSQLLogger(null);

        self::assertCount(4, $logger->queries);
        foreach ($logger->queries as $query) {
            self::assertStringStartsWith('SELECT', $query['sql']);
        }
        self::assertSame(0, $this->env->recordTable->countAll());
        self::assertFalse($connection->isTransactionActive());
        self::assertSame($records[0]->getController(), $records[1]->getController());
        self::assertSame($records[0]->getProcessors()->first(), $records[1]->getProcessors()->first());
        self::assertSame($records[0]->getRecipients()->first(), $records[1]->getRecipients()->first());
        self::assertFalse((new \ReflectionProperty(Entity\Record::class, 'id'))->isInitialized($records[0]));
        $this->env->recordTable->flush();
        self::assertSame(2, $this->env->recordTable->countAll());
        self::assertSame(1, $this->env->recordProcessorTable->countAll());
        self::assertSame(1, $this->env->recordDataCategoryTable->countAll());
    }

    public function testImportPreservesDifferentContentForSameNamedActorsAndProcessors(): void
    {
        $firstData = RopaTestEnvironment::recordData();
        $secondData = $firstData;
        $secondData['controller']['contact'] = 'different@example.invalid';
        $secondData['processors'][0]['activities'] = 'Different activity';
        $secondData['processors'][0]['representative']['contact'] = 'representative@example.invalid';
        [$first, $second] = $this->env->recordImportProcessor
            ->processRecordsData($this->env->anr, [$firstData, $secondData]);
        self::assertNotSame($first->getController(), $second->getController());
        self::assertNotSame($first->getProcessors()->first(), $second->getProcessors()->first());
        self::assertSame($firstData['controller']['contact'], $first->getController()->getContact());
        self::assertSame('Hosting', $first->getProcessors()->first()->getActivities());
        self::assertSame('Different activity', $second->getProcessors()->first()->getActivities());
        $this->env->recordTable->flush();

        $thirdId = $this->env->importRecord($this->env->anr, $secondData);
        $third = $this->env->recordTable->findById($thirdId);
        self::assertSame($second->getController(), $third->getController());
        self::assertSame($second->getProcessors()->first(), $third->getProcessors()->first());
        self::assertSame('Hosting', $first->getProcessors()->first()->getActivities());
    }

    public function testEmptyJsonExportsCanBeImportedWithoutLegacyDecryption(): void
    {
        foreach (['', 'secret'] as $password) {
            [, $content] = $this->env->recordExportService->export($this->env->anr, null, $password);
            $path = tempnam(sys_get_temp_dir(), 'ropa-empty-');
            try {
                file_put_contents($path, $content);
                self::assertSame([[], []], $this->env->recordImportService->importFromFile($this->env->other, [
                    'isJson' => true,
                    'password' => $password,
                    'file' => ['name' => 'empty.json', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK],
                ]));
            } finally {
                unlink($path);
            }
        }
    }

    public function testImportReusesSharedReferencesWithinTheTargetAnalysis(): void
    {
        $data = RopaTestEnvironment::recordData();
        $data['joint_controllers'][] = $data['joint_controllers'][0];
        $first = $this->env->importRecord($this->env->anr, $data);
        $second = $this->env->importRecord($this->env->anr, $data);
        $a = $this->env->recordTable->findById($first);
        $b = $this->env->recordTable->findById($second);
        self::assertCount(1, $a->getJointControllers());
        self::assertSame($a->getController(), $b->getController());
        self::assertSame($a->getProcessors()->first(), $b->getProcessors()->first());
        self::assertSame($a->getRecipients()->first(), $b->getRecipients()->first());
        self::assertSame(1, $this->env->recordProcessorTable->countAll());
        self::assertSame(1, $this->env->recordRecipientTable->countAll());
        self::assertSame(1, $this->env->recordDataCategoryTable->countAll());
    }

    public function testImportSkipsMissingRecordsAndNonRecordEntriesWithoutLosingContent(): void
    {
        self::assertNull($this->env->importRecord($this->env->anr, []));
        self::assertSame(0, $this->env->recordActorTable->countAll());
        $data = RopaTestEnvironment::recordData();
        $data['joint_controllers'][] = 'invalid entry';
        $data['recipients'][] = 'invalid';
        $data['personal_data'][0]['data_categories'][] = 'invalid entry';
        $id = $this->env->importRecord($this->env->anr, $data);
        $record = $this->env->recordTable->findById($id);
        self::assertCount(1, $record->getJointControllers());
        self::assertCount(1, $record->getRecipients());
        self::assertCount(1, $record->getPersonalData()->first()->getDataCategories());
    }

    public function testJsonEncryptedAndLegacyFilesAndCsvAreImportable(): void
    {
        $id = $this->env->importRecord($this->env->anr, RopaTestEnvironment::recordData());
        foreach (['', 'secret'] as $password) {
            [, $content] = $this->env->recordExportService->export($this->env->anr, $id, $password);
            $legacy = $password === '' ? base64_encode($this->encrypt($content, '')) : base64_encode($content);
            foreach ([$content, $legacy] as $bytes) {
                $path = tempnam(sys_get_temp_dir(), 'ropa-file-');
                try {
                    file_put_contents($path, $bytes);
                    [$ids, $errors] = $this->env->recordImportService->importFromFile(
                        $this->env->other,
                        [
                            'isJson' => 'true',
                            'password' => $password,
                            'file' => [
                                'name' => 'record.json',
                                'tmp_name' => $path,
                                'error' => UPLOAD_ERR_OK
                            ]
                        ]
                    );
                    self::assertSame([], $errors);
                    self::assertCount(1, $ids);
                } finally {
                    unlink($path);
                }
            }
        }
        [$ids, $errors] = $this->env->recordImportService->importFromFile($this->env->other, [
            'isJson' => 'false',
            'file' => ['name' => 'record.csv'],
            'csv' => [
                [
                    'name' => 'CSV record',
                    'purposes' => '0',
                    'controller name' => 'CSV controller',
                    'data subject' => 'Customers',
                    'data categories' => 'Email, Phone',
                    'retention period' => '0',
                    'retention period unit' => 'day(s)'
                ],
            ],
        ]);
        self::assertSame([], $errors);
        self::assertCount(1, $ids);
        self::assertSame('0', $this->env->recordTable->findById($ids[0])->getPurposes());
        self::assertCount(
            2,
            $this->env->recordTable->findById($ids[0])->getPersonalData()->first()->getDataCategories()
        );
    }

    public function testCsvImportPreservesNestedFieldsAndGroupsContinuationRowsWithoutAnUpload(): void
    {
        [$ids, $errors] = $this->env->recordImportService->importFromFile($this->env->anr, [
            'isJson' => 'false',
            'csv' => [
                'blank' => ['name' => '  '],
                'invalid' => null,
                'first' => [
                    'name' => 'Customer records',
                    'purposes' => '  Provide service  ',
                    'security measures' => 'Encryption',
                    'controller name' => 'Controller',
                    'controller contact' => 'controller@example.invalid',
                    'representative name' => 'Representative',
                    'data protection officer name' => 'DPO',
                    'joint controllers name' => 'Joint controller',
                    'data recipient' => 'Recipient',
                    'data recipient type' => 'internal',
                    'description' => 'Email',
                    'data subject' => 'Customers',
                    'data categories' => 'Contact details, Phone',
                    'retention period' => '0',
                    'retention period unit' => 'month(s)',
                    'retention period description' => 'After contract',
                    'organisation of international transfer' => 'Provider',
                    'country' => 'Luxembourg',
                    'documents' => 'Contract',
                    'data processor name' => 'Processor',
                    'data processor contact' => 'processor@example.invalid',
                    'activities' => 'Hosting',
                    'data processor security measures' => 'Backups',
                    'data processor representative name' => 'Processor representative',
                    'data processor representative contact' => 'representative@example.invalid',
                    'data processor data protection officer name' => 'Processor DPO',
                    'data processor data protection officer contact' => 'dpo@example.invalid',
                ],
                'continuation' => [
                    'joint controllers name' => 'Another joint controller',
                    'joint controllers contact' => 'joint@example.invalid',
                    'data recipient' => 'External recipient',
                    'data recipient type' => 'external',
                    'controller contact' => 'Ignore contact without name',
                    'data subject' => 'Employees',
                    'retention period' => '1',
                    'retention period unit' => 'year(s)',
                ],
                'second' => ['name' => '0', 'purposes' => '0'],
            ],
        ]);

        self::assertSame([], $errors);
        self::assertCount(2, $ids);
        $expected = RopaTestEnvironment::recordData();
        $expected['purposes'] = '  Provide service  ';
        $expected['personal_data'][0]['retention_period'] = 0;
        $expected['personal_data'][0]['data_categories'][] = ['name' => 'Phone'];
        $expected['personal_data'][] = [
            'data_subject' => 'Employees',
            'data_categories' => [],
            'retention_period' => 1,
            'retention_period_mode' => 'year(s)',
        ];
        $expected['joint_controllers'][] = [
            'name' => 'Another joint controller',
            'contact' => 'joint@example.invalid',
        ];
        $expected['recipients'][0]['description'] = 'Email';
        $expected['recipients'][] = ['name' => 'External recipient', 'type' => 'external'];
        $expected['international_transfers'][0]['description'] = 'Email';
        $expected['processors'][0]['representative']['contact'] = 'representative@example.invalid';
        $expected['processors'][0]['data_protection_officer']['contact'] = 'dpo@example.invalid';
        $copyId = $this->env->importRecord($this->env->other, $expected);
        self::assertSame(
            $this->env->exportRecord($this->env->recordTable->findById($copyId)),
            $this->env->exportRecord($this->env->recordTable->findById($ids[0]))
        );
        $second = $this->env->recordTable->findById($ids[1]);
        self::assertSame('0', $second->getLabel());
        self::assertSame('0', $second->getPurposes());
        self::assertCount(0, $second->getJointControllers());
        self::assertCount(0, $second->getPersonalData());
    }

    public function testCsvImportDoesNotAttributeInvalidLeadingDataToTheFollowingRecord(): void
    {
        [$ids, $errors] = $this->env->recordImportService->importFromFile($this->env->anr, [
            'isJson' => false,
            'csv' => [
                ['purposes' => 'Missing record name'],
                ['name' => 'Valid record'],
            ],
        ]);
        self::assertCount(1, $ids);
        self::assertSame('Valid record', $this->env->recordTable->findById($ids[0])->getLabel());
        self::assertSame(['The file "" can\'t be imported'], $errors);
    }

    public function testJsonImportStillRequiresAnUploadedFile(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('File missing');
        $this->expectExceptionCode(412);
        $this->env->recordImportService->importFromFile($this->env->anr, []);
    }

    public function testInvalidJsonAndPasswordsReturnImportErrorsWithoutPersistingRecords(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ropa-invalid-');
        try {
            foreach ([
                ['malformed JSON', null],
                ['null', null],
                ['false', null],
                ['"text"', null],
                [$this->encrypt('[{"name":"Encrypted record"}]', 'secret'), 'wrong password'],
            ] as [$content, $password]) {
                file_put_contents($path, $content);
                self::assertSame(
                    [[], ['The file "invalid.json" can\'t be imported']],
                    $this->env->recordImportService->importFromFile($this->env->anr, [
                        'file' => ['name' => 'invalid.json', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK],
                        'password' => $password,
                    ])
                );
                self::assertSame(0, $this->env->recordTable->countAll());
                self::assertSame([], $this->env->manager->getUnitOfWork()->getScheduledEntityInsertions());
            }
        } finally {
            unlink($path);
        }
    }

    public function testJsonImportReportsInvalidEntriesAndStillImportsValidRecords(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ropa-mixed-');
        try {
            file_put_contents($path, json_encode([null, [], ['name' => 'Valid record'], 'invalid']));
            [$ids, $errors] = $this->env->recordImportService->importFromFile($this->env->anr, [
                'file' => ['name' => 'mixed.json', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK],
            ]);
            self::assertCount(1, $ids);
            self::assertSame('Valid record', $this->env->recordTable->findById($ids[0])->getLabel());
            self::assertSame(array_fill(0, 3, 'The file "mixed.json" can\'t be imported'), $errors);
        } finally {
            unlink($path);
        }
    }

    /** @dataProvider scopedServices */
    public function testEveryScopedServiceRejectsForeignAnalysisReadsAndWrites(
        string $serviceName,
        string $tableName,
        string $getDataMethod
    ): void {
        $this->env->importRecord($this->env->other, RopaTestEnvironment::recordData());
        $entity = $this->env->$tableName->findByAnr($this->env->other)[0];
        foreach ([$getDataMethod, 'update'] as $method) {
            try {
                $this->env->$serviceName->$method($this->env->anr, $entity->getId(), ['label' => 'Changed']);
                self::fail('Foreign record accepted');
            } catch (EntityNotFoundException $error) {
                self::assertSame(0, count($this->env->$tableName->findByAnr($this->env->anr)));
            }
        }
        $resources = [
            'anrRecordService' => ['records', 'records'],
            'anrRecordActorService' => ['actors', 'record-actors'],
            'anrRecordDataCategoryService' => ['dataCategories', 'record-data-categories'],
            'anrRecordPersonalDataService' => ['personalData', 'record-personal-data'],
            'anrRecordProcessorService' => ['processors', 'record-processors'],
            'anrRecordRecipientService' => ['recipients', 'record-recipients'],
            'anrRecordInternationalTransferService' => ['internationalTransfers', 'record-international-transfers'],
        ];
        [$resource, $responseKey] = $resources[$serviceName];
        $query = ['anr' => $this->env->other->getId()];
        $list = $this->request($resource, 'GET', null, null, $query);
        self::assertSame(0, $list['count']);
        self::assertSame([], $list[$responseKey]);

        $this->env->importRecord($this->env->anr, RopaTestEnvironment::recordData());
        $expectedIds = array_map(
            static fn ($row): int => $row->getId(),
            $this->env->$tableName->findByAnr($this->env->anr)
        );
        $list = $this->request($resource, 'GET', null, null, $query);
        self::assertSame(count($expectedIds), $list['count']);
        self::assertSame($expectedIds, array_column($list[$responseKey], 'id'));
    }

    public static function scopedServices(): array
    {
        return array_map(
            static fn ($name): array => [
                'anr' . $name . 'Service', lcfirst($name) . 'Table', 'get' . $name . 'Data',
            ],
            ['Record', 'RecordActor', 'RecordDataCategory', 'RecordPersonalData', 'RecordProcessor',
            'RecordRecipient',
            'RecordInternationalTransfer']
        );
    }

    public function testNestedForeignCategoryFailsBeforeAnyNewCategoryIsScheduled(): void
    {
        $id = $this->env->importRecord($this->env->anr, RopaTestEnvironment::recordData());
        $pd = $this->env->recordTable->findById($id)->getPersonalData()->first();
        $foreign = $this->env->anrRecordDataCategoryService->create($this->env->other, ['label' => 'Foreign']);
        try {
            $this->request('personalData', 'PUT', $pd->getId(), [
            'record' => $id,
                'dataCategories' => [['label' => 'Do not create'], ['id' => $foreign->getId()]],
            ]);
            self::fail('Foreign category accepted');
        } catch (EntityNotFoundException $error) {
            self::assertSame(2, $this->env->recordDataCategoryTable->countAll());
            self::assertSame([], $this->env->manager->getUnitOfWork()->getScheduledEntityInsertions());
            self::assertSame('Contact details', $pd->getDataCategories()->first()->getLabel());
        }
    }

    public function testFileBatchWritesOnlyAtTheFinalFlushAndInvalidRowsAreSkipped(): void
    {
        $connection = $this->env->manager->getConnection();
        $connection->executeStatement("CREATE TRIGGER record_fail BEFORE INSERT ON records
            WHEN NEW.label = 'Stop' BEGIN SELECT RAISE(ABORT, 'test failure'); END");
        $path = tempnam(sys_get_temp_dir(), 'ropa-rollback-');
        try {
            file_put_contents($path, json_encode([RopaTestEnvironment::recordData(), ['name' => 'Stop']]));
            try {
                $this->env->recordImportService->importFromFile($this->env->anr, [
                    'isJson' => true,
                    'file' => ['name' => 'batch.json', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK]
                ]);
                self::fail('Database errors must propagate');
            } catch (\Doctrine\DBAL\Exception $error) {
                self::assertSame(0, (int)$connection->fetchOne('SELECT COUNT(*) FROM records'));
                self::assertSame(0, (int)$connection->fetchOne('SELECT COUNT(*) FROM record_actors'));
                self::assertSame(0, (int)$connection->fetchOne('SELECT COUNT(*) FROM record_processors'));
                self::assertFalse($connection->isTransactionActive());
            }
        } finally {
            unlink($path);
        }
    }

    public function testProcessorTextIsCopiedVerbatimEvenWhenItLooksLikeJson(): void
    {
        $id = $this->env->importRecord($this->env->anr, RopaTestEnvironment::recordData());
        $processor = $this->env->recordTable->findById($id)->getProcessors()->first();
        $activities = json_encode([$id => 'Keep', 999 => 'Also keep'], JSON_THROW_ON_ERROR);
        $secMeasures = '["Backups", "Encryption"]';
        $processor->setActivities($activities)->setSecMeasures($secMeasures);
        $this->env->recordProcessorTable->save($processor);
        $this->env->anrRecordService->copy($this->env->anr, $this->env->other);
        self::assertSame([], $this->env->recordTable->findByAnr($this->env->other));
        $this->env->recordTable->flush();
        $copy = $this->env->recordTable->findByAnr($this->env->other)[0];
        self::assertSame($activities, $copy->getProcessors()->first()->getActivities());
        self::assertSame($secMeasures, $copy->getProcessors()->first()->getSecMeasures());

        $duplicateId = $this->env->anrRecordService->duplicate($this->env->anr, $id, 'Text copy');
        $duplicate = $this->env->recordTable->findById($duplicateId);
        self::assertSame($activities, $duplicate->getProcessors()->first()->getActivities());
        self::assertSame($secMeasures, $duplicate->getProcessors()->first()->getSecMeasures());
        self::assertSame($activities, $processor->getActivities());
        self::assertSame($secMeasures, $processor->getSecMeasures());
    }

    public function testRoutesStayAuthorizedAndSpecificRecordChildrenAreNotCapturedByTheCrudRoute(): void
    {
        $config = require __DIR__ . '/../../../config/module.config.php';
        $router = \Laminas\Router\Http\TreeRouteStack::factory($config['router']);
        foreach ([
            'records' => 'records',
            'record_actors' => 'record-actors',
            'record_data_categories' => 'record-data-categories',
            'record_processors' => 'record-processors',
            'record_recipients' => 'record-recipients',
            'record_personal_data' => 'record-personal-data',
            'record_international_transfers' => 'record-international-transfers',
            'records_export' => 'records/export',
            'record_export' => 'records/1/export',
            'record_import' => 'records/import',
            'record_duplicate' => 'records/duplicate'
        ] as $route => $path) {
            $request = (new \Laminas\Http\Request())->setUri('/api/client-anr/1/' . $path);
            self::assertSame('monarc_api_global_client_anr/' . $route, $router->match($request)->getMatchedRouteName());
            self::assertContains('monarc_api_global_client_anr/' . $route, $config['roles']['userfo']);
        }
        $request = (new \Laminas\Http\Request())->setUri('/api/client-anr/1/records/unknown');
        self::assertNull($router->match($request));
    }

    public function testRopaReportSectionsRenderCompleteAndNullableRecords(): void
    {
        $id = $this->env->importRecord($this->env->anr, RopaTestEnvironment::recordData());
        $class = DeliverableGenerationService::class;
        $service = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
        $translate = $this->createMock(\Monarc\Core\Service\TranslateService::class);
        $translate->method('translate')->willReturnArgument(0);
        foreach ([
            'anr' => $this->env->anr,
            'recordTable' => $this->env->recordTable,
            'translateService' => $translate,
            'currentLangAnrIndex' => 1,
            'currentOutputFormat' => 'docx'
        ] as $name => $value) {
            (new \ReflectionProperty($class, $name))->setValue($service, $value);
        }
        foreach (['generateTableRecordGDPR', 'generateTableRecordActors', 'generateTableRecordPersonalData',
            'generateTableRecordRecipients', 'generateTableRecordInternationalTransfers',
            'generateTableRecordProcessors', 'generateTableAllRecordsGDPR'] as $method) {
            $xml = (new \ReflectionMethod($class, $method))->invoke($service, $id);
            self::assertIsString($xml);
            self::assertStringContainsString('<w:', $xml);
        }
        $path = tempnam(sys_get_temp_dir(), 'ropa-document-');
        try {
            $word = new \PhpOffice\PhpWord\PhpWord();
            $word->addSection()->addText('ROPA placeholder');
            \PhpOffice\PhpWord\IOFactory::createWriter($word, 'Word2007')->save($path);
            $zip = new \ZipArchive();
            self::assertTrue($zip->open($path));
            $documentXml = $zip->getFromName('word/document.xml');
            $documentXml = preg_replace_callback(
                '/<w:body>.*?(?=<w:sectPr)/s',
                static fn (): string => '<w:body>' . $xml,
                $documentXml
            );
            $zip->addFromString('word/document.xml', $documentXml);
            $zip->close();
            $dom = new \DOMDocument();
            self::assertTrue($dom->loadXML($documentXml));
            foreach (['Customer records', 'Hosting', 'Contact details', 'Luxembourg'] as $text) {
                self::assertStringContainsString($text, $dom->textContent);
            }
        } finally {
            unlink($path);
        }
        $record = $this->env->recordTable->findById($id);
        $record->setLabel(null)->setPurposes(null)->setSecMeasures(null);
        self::assertIsString((new \ReflectionMethod($class, 'generateTableRecordGDPR'))->invoke($service, $id));
    }

    private function encrypt(string $content, string $password): string
    {
        $helper = new class {
            use \Monarc\Core\Helper\EncryptDecryptHelperTrait { encrypt as public;
            }
        };
        return $helper->encrypt($content, $password);
    }

    private function request(
        string $resource,
        string $method,
        ?int $id = null,
        ?array $data = null,
        array $query = []
    ): array {
        $controllers = [
            'records' => new Controller\ApiAnrRecordsController(
                new Formatter\GetRecordsInputFormatter(),
                $this->env->recordDataInputValidator,
                $this->env->anrRecordService
            ),
            'actors' => new Controller\ApiAnrRecordActorsController(
                new Formatter\GetRecordActorsInputFormatter(),
                $this->env->recordActorDataInputValidator,
                $this->env->anrRecordActorService
            ),
            'personalData' => new Controller\ApiAnrRecordPersonalDataController(
                new Formatter\GetRecordPersonalDataInputFormatter(),
                $this->env->recordPersonalDataDataInputValidator,
                $this->env->anrRecordPersonalDataService
            ),
            'dataCategories' => new Controller\ApiAnrRecordDataCategoriesController(
                new Formatter\GetRecordDataCategoriesInputFormatter(),
                $this->env->anrRecordDataCategoryService
            ),
            'processors' => new Controller\ApiAnrRecordProcessorsController(
                new Formatter\GetRecordProcessorsInputFormatter(),
                $this->env->recordProcessorDataInputValidator,
                $this->env->anrRecordProcessorService
            ),
            'recipients' => new Controller\ApiAnrRecordRecipientsController(
                new Formatter\GetRecordRecipientsInputFormatter(),
                $this->env->recordRecipientDataInputValidator,
                $this->env->anrRecordRecipientService
            ),
            'internationalTransfers' => new Controller\ApiAnrRecordInternationalTransfersController(
                new Formatter\GetRecordInternationalTransfersInputFormatter(),
                $this->env->recordInternationalTransferDataInputValidator,
                $this->env->anrRecordInternationalTransferService
            ),
        ];
        $body = (new StreamFactory())->createStream(json_encode($data ?? [], JSON_THROW_ON_ERROR));
        $params = $id === null ? ['anrid' => 1] : ['anrid' => 1, 'id' => $id];
        $request = (new ServerRequest([], [], '/api/client-anr/1/records', $method))->withParsedBody($data)
            ->withQueryParams($query)
            ->withHeader('Content-Type', 'application/json')->withBody($body)
            ->withAttribute(RouteMatch::class, new RouteMatch($params))->withAttribute('anr', $this->env->anr);
        $response = $controllers[$resource]->handle($request);
        return json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
