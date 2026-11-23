<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFrontOfficeTests\Fixture;

use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Validator\InputValidator\InputValidationTranslator;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service;
use Monarc\FrontOffice\Table;
use Monarc\FrontOffice\Validator\InputValidator\Record as Validator;

class RopaTestEnvironment
{
    public EntityManager $manager;
    public Anr $anr;
    public Anr $other;
    public Table\RecordTable $recordTable;
    public Service\AnrRecordService $anrRecordService;
    public Validator\RecordDataInputValidator $recordDataInputValidator;
    public Table\RecordActorTable $recordActorTable;
    public Service\AnrRecordActorService $anrRecordActorService;
    public Validator\RecordActorDataInputValidator $recordActorDataInputValidator;
    public Table\RecordDataCategoryTable $recordDataCategoryTable;
    public Service\AnrRecordDataCategoryService $anrRecordDataCategoryService;
    public Validator\RecordDataCategoryDataInputValidator $recordDataCategoryDataInputValidator;
    public Table\RecordInternationalTransferTable $recordInternationalTransferTable;
    public Service\AnrRecordInternationalTransferService $anrRecordInternationalTransferService;
    public Validator\RecordInternationalTransferDataInputValidator $recordInternationalTransferDataInputValidator;
    public Table\RecordPersonalDataTable $recordPersonalDataTable;
    public Service\AnrRecordPersonalDataService $anrRecordPersonalDataService;
    public Validator\RecordPersonalDataDataInputValidator $recordPersonalDataDataInputValidator;
    public Table\RecordProcessorTable $recordProcessorTable;
    public Service\AnrRecordProcessorService $anrRecordProcessorService;
    public Validator\RecordProcessorDataInputValidator $recordProcessorDataInputValidator;
    public Table\RecordRecipientTable $recordRecipientTable;
    public Service\AnrRecordRecipientService $anrRecordRecipientService;
    public Validator\RecordRecipientDataInputValidator $recordRecipientDataInputValidator;
    public \Monarc\FrontOffice\Import\Service\RecordImportService $recordImportService;
    public \Monarc\FrontOffice\Import\Processor\RecordImportProcessor $recordImportProcessor;
    public \Monarc\FrontOffice\Export\Service\RecordExportService $recordExportService;

