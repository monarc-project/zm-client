<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Processor;

use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Record;
use Monarc\FrontOffice\Entity\RecordActor;
use Monarc\FrontOffice\Entity\RecordRecipient;
use Monarc\FrontOffice\Entity\RecordProcessor;
use Monarc\FrontOffice\Entity\RecordDataCategory;
use Monarc\FrontOffice\Entity\RecordPersonalData;
use Monarc\FrontOffice\Entity\RecordInternationalTransfer;
use Monarc\FrontOffice\Table\RecordTable;
use Monarc\FrontOffice\Table\RecordActorTable;
use Monarc\FrontOffice\Table\RecordRecipientTable;
use Monarc\FrontOffice\Table\RecordProcessorTable;
use Monarc\FrontOffice\Table\RecordDataCategoryTable;
use Monarc\FrontOffice\Table\RecordPersonalDataTable;
use Monarc\FrontOffice\Table\RecordInternationalTransferTable;

class RecordImportProcessor
{
    private UserSuperClass $connectedUser;

    public function __construct(
        private RecordTable $recordTable,
        private RecordActorTable $recordActorTable,
        private RecordRecipientTable $recordRecipientTable,
        private RecordProcessorTable $recordProcessorTable,
        private RecordDataCategoryTable $recordDataCategoryTable,
        private RecordPersonalDataTable $recordPersonalDataTable,
        private RecordInternationalTransferTable $recordInternationalTransferTable,
        private ImportCacheHelper $importCacheHelper,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function prepareCache(Anr $anr): void
    {
        $this->importCacheHelper->setArrayCacheValue('record_import_scope', $anr);
        foreach ([
            'record_actors' => $this->recordActorTable,
            'record_recipients' => $this->recordRecipientTable,
            'record_processors' => $this->recordProcessorTable,
            'record_data_categories' => $this->recordDataCategoryTable,
        ] as $cacheKey => $table) {
            $this->importCacheHelper->setArrayCacheValue($cacheKey, []);
            foreach ($table->findByAnr($anr) as $recordReference) {
                $key = match (true) {
                    $recordReference instanceof RecordActor => $this->actorKey($recordReference),
                    $recordReference instanceof RecordProcessor => $this->processorKey($recordReference),
                    $recordReference instanceof RecordRecipient => $this->recipientKey(
                        $recordReference->getLabel(),
                        $recordReference->getType(),
                        $recordReference->getDescription()
                    ),
                    default => json_encode($recordReference->getLabel(), JSON_THROW_ON_ERROR),
                };
                $this->importCacheHelper->addItemToArrayCache($cacheKey, $recordReference, $key);
            }
        }
    }

    public function processRecordsData(Anr $anr, array $recordsData): array
    {
        if ($recordsData === []) {
            return [];
        }
        $this->prepareCache($anr);
        $records = [];
        foreach ($recordsData as $recordData) {
            if (is_array($recordData)) {
                $record = $this->processRecordData($anr, $recordData);
                if ($record !== null) {
                    $records[] = $record;
                }
            }
        }

        return $records;
    }

    public function processRecordData(Anr $anr, array $source): ?Record
    {
        $data = $this->prepare($source);
        if ($data === null) {
            return null;
        }
        if ($this->importCacheHelper->getValueFromArrayCache('record_import_scope') !== $anr) {
            $this->prepareCache($anr);
        }
        $record = (new Record())
            ->setAnr($anr)
            ->setLabel($data['record']['label'])
            ->setPurposes($data['record']['purposes'])
            ->setSecMeasures($data['record']['secMeasures'])
            ->setCreator($this->connectedUser->getEmail());
        foreach ([
            'controller' => 'setController',
            'representative' => 'setRepresentative',
            'data_protection_officer' => 'setDpo',
        ] as $sourceField => $setter) {
            if (isset($data['actors'][$sourceField])) {
                $record->$setter($this->processActorData($anr, $data['actors'][$sourceField]));
            }
        }
        foreach ($data['joint_controllers'] as $actorData) {
            $record->addJointController($this->processActorData($anr, $actorData));
        }
        foreach ($data['recipients'] as $recipientData) {
            $record->addRecipientCategory($this->processRecipientData($anr, $recipientData));
        }
        foreach ($data['processors'] as $processorData) {
            $record->addProcessor($this->processProcessorData($anr, $processorData));
        }
        $this->recordTable->save($record, false);
        foreach ($data['personal_data'] as $personalData) {
            $categories = [];
            foreach ($personalData['dataCategories'] as $categoryData) {
                $key = json_encode($categoryData['label'], JSON_THROW_ON_ERROR);
                $category = $this->importCacheHelper->getItemFromArrayCache('record_data_categories', $key);
                if ($category === null) {
                    $category = (new RecordDataCategory())
                        ->setAnr($anr)
                        ->setLabel($categoryData['label'])
                        ->setCreator($this->connectedUser->getEmail());
                    $this->recordDataCategoryTable->save($category, false);
                    $this->importCacheHelper->addItemToArrayCache('record_data_categories', $category, $key);
                }
                $categories[] = $category;
            }
            $recordPersonalData = (new RecordPersonalData())
                ->setAnr($anr)
                ->setRecord($record)
                ->setDataSubject($personalData['dataSubject'])
                ->setDescription($personalData['description'])
                ->setRetentionPeriod($personalData['retentionPeriod'])
                ->setRetentionPeriodMode($personalData['retentionPeriodMode'])
                ->setRetentionPeriodDescription($personalData['retentionPeriodDescription'])
                ->setDataCategories($categories)
                ->setCreator($this->connectedUser->getEmail());
            $record->getPersonalData()->add($recordPersonalData);
            $this->recordPersonalDataTable->save($recordPersonalData, false);
        }
        foreach ($data['international_transfers'] as $transferData) {
            $recordInternationalTransfer = (new RecordInternationalTransfer())
                ->setAnr($anr)
                ->setRecord($record)
                ->setOrganisation($transferData['organisation'] ?? null)
                ->setDescription($transferData['description'] ?? null)
                ->setCountry($transferData['country'] ?? null)
                ->setDocuments($transferData['documents'] ?? null)
                ->setCreator($this->connectedUser->getEmail());
            $record->getInternationalTransfers()->add($recordInternationalTransfer);
            $this->recordInternationalTransferTable->save($recordInternationalTransfer, false);
        }

        return $record;
    }

    private function processActorData(Anr $anr, array $actorData): RecordActor
    {
        $actor = (new RecordActor())
            ->setAnr($anr)
            ->setLabel($actorData['label'])
            ->setContact($actorData['contact'])
            ->setCreator($this->connectedUser->getEmail());
        $key = $this->actorKey($actor);
        $storedActor = $this->importCacheHelper->getItemFromArrayCache('record_actors', $key);
        if ($storedActor !== null) {
            return $storedActor;
        }
        $this->recordActorTable->save($actor, false);
        $this->importCacheHelper->addItemToArrayCache('record_actors', $actor, $key);

        return $actor;
    }

    private function actorKey(RecordActor $actor): string
    {
        return json_encode([$actor->getLabel(), $actor->getContact()], JSON_THROW_ON_ERROR);
    }

    private function processRecipientData(Anr $anr, array $recipientData): RecordRecipient
    {
        $type = (string)$recipientData['type'];
        $key = $this->recipientKey($recipientData['label'], $type, $recipientData['description']);
        $recipient = $this->importCacheHelper->getItemFromArrayCache('record_recipients', $key);
        if ($recipient === null) {
            $recipient = (new RecordRecipient())
                ->setAnr($anr)
                ->setLabel($recipientData['label'])
                ->setType($type)
                ->setDescription($recipientData['description'])
                ->setCreator($this->connectedUser->getEmail());
            $this->recordRecipientTable->save($recipient, false);
            $this->importCacheHelper->addItemToArrayCache('record_recipients', $recipient, $key);
        }

        return $recipient;
    }

    private function recipientKey(?string $label, ?string $type, ?string $description): string
    {
        return json_encode([$label, $type, $description], JSON_THROW_ON_ERROR);
    }

    private function processProcessorData(Anr $anr, array $processorData): RecordProcessor
    {
        $processor = (new RecordProcessor())
            ->setAnr($anr)
            ->setLabel($processorData['label'])
            ->setContact($processorData['contact'])
            ->setActivities($processorData['activities'])
            ->setSecMeasures($processorData['secMeasures'])
            ->setCreator($this->connectedUser->getEmail());
        foreach (['representative' => 'setRepresentative', 'dpo' => 'setDpo'] as $role => $setter) {
            if (isset($processorData['actors'][$role])) {
                $processor->$setter($this->processActorData($anr, $processorData['actors'][$role]));
            }
        }
        $key = $this->processorKey($processor);
        $storedProcessor = $this->importCacheHelper->getItemFromArrayCache('record_processors', $key);
        if ($storedProcessor !== null) {
            return $storedProcessor;
        }
        $this->recordProcessorTable->save($processor, false);
        $this->importCacheHelper->addItemToArrayCache('record_processors', $processor, $key);

        return $processor;
    }

    private function processorKey(RecordProcessor $processor): string
    {
        return json_encode([
            $processor->getLabel(),
            $processor->getContact(),
            $processor->getActivities(),
            $processor->getSecMeasures(),
            $processor->getRepresentative() === null ? null : $this->actorKey($processor->getRepresentative()),
            $processor->getDpo() === null ? null : $this->actorKey($processor->getDpo()),
        ], JSON_THROW_ON_ERROR);
    }

    private function prepare(array $source): ?array
    {
        if (!array_key_exists('name', $source)) {
            return null;
        }
        $data = [
            'record' => [
                'label' => $source['name'],
                'purposes' => $source['purposes'] ?? null,
                'secMeasures' => $source['security_measures'] ?? null
            ],
            'actors' => [],
            'joint_controllers' => [],
            'recipients' => [],
            'processors' => [],
            'personal_data' => [],
            'international_transfers' => [],
        ];
        foreach (['controller', 'representative', 'data_protection_officer'] as $field) {
            if (isset($source[$field]) && is_array($source[$field])) {
                $data['actors'][$field] = [
                    'label' => $source[$field]['name'] ?? null,
                    'contact' => $source[$field]['contact'] ?? null,
                ];
            }
        }
        foreach (['joint_controllers', 'recipients', 'processors', 'personal_data',
            'international_transfers'] as $field) {
            foreach (is_array($source[$field] ?? null) ? $source[$field] : [] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                if ($field === 'joint_controllers') {
                    $row = ['label' => $row['name'] ?? null, 'contact' => $row['contact'] ?? null];
                } elseif ($field === 'recipients') {
                    $row = [
                        'label' => $row['name'] ?? null,
                        'type' => ($row['type'] ?? '') === 'internal' ? 0 : 1,
                        'description' => $row['description'] ?? null
                    ];
                } elseif ($field === 'processors') {
                    $actors = [];
                    foreach ([
                        'representative' => 'representative',
                        'data_protection_officer' => 'dpo'
                    ] as $key => $role) {
                        if (isset($row[$key]) && is_array($row[$key])) {
                            $actors[$role] = [
                                'label' => $row[$key]['name'] ?? null,
                                'contact' => $row[$key]['contact'] ?? null
                            ];
                        }
                    }
                    $row = [
                        'label' => $row['name'] ?? null,
                        'contact' => $row['contact'] ?? null,
                        'activities' => $row['activities'] ?? null,
                        'secMeasures' => $row['security_measures'] ?? null,
                        'actors' => $actors
                    ];
                } elseif ($field === 'personal_data') {
                    $categories = [];
                    foreach (is_array($row['data_categories'] ?? null) ? $row['data_categories'] : [] as $category) {
                        if (is_array($category)) {
                            $categories[] = ['label' => $category['name'] ?? null];
                        }
                    }
                    $modes = ['day(s)' => 0, 'month(s)' => 1, 'year(s)' => 2];
                    $mode = $row['retention_period_mode'] ?? 'day(s)';
                    $row = [
                        'dataSubject' => $row['data_subject'] ?? null,
                        'description' => $row['description'] ?? null,
                        'retentionPeriod' => (int)($row['retention_period'] ?? 0),
                        'retentionPeriodMode' => (int)($modes[$mode] ?? $mode),
                        'retentionPeriodDescription' => $row['retention_period_description'] ?? null,
                        'dataCategories' => $categories
                    ];
                }
                $data[$field][] = $row;
            }
        }

        return $data;
    }
}
