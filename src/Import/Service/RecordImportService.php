<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Service;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Helper\EncryptDecryptHelperTrait;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Record;
use Monarc\FrontOffice\Import\Processor\RecordImportProcessor;
use Monarc\FrontOffice\Table\RecordTable;

class RecordImportService
{
    use EncryptDecryptHelperTrait;

    public function __construct(
        private RecordTable $recordTable,
        private RecordImportProcessor $recordImportProcessor
    ) {
    }

    public function importFromFile(Anr $anr, array $data): array
    {
        $this->recordImportProcessor->prepareCache($anr);
        [$records, $errors] = $this->processFile($anr, $data);
        $this->recordTable->flush();

        return [array_map(fn (Record $record): int => $record->getId(), $records), $errors];
    }

    private function processFile(Anr $anr, array $data): array
    {
        // We can have multiple files imported with the same password (we'll emit warnings if the password mismatches)
        if (empty($data['file'])) {
            throw new Exception('File missing', 412);
        }

        $records = $errors = [];
        if (in_array($data['isJson'] ?? true, [true, 1, '1', 'true'], true)) {
            $f = $data['file'];
            if (isset($f['error']) && $f['error'] === UPLOAD_ERR_OK && file_exists($f['tmp_name'])) {
                $file = [];
                if (empty($data['password'])) {
                    $file = json_decode(trim(file_get_contents($f['tmp_name'])), true);
                    if (!is_array($file)) { // support legacy exports which were base64 encoded
                        $file = json_decode(
                            trim((string)$this->decrypt(base64_decode(file_get_contents($f['tmp_name'])), '')),
                            true
                        );
                    }
                } else {
                    // Decrypt the file and store the JSON data as an array in memory
                    $key = $data['password'];
                    $file = json_decode(trim((string)$this->decrypt(file_get_contents($f['tmp_name']), $key)), true);
                    if (!is_array($file)) { // support legacy exports which were base64 encoded
                        $file = json_decode(
                            trim((string)$this->decrypt(base64_decode(file_get_contents($f['tmp_name'])), $key)),
                            true
                        );
                    }
                }

                if (!is_array($file)) {
                    $errors[] = 'The file "' . $f['name'] . '" can\'t be imported';
                }
                foreach (is_array($file) ? $file : [] as $key => $record) {
                    if (is_array($record) && ($importedRecord = $this->recordImportProcessor->processRecordData(
                        $anr,
                        $record
                    )) !== null) {
                        $records[] = $importedRecord;
                    } else {
                        $errors[] = 'The file "' . $f['name'] . '" can\'t be imported';
                    }
                }
            }
        } else {
            $array = $data['csv'] ?? [];
            $file = [];
            $file['type'] = 'record';
            foreach ($array as $key => $row) {
                if (!is_array($row)) {
                    continue;
                }
                if ($key != 0 && trim((string)($row["name"] ?? '')) !== '') {
                    if ($file !== false && ($importedRecord = $this->recordImportProcessor->processRecordData(
                        $anr,
                        $file
                    )) !== null) {
                        $records[] = $importedRecord;
                    } else {
                        $errors[] = 'The file "' . $row['name'] . '" can\'t be imported';
                    }
                    $file = [];
                    $file['type'] = 'record';
                }
                if (trim((string)($row['name'] ?? '')) !== '') {
                    $file['name'] = $row['name'];
                }
                if (trim((string)($row['purposes'] ?? '')) !== '') {
                    $file['purposes'] = $row['purposes'];
                }
                if (trim((string)($row['security measures'] ?? '')) !== '') {
                    $file['security_measures'] = $row['security measures'];
                }
                if (trim((string)($row['controller name'] ?? '')) !== '') {
                    $file['controller'] = [];
                    $file['controller']['name'] = $row['controller name'];
                    if (trim((string)($row['controller contact'] ?? '')) !== '') {
                        $file['controller']['contact'] = $row['controller contact'];
                    }
                }
                if (trim((string)($row['representative name'] ?? '')) !== '') {
                    $file['representative'] = [];
                    $file['representative']['name'] = $row['representative name'];
                    if (trim((string)($row['representative contact'] ?? '')) !== '') {
                        $file['representative']['contact'] = $row['representative contact'];
                    }
                }
                if (trim((string)($row['data protection officer name'] ?? '')) !== '') {
                    $file['data_protection_officer'] = [];
                    $file['data_protection_officer']['name'] = $row['data protection officer name'];
                    if (trim((string)($row['data protection officer contact'] ?? '')) !== '') {
                        $file['data_protection_officer']['contact'] = $row['data protection officer contact'];
                    }
                }
                if (trim((string)($row['joint controllers name'] ?? '')) !== '') {
                    if (!isset($file['joint_controllers'])) {
                        $file['joint_controllers'] = [];
                    }
                    $jc = [];
                    $jc['name'] = $row['joint controllers name'];
                    if (trim((string)($row['joint controllers contact'] ?? '')) !== '') {
                        $jc['contact'] = $row['joint controllers contact'];
                    }
                    $file['joint_controllers'][] = $jc;
                }
                if (trim((string)($row['retention period unit'] ?? '')) !== '') {
                    if (!isset($file['personal_data'])) {
                        $file['personal_data'] = [];
                    }
                    $pd = [];
                    if (trim((string)($row['data subject'] ?? '')) !== '') {
                        $pd['data_subject'] = $row['data subject'];
                    }
                    if (trim((string)($row['data categories'] ?? '')) !== '') {
                        foreach (explode(", ", $row['data categories']) as $dc) {
                            $dataCategory = [];
                            $dataCategory['name'] = $dc;
                            $pd['data_categories'][] = $dataCategory;
                        }
                    }
                    if (trim((string)($row['description'] ?? '')) !== '') {
                        $pd['description'] = $row['description'];
                    }
                    if (trim((string)($row['retention period'] ?? '')) != "") {
                        $pd['retention_period'] = $row['retention period'];
                    }
                    if (trim((string)($row['retention period unit'] ?? '')) !== '') {
                        $pd['retention_period_mode'] = $row['retention period unit'];
                    }
                    if (trim((string)($row['retention period description'] ?? '')) !== '') {
                        $pd['retention_period_description'] = $row['retention period description'];
                    }
                    $file['personal_data'][] = $pd;
                }
                if (trim((string)($row['data recipient'] ?? '')) !== ''
                    || trim((string)($row['data recipient type'] ?? '')) !== ''
                    || trim((string)($row['description'] ?? '')) !== ''
                ) {
                    if (!isset($file['recipients'])) {
                        $file['recipients'] = [];
                    }
                    $r = [];
                    if (trim((string)($row['data recipient'] ?? '')) !== '') {
                        $r['name'] = $row['data recipient'];
                    }
                    if (trim((string)($row['data recipient type'] ?? '')) !== '') {
                        $r['type'] = $row['data recipient type'];
                    }
                    if (trim((string)($row['description'] ?? '')) !== '') {
                        $r['description'] = $row['description'];
                    }
                    $file['recipients'][] = $r;
                }
                if (trim((string)($row['organisation of international transfer'] ?? '')) !== ''
                    || trim((string)($row['description'] ?? '')) !== ''
                    || trim((string)($row['country'] ?? '')) !== ''
                    || trim((string)($row['documents'] ?? '')) !== ''
                ) {
                    if (!isset($file['international_transfers'])) {
                        $file['international_transfers'] = [];
                    }
                    $it = [];
                    if (trim((string)($row['organisation of international transfer'] ?? '')) !== '') {
                        $it['organisation'] = $row['organisation of international transfer'];
                    }
                    if (trim((string)($row['description'] ?? '')) !== '') {
                        $it['description'] = $row['description'];
                    }
                    if (trim((string)($row['country'] ?? '')) !== '') {
                        $it['country'] = $row['country'];
                    }
                    if (trim((string)($row['documents'] ?? '')) !== '') {
                        $it['documents'] = $row['documents'];
                    }
                    $file['international_transfers'][] = $it;
                }
                if (trim((string)($row['data processor name'] ?? '')) !== '') {
                    if (!isset($file['processors'])) {
                        $file['processors'] = [];
                    }
                    $p = [];
                    $p['name'] = $row['data processor name'];
                    if (trim((string)($row['data processor contact'] ?? '')) !== '') {
                        $p['contact'] = $row['data processor contact'];
                    }
                    if (trim((string)($row['activities'] ?? '')) !== '') {
                        $p['activities'] = $row['activities'];
                    }
                    if (trim((string)($row['data processor security measures'] ?? '')) !== '') {
                        $p['security_measures'] = $row['data processor security measures'];
                    }
                    if (trim((string)($row['data processor representative name'] ?? '')) !== '') {
                        $rep = [];
                        $rep['name'] = $row['data processor representative name'];
                        if (trim((string)($row['data processor representative contact'] ?? '')) !== '') {
                            $rep['contact'] = $row['data processor representative contact'];
                        }
                        $p['representative'] = $rep;
                    }
                    if (trim((string)($row['data processor data protection officer name'] ?? '')) !== '') {
                        $dpo = [];
                        $dpo['name'] = $row['data processor data protection officer name'];
                        if (trim((string)($row['data processor data protection officer contact'] ?? '')) !== '') {
                            $dpo['contact'] = $row['data processor data protection officer contact'];
                        }
                        $p['data_protection_officer'] = $dpo;
                    }
                    $file['processors'][] = $p;
                }
            }
            if (isset($file['name']) && ($importedRecord = $this->recordImportProcessor->processRecordData(
                $anr,
                $file
            )) !== null) {
                $records[] = $importedRecord;
            } else {
                $errors[] = 'The file "' . ($file['name'] ?? '') . '" can\'t be imported';
            }
        }

        return [$records, $errors];
    }
}