    public function __construct(ConnectedUserService $identity)
    {
        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([__DIR__ . '/../../src/Entity']));
        $config->setProxyDir(sys_get_temp_dir());
        $config->setProxyNamespace('Php84RopaTestProxies');
        $config->setAutoGenerateProxyClasses(true);
        $this->manager = EntityManager::create(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $metadata = [];
        $metadata[] = $this->manager->getClassMetadata(\Monarc\FrontOffice\Entity\Record::class);
        $metadata[] = $this->manager->getClassMetadata(\Monarc\FrontOffice\Entity\RecordActor::class);
        $metadata[] = $this->manager->getClassMetadata(\Monarc\FrontOffice\Entity\RecordDataCategory::class);
        $metadata[] = $this->manager->getClassMetadata(\Monarc\FrontOffice\Entity\RecordInternationalTransfer::class);
        $metadata[] = $this->manager->getClassMetadata(\Monarc\FrontOffice\Entity\RecordPersonalData::class);
        $metadata[] = $this->manager->getClassMetadata(\Monarc\FrontOffice\Entity\RecordProcessor::class);
        $metadata[] = $this->manager->getClassMetadata(\Monarc\FrontOffice\Entity\RecordRecipient::class);
        foreach ((new SchemaTool($this->manager))->getCreateSchemaSql($metadata) as $sql) {
            $sql = preg_replace('/^(CREATE INDEX) (\w+) ON (\w+)/', '$1 $3_$2 ON $3', $sql);
            $this->manager->getConnection()->executeStatement($sql);
        }
        $this->recordTable = new Table\RecordTable($this->manager);
        $this->recordActorTable = new Table\RecordActorTable($this->manager);
        $this->recordDataCategoryTable = new Table\RecordDataCategoryTable($this->manager);
        $this->recordInternationalTransferTable = new Table\RecordInternationalTransferTable($this->manager);
        $this->recordPersonalDataTable = new Table\RecordPersonalDataTable($this->manager);
        $this->recordProcessorTable = new Table\RecordProcessorTable($this->manager);
        $this->recordRecipientTable = new Table\RecordRecipientTable($this->manager);
        $this->anrRecordActorService = new Service\AnrRecordActorService(
            $this->recordActorTable,
            $identity
        );
        $this->anrRecordDataCategoryService = new Service\AnrRecordDataCategoryService(
            $this->recordDataCategoryTable,
            $identity
        );
        $this->anrRecordProcessorService = new Service\AnrRecordProcessorService(
            $this->recordProcessorTable,
            $this->recordActorTable,
            $this->anrRecordActorService,
            $identity
        );
        $this->anrRecordRecipientService = new Service\AnrRecordRecipientService(
            $this->recordRecipientTable,
            $identity
        );
        $this->anrRecordPersonalDataService = new Service\AnrRecordPersonalDataService(
            $this->recordPersonalDataTable,
            $this->recordTable,
            $this->recordDataCategoryTable,
            $this->anrRecordDataCategoryService,
            $identity
        );
        $this->anrRecordInternationalTransferService = new Service\AnrRecordInternationalTransferService(
            $this->recordInternationalTransferTable,
            $this->recordTable,
            $identity
        );
        $this->anrRecordService = new Service\AnrRecordService(
            $this->recordTable,
            $this->recordActorTable,
            $this->recordDataCategoryTable,
            $this->recordInternationalTransferTable,
            $this->recordPersonalDataTable,
            $this->recordProcessorTable,
            $this->recordRecipientTable,
            $this->anrRecordActorService,
            $this->anrRecordInternationalTransferService,
            $this->anrRecordPersonalDataService,
            $this->anrRecordProcessorService,
            $this->anrRecordRecipientService,
            $identity
        );
        $translator = new InputValidationTranslator($identity, []);
        $this->recordDataInputValidator = new Validator\RecordDataInputValidator([], $translator);
        $this->recordActorDataInputValidator = new Validator\RecordActorDataInputValidator([], $translator);
        $this->recordDataCategoryDataInputValidator = new Validator\RecordDataCategoryDataInputValidator(
            [],
            $translator
        );
        $this->recordInternationalTransferDataInputValidator =
            new Validator\RecordInternationalTransferDataInputValidator(
                [],
                $translator
            );
        $this->recordProcessorDataInputValidator = new Validator\RecordProcessorDataInputValidator([], $translator);
        $this->recordRecipientDataInputValidator = new Validator\RecordRecipientDataInputValidator([], $translator);
        $this->recordPersonalDataDataInputValidator = new Validator\RecordPersonalDataDataInputValidator(
            [],
            $translator,
            $this->recordDataCategoryDataInputValidator
        );
        $this->recordImportProcessor = new \Monarc\FrontOffice\Import\Processor\RecordImportProcessor(
            $this->recordTable,
            $this->recordActorTable,
            $this->recordRecipientTable,
            $this->recordProcessorTable,
            $this->recordDataCategoryTable,
            $this->recordPersonalDataTable,
            $this->recordInternationalTransferTable,
            new \Monarc\FrontOffice\Import\Helper\ImportCacheHelper(),
            $identity
        );
        $this->recordImportService = new \Monarc\FrontOffice\Import\Service\RecordImportService(
            $this->recordTable,
            $this->recordImportProcessor
        );
        $this->recordExportService = new \Monarc\FrontOffice\Export\Service\RecordExportService($this->recordTable);
        $this->anr = $this->analysis(1);
        $this->other = $this->analysis(2);
    }

    public function importRecord(Anr $anr, array $source): ?int
    {
        $records = $this->recordImportProcessor->processRecordsData($anr, [$source]);
        $this->recordTable->flush();

        return isset($records[0]) ? $records[0]->getId() : null;
    }

    public function exportRecord(\Monarc\FrontOffice\Entity\Record $record): array
    {
        [, $content] = $this->recordExportService->export($record->getAnr(), $record->getId());

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR)[0];
    }

    public function analysis(int $id): Anr
    {
        $anr = new Anr();
        (new \ReflectionProperty($anr, 'id'))->setValue($anr, $id);
        $anr->setLanguage(1);
        $this->manager->getUnitOfWork()->registerManaged($anr, ['id' => $id], []);

        return $anr;
    }

    public static function recordData(): array
    {
        return [
            'name' => 'Customer records',
            'purposes' => 'Provide service',
            'security_measures' => 'Encryption',
            'controller' => ['name' => 'Controller', 'contact' => 'controller@example.invalid'],
            'representative' => ['name' => 'Representative'],
            'data_protection_officer' => ['name' => 'DPO'],
            'joint_controllers' => [['name' => 'Joint controller']],
            'recipients' => [['name' => 'Recipient', 'type' => 'internal', 'description' => 'Staff']],
            'processors' => [[
                'name' => 'Processor',
                'contact' => 'processor@example.invalid',
                'activities' => 'Hosting',
                'security_measures' => 'Backups',
                'representative' => ['name' => 'Processor representative'],
                'data_protection_officer' => ['name' => 'Processor DPO']
            ]],
            'personal_data' => [[
                'data_subject' => 'Customers',
                'data_categories' => [['name' => 'Contact details']],
                'description' => 'Email',
                'retention_period' => 12,
                'retention_period_mode' => 'month(s)',
                'retention_period_description' => 'After contract'
            ]],
            'international_transfers' => [[
                'organisation' => 'Provider',
                'description' => 'Service transfer',
                'country' => 'Luxembourg',
                'documents' => 'Contract'
            ]]
        ];
    }
}
