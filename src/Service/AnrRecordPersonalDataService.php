<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\InputFormatter\FormattedInputParams;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Table\RecordPersonalDataTable;
use Monarc\FrontOffice\Table\RecordTable;
use Monarc\FrontOffice\Table\RecordDataCategoryTable;
use Monarc\FrontOffice\Entity\RecordPersonalData;
use Monarc\FrontOffice\Entity\Anr;

class AnrRecordPersonalDataService
{
    private UserSuperClass $connectedUser;

    public function __construct(
        private RecordPersonalDataTable $recordPersonalDataTable,
        private RecordTable $recordTable,
        private RecordDataCategoryTable $recordDataCategoryTable,
        private AnrRecordDataCategoryService $anrRecordDataCategoryService,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getList(FormattedInputParams $params): array
    {
        return array_map(
            fn (RecordPersonalData $recordPersonalData): array => $this->serialize($recordPersonalData),
            $this->recordPersonalDataTable->findByParams($params)
        );
    }

    public function getCount(FormattedInputParams $params): int
    {
        return $this->recordPersonalDataTable->countByParams($params);
    }

    public function getRecordPersonalDataData(Anr $anr, int $id): array
    {
        return $this->serialize($this->recordPersonalDataTable->findByIdAndAnr($id, $anr));
    }

    public function serialize(RecordPersonalData $recordPersonalData): array
    {
        return [
            'id' => $recordPersonalData->getId(),
            'dataSubject' => $recordPersonalData->getDataSubject(),
            'description' => $recordPersonalData->getDescription(),
            'retentionPeriod' => $recordPersonalData->getRetentionPeriod(),
            'retentionPeriodMode' => $recordPersonalData->getRetentionPeriodMode(),
            'retentionPeriodDescription' => $recordPersonalData->getRetentionPeriodDescription(),
            'record' => $recordPersonalData->getRecord()->getId(),
            'dataCategories' => array_map(
                fn ($category): array => $this->anrRecordDataCategoryService->serialize($category),
                $recordPersonalData->getDataCategories()->toArray()
            ),
            'creator' => $recordPersonalData->getCreator(),
            'createdAt' => $recordPersonalData->getCreatedAt(),
            'updater' => $recordPersonalData->getUpdater(),
            'updatedAt' => $recordPersonalData->getUpdatedAt(),
        ];
    }

    public function create(Anr $anr, array $data, bool $flush = true): RecordPersonalData
    {
        $recordPersonalData = (new RecordPersonalData())
            ->setAnr($anr)
            ->setCreator($this->connectedUser->getEmail());
        $this->applyData($recordPersonalData, $data);
        $this->recordPersonalDataTable->save($recordPersonalData, $flush);

        return $recordPersonalData;
    }

    public function update(Anr $anr, int $id, array $data): void
    {
        $recordPersonalData = $this->recordPersonalDataTable->findByIdAndAnr($id, $anr);
        $this->applyData($recordPersonalData, $data);
        $recordPersonalData->setUpdater($this->connectedUser->getEmail());
        $this->recordPersonalDataTable->save($recordPersonalData);
    }

    private function applyData(RecordPersonalData $recordPersonalData, array $data): void
    {
        $record = array_key_exists('record', $data)
            ? $this->recordTable->findByIdAndAnr((int)$data['record'], $recordPersonalData->getAnr())
            : $recordPersonalData->getRecord();
        $categories = [];
        foreach ($data['dataCategories'] ?? [] as $category) {
            if (!empty($category['id'])) {
                $categories[] = $this->recordDataCategoryTable->findByIdAndAnr(
                    (int)$category['id'],
                    $recordPersonalData->getAnr()
                );
            }
        }
        foreach ($data['dataCategories'] ?? [] as $category) {
            if (empty($category['id'])) {
                $categories[] = $this->anrRecordDataCategoryService->findOrCreate(
                    $recordPersonalData->getAnr(),
                    $category,
                    false
                );
            }
        }
        if (array_key_exists('dataSubject', $data)) {
            $recordPersonalData->setDataSubject($data['dataSubject']);
        }
        if (array_key_exists('description', $data)) {
            $recordPersonalData->setDescription($data['description']);
        }
        if (array_key_exists('retentionPeriod', $data)) {
            $recordPersonalData->setRetentionPeriod($data['retentionPeriod']);
        }
        if (array_key_exists('retentionPeriodMode', $data)) {
            $recordPersonalData->setRetentionPeriodMode($data['retentionPeriodMode']);
        }
        if (array_key_exists('retentionPeriodDescription', $data)) {
            $recordPersonalData->setRetentionPeriodDescription($data['retentionPeriodDescription']);
        }
        $recordPersonalData->setRecord($record);
        if (!$record->getPersonalData()->contains($recordPersonalData)) {
            $record->getPersonalData()->add($recordPersonalData);
        }
        if (array_key_exists('dataCategories', $data)) {
            $recordPersonalData->setDataCategories($categories);
        }
    }
}
