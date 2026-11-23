<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Service;

use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Record;
use Monarc\FrontOffice\Import\Processor\RecordImportProcessor;
use Monarc\FrontOffice\Import\Traits\ImportFileContentTrait;
use Monarc\FrontOffice\Table\RecordTable;

class RecordImportService
{
    use ImportFileContentTrait;

    public function __construct(
        private RecordTable $recordTable,
        private RecordImportProcessor $recordImportProcessor
    ) {
    }

    public function importFromFile(Anr $anr, array $importParams): array
    {
        if (!in_array($importParams['isJson'] ?? true, [true, 1, '1', 'true'], true)) {
            return $this->importFromArray($anr, $this->getRecordsDataFromCsv($importParams['csv'] ?? []));
        }
        if (empty($importParams['file'])) {
            throw new Exception('File missing', 412);
        }

        $file = $importParams['file'];
        if (isset($file['error']) && $file['error'] === UPLOAD_ERR_OK && file_exists($file['tmp_name'])) {
            $data = $this->getRecordsDataFromJsonFile($file['tmp_name'], $importParams['password'] ?? null);
            if ($data !== false) {
                return $this->importFromArray($anr, $data, $file['name']);
            }

            return [[], ['The file "' . $file['name'] . '" can\'t be imported']];
        }

        return [[], []];
    }

    private function importFromArray(Anr $anr, array $recordsData, ?string $fileName = null): array
    {
        $this->recordImportProcessor->prepareCache($anr);
        $records = $importErrors = [];
        foreach ($recordsData as $recordData) {
            $record = is_array($recordData)
                ? $this->recordImportProcessor->processRecordData($anr, $recordData)
                : null;
            if ($record !== null) {
                $records[] = $record;
            } else {
                $name = $fileName ?? ($recordData['name'] ?? '');
                $importErrors[] = 'The file "' . $name . '" can\'t be imported';
            }
        }
        $this->recordTable->flush();

        return [array_map(fn (Record $record): int => $record->getId(), $records), $importErrors];
    }

    private function getRecordsDataFromJsonFile(string $fileName, ?string $password): array|false
    {
        try {
            $data = $this->getArrayDataOfJsonFileContent($fileName, $password, allowLegacyEncoding: true);
        } catch (\JsonException | Exception) {
            return false;
        }

        return is_array($data) ? $data : false;
    }

    private function getRecordsDataFromCsv(array $rows): array
    {
        $recordsData = [];
        $recordData = ['type' => 'record'];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $fields = $this->getCsvFields($row, [
                'name' => 'name',
                'purposes' => 'purposes',
                'security measures' => 'security_measures',
            ]);
            if (isset($fields['name']) && $recordData !== ['type' => 'record']) {
                $recordsData[] = $recordData;
                $recordData = ['type' => 'record'];
            }
            $recordData = array_replace($recordData, $fields);
            foreach (['controller', 'representative', 'data protection officer'] as $role) {
                $actorData = $this->getActorDataFromCsv($row, $role);
                if ($actorData !== []) {
                    $recordData[str_replace(' ', '_', $role)] = $actorData;
                }
            }
            $jointControllerData = $this->getActorDataFromCsv($row, 'joint controllers');
            if ($jointControllerData !== []) {
                $recordData['joint_controllers'][] = $jointControllerData;
            }
            $personalData = $this->getCsvFields($row, [
                'data subject' => 'data_subject',
                'description' => 'description',
                'retention period' => 'retention_period',
                'retention period unit' => 'retention_period_mode',
                'retention period description' => 'retention_period_description',
            ]);
            if (isset($personalData['retention_period_mode'])) {
                if (trim((string)($row['data categories'] ?? '')) !== '') {
                    $personalData['data_categories'] = array_map(
                        fn (string $name): array => ['name' => $name],
                        explode(', ', $row['data categories'])
                    );
                }
                $recordData['personal_data'][] = $personalData;
            }
            $recipientData = $this->getCsvFields($row, [
                'data recipient' => 'name',
                'data recipient type' => 'type',
                'description' => 'description',
            ]);
            if ($recipientData !== []) {
                $recordData['recipients'][] = $recipientData;
            }
            $transferData = $this->getCsvFields($row, [
                'organisation of international transfer' => 'organisation',
                'description' => 'description',
                'country' => 'country',
                'documents' => 'documents',
            ]);
            if ($transferData !== []) {
                $recordData['international_transfers'][] = $transferData;
            }
            $processorData = $this->getActorDataFromCsv($row, 'data processor');
            if ($processorData !== []) {
                $processorData = array_replace($processorData, $this->getCsvFields($row, [
                    'activities' => 'activities',
                    'data processor security measures' => 'security_measures',
                ]));
                foreach (['representative', 'data protection officer'] as $role) {
                    $actorData = $this->getActorDataFromCsv($row, 'data processor ' . $role);
                    if ($actorData !== []) {
                        $processorData[str_replace(' ', '_', $role)] = $actorData;
                    }
                }
                $recordData['processors'][] = $processorData;
            }
        }
        $recordsData[] = $recordData;

        return $recordsData;
    }

    private function getActorDataFromCsv(array $row, string $prefix): array
    {
        $actorData = $this->getCsvFields($row, [$prefix . ' name' => 'name', $prefix . ' contact' => 'contact']);

        return isset($actorData['name']) ? $actorData : [];
    }

    private function getCsvFields(array $row, array $fieldMapping): array
    {
        $data = [];
        foreach ($fieldMapping as $column => $field) {
            if (trim((string)($row[$column] ?? '')) !== '') {
                $data[$field] = $row[$column];
            }
        }

        return $data;
    }
}
