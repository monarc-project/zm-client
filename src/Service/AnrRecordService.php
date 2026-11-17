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
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Record;
use Monarc\FrontOffice\Entity\RecordActor;
use Monarc\FrontOffice\Entity\RecordDataCategory;
use Monarc\FrontOffice\Entity\RecordProcessor;
use Monarc\FrontOffice\Entity\RecordRecipient;
use Monarc\FrontOffice\Entity\RecordPersonalData;
use Monarc\FrontOffice\Entity\RecordInternationalTransfer;
use Monarc\FrontOffice\Table\RecordTable;
use Monarc\FrontOffice\Table\RecordActorTable;
use Monarc\FrontOffice\Table\RecordDataCategoryTable;
use Monarc\FrontOffice\Table\RecordInternationalTransferTable;
use Monarc\FrontOffice\Table\RecordPersonalDataTable;
use Monarc\FrontOffice\Table\RecordProcessorTable;
use Monarc\FrontOffice\Table\RecordRecipientTable;

class AnrRecordService
{
    private UserSuperClass $connectedUser;

    public function __construct(
        private RecordTable $recordTable,
        private RecordActorTable $recordActorTable,
        private RecordDataCategoryTable $recordDataCategoryTable,
        private RecordInternationalTransferTable $recordInternationalTransferTable,
        private RecordPersonalDataTable $recordPersonalDataTable,
        private RecordProcessorTable $recordProcessorTable,
        private RecordRecipientTable $recordRecipientTable,
        private AnrRecordActorService $anrRecordActorService,
        private AnrRecordInternationalTransferService $anrRecordInternationalTransferService,
        private AnrRecordPersonalDataService $anrRecordPersonalDataService,
        private AnrRecordProcessorService $anrRecordProcessorService,
        private AnrRecordRecipientService $anrRecordRecipientService,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getList(FormattedInputParams $params): array
    {
        return array_map(
            fn (Record $record): array => $this->serialize($record),
            $this->recordTable->findByParams($params)
        );
    }

    public function getCount(FormattedInputParams $params): int
    {
        return $this->recordTable->countByParams($params);
    }

    public function getRecordData(Anr $anr, int $id): array
    {
        return $this->serialize($this->recordTable->findByIdAndAnr($id, $anr));
    }

    public function serialize(Record $record): array
    {
        return [
            'id' => $record->getId(),
            'label' => $record->getLabel(),
            'purposes' => $record->getPurposes(),
            'secMeasures' => $record->getSecMeasures(),
            'controller' => $record->getController() === null ? null
                : $this->anrRecordActorService->serialize($record->getController()),
            'representative' => $record->getRepresentative() === null ? null
                : $this->anrRecordActorService->serialize($record->getRepresentative()),
            'dpo' => $record->getDpo() === null ? null
                : $this->anrRecordActorService->serialize($record->getDpo()),
            'jointControllers' => array_map(
                fn ($recordActor): array => $this->anrRecordActorService->serialize($recordActor),
                $record->getJointControllers()->toArray()
            ),
            'personalData' => array_map(
                fn ($recordPersonalData): array => $this->anrRecordPersonalDataService->serialize($recordPersonalData),
                $record->getPersonalData()->toArray()
            ),
            'processors' => array_map(
                fn ($recordProcessor): array => $this->anrRecordProcessorService->serialize($recordProcessor),
                $record->getProcessors()->toArray()
            ),
            'recipients' => array_map(
                fn ($recordRecipient): array => $this->anrRecordRecipientService->serialize($recordRecipient),
                $record->getRecipients()->toArray()
            ),
            'internationalTransfers' => array_map(
                fn ($recordInternationalTransfer): array =>
                    $this->anrRecordInternationalTransferService->serialize($recordInternationalTransfer),
                $record->getInternationalTransfers()->toArray()
            ),
            'creator' => $record->getCreator(),
            'createdAt' => $record->getCreatedAt(),
            'updater' => $record->getUpdater(),
            'updatedAt' => $record->getUpdatedAt(),
        ];
    }

    public function create(Anr $anr, array $data): Record
    {
        $record = (new Record())
            ->setAnr($anr)
            ->setCreator($this->connectedUser->getEmail());
        $this->applyData($record, $data);
        $this->recordTable->save($record);

        return $record;
    }

    public function update(Anr $anr, int $id, array $data): Record
    {
        $record = $this->recordTable->findByIdAndAnr($id, $anr);
        $this->applyData($record, $data);
        $record->setUpdater($this->connectedUser->getEmail());
        $this->recordTable->save($record);

        return $record;
    }

    private function applyData(Record $record, array $data): void
    {
        $actors = [];
        foreach (['controller', 'representative', 'dpo'] as $field) {
            if (array_key_exists($field, $data)) {
                $actors[$field] = empty($data[$field]) ? null
                    : $this->recordActorTable->findByIdAndAnr((int)$data[$field], $record->getAnr());
            }
        }
        $jointControllers = [];
        foreach ($data['jointControllers'] ?? [] as $id) {
            $jointControllers[] = $this->recordActorTable->findByIdAndAnr((int)$id, $record->getAnr());
        }
        $processors = [];
        foreach ($data['processors'] ?? [] as $id) {
            $processors[] = $this->recordProcessorTable->findByIdAndAnr((int)$id, $record->getAnr());
        }
        $recipients = [];
        foreach ($data['recipients'] ?? [] as $id) {
            $recipients[] = $this->recordRecipientTable->findByIdAndAnr((int)$id, $record->getAnr());
        }
        $personalData = [];
        foreach ($data['personalData'] ?? [] as $id) {
            $personalData[] = $this->recordPersonalDataTable->findByIdAndAnr((int)$id, $record->getAnr());
        }
        $internationalTransfers = [];
        foreach ($data['internationalTransfers'] ?? [] as $id) {
            $internationalTransfers[] = $this->recordInternationalTransferTable->findByIdAndAnr(
                (int)$id,
                $record->getAnr()
            );
        }
        if (array_key_exists('label', $data)) {
            $record->setLabel($data['label']);
        }
        if (array_key_exists('purposes', $data)) {
            $record->setPurposes($data['purposes']);
        }
        if (array_key_exists('secMeasures', $data)) {
            $record->setSecMeasures($data['secMeasures']);
        }
        if (array_key_exists('controller', $actors)) {
            $record->setController($actors['controller']);
        }
        if (array_key_exists('representative', $actors)) {
            $record->setRepresentative($actors['representative']);
        }
        if (array_key_exists('dpo', $actors)) {
            $record->setDpo($actors['dpo']);
        }
        if (array_key_exists('jointControllers', $data)) {
            $record->setJointControllers($jointControllers);
        }
        if (array_key_exists('processors', $data)) {
            $record->setProcessors($processors);
        }
        if (array_key_exists('recipients', $data)) {
            $record->setRecipients($recipients);
        }
        if (array_key_exists('personalData', $data)) {
            $record->setPersonalData($personalData);
        }
        if (array_key_exists('internationalTransfers', $data)) {
            $record->setInternationalTransfers($internationalTransfers);
        }
    }

    public function delete(Anr $anr, int $id): void
    {
        $record = $this->recordTable->findByIdAndAnr($id, $anr);
        $this->recordTable->remove($record);
    }

    public function duplicate(Anr $anr, int $id, ?string $label): int
    {
        $source = $this->recordTable->findByIdAndAnr($id, $anr);
        $actors = $categories = $processors = $recipients = [];
        foreach ($this->recordActorTable->findByAnr($anr) as $recordActor) {
            $actors[$recordActor->getId()] = $recordActor;
        }
        foreach ($this->recordDataCategoryTable->findByAnr($anr) as $recordDataCategory) {
            $categories[$recordDataCategory->getId()] = $recordDataCategory;
        }
        foreach ($this->recordProcessorTable->findByAnr($anr) as $recordProcessor) {
            $processors[$recordProcessor->getId()] = $recordProcessor;
        }
        foreach ($this->recordRecipientTable->findByAnr($anr) as $recordRecipient) {
            $recipients[$recordRecipient->getId()] = $recordRecipient;
        }
        $copy = $this->copyRecord($source, $anr, $actors, $categories, $processors, $recipients)->setLabel($label);
        $this->recordTable->save($copy);

        return $copy->getId();
    }

    /** Build new identities and associations; the analysis service keeps its existing flush boundary. */
    public function copy(Anr $source, Anr $target): void
    {
        $actors = $categories = $processors = $recipients = [];
        foreach ($this->recordActorTable->findByAnr($source) as $recordActor) {
            $copy = (new RecordActor())
                ->setAnr($target)
                ->setCreator($recordActor->getCreator())
                ->setUpdater($recordActor->getUpdater())
                ->setLabel($recordActor->getLabel())
                ->setContact($recordActor->getContact());
            $this->recordActorTable->save($copy, false);
            $actors[$recordActor->getId()] = $copy;
        }
        foreach ($this->recordDataCategoryTable->findByAnr($source) as $recordDataCategory) {
            $copy = (new RecordDataCategory())
                ->setAnr($target)
                ->setCreator($recordDataCategory->getCreator())
                ->setUpdater($recordDataCategory->getUpdater())
                ->setLabel($recordDataCategory->getLabel());
            $this->recordDataCategoryTable->save($copy, false);
            $categories[$recordDataCategory->getId()] = $copy;
        }
        foreach ($this->recordProcessorTable->findByAnr($source) as $recordProcessor) {
            $copy = (new RecordProcessor())
                ->setAnr($target)
                ->setCreator($recordProcessor->getCreator())
                ->setUpdater($recordProcessor->getUpdater())
                ->setLabel($recordProcessor->getLabel())
                ->setContact($recordProcessor->getContact())
                ->setActivities($recordProcessor->getActivities())
                ->setSecMeasures($recordProcessor->getSecMeasures());
            if ($recordProcessor->getRepresentative() !== null) {
                $copy->setRepresentative($actors[$recordProcessor->getRepresentative()->getId()] ?? null);
            }
            if ($recordProcessor->getDpo() !== null) {
                $copy->setDpo($actors[$recordProcessor->getDpo()->getId()] ?? null);
            }
            $this->recordProcessorTable->save($copy, false);
            $processors[$recordProcessor->getId()] = $copy;
        }
        foreach ($this->recordRecipientTable->findByAnr($source) as $recordRecipient) {
            $copy = (new RecordRecipient())
                ->setAnr($target)
                ->setCreator($recordRecipient->getCreator())
                ->setUpdater($recordRecipient->getUpdater())
                ->setLabel($recordRecipient->getLabel())
                ->setType($recordRecipient->getType())
                ->setDescription($recordRecipient->getDescription());
            $this->recordRecipientTable->save($copy, false);
            $recipients[$recordRecipient->getId()] = $copy;
        }
        foreach ($this->recordTable->findByAnr($source) as $record) {
            $this->copyRecord(
                $record,
                $target,
                $actors,
                $categories,
                $processors,
                $recipients
            );
        }
    }

    private function copyRecord(
        Record $source,
        Anr $target,
        array $actors,
        array $categories,
        array $processorMap,
        array $recipientMap
    ): Record {
        $copy = (new Record())
            ->setAnr($target)
            ->setLabel($source->getLabel())
            ->setPurposes($source->getPurposes())
            ->setSecMeasures($source->getSecMeasures())
            ->setCreator($source->getCreator())
            ->setUpdater($source->getUpdater());
        if ($source->getController() !== null) {
            $copy->setController($actors[$source->getController()->getId()] ?? null);
        }
        if ($source->getRepresentative() !== null) {
            $copy->setRepresentative($actors[$source->getRepresentative()->getId()] ?? null);
        }
        if ($source->getDpo() !== null) {
            $copy->setDpo($actors[$source->getDpo()->getId()] ?? null);
        }
        $jointControllers = [];
        foreach ($source->getJointControllers() as $jointController) {
            if (isset($actors[$jointController->getId()])) {
                $jointControllers[] = $actors[$jointController->getId()];
            }
        }
        $copy->setJointControllers($jointControllers);
        $processors = [];
        foreach ($source->getProcessors() as $recordProcessor) {
            if (isset($processorMap[$recordProcessor->getId()])) {
                $processors[] = $processorMap[$recordProcessor->getId()];
            }
        }
        $copy->setProcessors($processors);
        $recipients = [];
        foreach ($source->getRecipients() as $recordRecipient) {
            if (isset($recipientMap[$recordRecipient->getId()])) {
                $recipients[] = $recipientMap[$recordRecipient->getId()];
            }
        }
        $copy->setRecipients($recipients);
        $this->recordTable->save($copy, false);
        $personalData = [];
        foreach ($source->getPersonalData() as $recordPersonalData) {
            $child = (new RecordPersonalData())
                ->setAnr($target)
                ->setRecord($copy)
                ->setCreator($recordPersonalData->getCreator())
                ->setUpdater($recordPersonalData->getUpdater())
                ->setDataSubject($recordPersonalData->getDataSubject())
                ->setDescription($recordPersonalData->getDescription())
                ->setRetentionPeriod($recordPersonalData->getRetentionPeriod())
                ->setRetentionPeriodMode($recordPersonalData->getRetentionPeriodMode())
                ->setRetentionPeriodDescription($recordPersonalData->getRetentionPeriodDescription());
            $links = [];
            foreach ($recordPersonalData->getDataCategories() as $category) {
                if (isset($categories[$category->getId()])) {
                    $links[] = $categories[$category->getId()];
                }
            }
            $child->setDataCategories($links);
            $personalData[] = $child;
            $this->recordPersonalDataTable->save($child, false);
        }
        $copy->setPersonalData($personalData);
        $transfers = [];
        foreach ($source->getInternationalTransfers() as $recordInternationalTransfer) {
            $child = (new RecordInternationalTransfer())
                ->setAnr($target)
                ->setRecord($copy)
                ->setCreator($recordInternationalTransfer->getCreator())
                ->setUpdater($recordInternationalTransfer->getUpdater())
                ->setOrganisation($recordInternationalTransfer->getOrganisation())
                ->setDescription($recordInternationalTransfer->getDescription())
                ->setCountry($recordInternationalTransfer->getCountry())
                ->setDocuments($recordInternationalTransfer->getDocuments());
            $transfers[] = $child;
            $this->recordInternationalTransferTable->save($child, false);
        }
        $copy->setInternationalTransfers($transfers);

        return $copy;
    }
}
