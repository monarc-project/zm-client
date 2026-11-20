<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Service;

use Monarc\Core\Helper\EncryptDecryptHelperTrait;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Record;
use Monarc\FrontOffice\Export\Service\Traits\RecordExportTrait;
use Monarc\FrontOffice\Table\RecordTable;

class RecordExportService
{
    use EncryptDecryptHelperTrait;
    use RecordExportTrait;

    public function __construct(private RecordTable $recordTable)
    {
    }

    public function export(Anr $anr, ?int $id, string $password = ''): array
    {
        $records = $id === null ? $this->recordTable->findByAnr($anr)
            : [$this->recordTable->findByIdAndAnr($id, $anr)];
        $recordsData = array_map(fn (Record $record): array => $this->prepareRecordData($record), $records);
        $filename = $id === null ? 'records_list'
            : preg_replace('/[^a-z0-9._-]+/i', '', (string)$records[0]->getLabel());
        $json = json_encode($recordsData, JSON_THROW_ON_ERROR);

        return [$filename, $password === '' ? $json : $this->encrypt($json, $password)];
    }
}
