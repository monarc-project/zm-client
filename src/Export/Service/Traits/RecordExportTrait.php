<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Service\Traits;

use Monarc\FrontOffice\Entity\Record;
use Monarc\FrontOffice\Entity\RecordActor;
use Monarc\FrontOffice\Entity\RecordRecipient;
use Monarc\FrontOffice\Entity\RecordProcessor;
use Monarc\FrontOffice\Entity\RecordPersonalData;
use Monarc\FrontOffice\Entity\RecordInternationalTransfer;

trait RecordExportTrait
{
    private function prepareRecordData(Record $record): array
    {
        $recordData = [
            'name' => $record->getLabel(),
            'purposes' => $record->getPurposes(),
            'security_measures' => $record->getSecMeasures()
        ];
        if ($record->getController() !== null) {
            $recordData['controller'] = $this->prepareRecordActorData($record->getController());
        }
        if ($record->getRepresentative() !== null) {
            $recordData['representative'] = $this->prepareRecordActorData($record->getRepresentative());
        }
        if ($record->getDpo() !== null) {
            $recordData['data_protection_officer'] = $this->prepareRecordActorData($record->getDpo());
        }
        $recordData['joint_controllers'] = array_map(
            fn ($recordActor): array => $this->prepareRecordActorData($recordActor),
            $record->getJointControllers()->toArray()
        );
        $recordData['personal_data'] = array_map(
            fn ($recordPersonalData): array => $this->prepareRecordPersonalDataData($recordPersonalData),
            $record->getPersonalData()->toArray()
        );
        $recordData['recipients'] = array_map(
            fn ($recordRecipient): array => $this->prepareRecordRecipientData($recordRecipient),
            $record->getRecipients()->toArray()
        );
        $recordData['processors'] = array_map(
            fn ($recordProcessor): array => $this->prepareRecordProcessorData($recordProcessor),
            $record->getProcessors()->toArray()
        );
        $recordData['international_transfers'] = array_map(
            fn ($recordInternationalTransfer): array =>
                $this->prepareRecordInternationalTransferData($recordInternationalTransfer),
            $record->getInternationalTransfers()->toArray()
        );

        return $recordData;
    }

    private function prepareRecordActorData(RecordActor $actor): array
    {
        return ['name' => $actor->getLabel(), 'contact' => $actor->getContact()];
    }

    private function prepareRecordRecipientData(RecordRecipient $recipient): array
    {
        return [
            'name' => $recipient->getLabel(),
            'type' => (int)$recipient->getType() === 0 ? 'internal' : 'external',
            'description' => $recipient->getDescription()
        ];
    }

    private function prepareRecordProcessorData(RecordProcessor $processor): array
    {
        $recordProcessorData = [
            'name' => $processor->getLabel(),
            'contact' => $processor->getContact(),
            'activities' => $processor->getActivities(),
            'security_measures' => $processor->getSecMeasures()
        ];
        if ($processor->getRepresentative() !== null) {
            $recordProcessorData['representative'] = $this->prepareRecordActorData(
                $processor->getRepresentative()
            );
        }
        if ($processor->getDpo() !== null) {
            $recordProcessorData['data_protection_officer'] = $this->prepareRecordActorData(
                $processor->getDpo()
            );
        }

        return $recordProcessorData;
    }

    private function prepareRecordPersonalDataData(RecordPersonalData $recordPersonalData): array
    {
        return [
            'data_subject' => $recordPersonalData->getDataSubject(),
            'data_categories' => array_map(
                fn ($category): array => ['name' => $category->getLabel()],
                $recordPersonalData->getDataCategories()->toArray()
            ),
            'description' => $recordPersonalData->getDescription(),
            'retention_period' => $recordPersonalData->getRetentionPeriod(),
            'retention_period_mode' =>
                ['day(s)', 'month(s)', 'year(s)'][$recordPersonalData->getRetentionPeriodMode()] ?? 'day(s)',
            'retention_period_description' => $recordPersonalData->getRetentionPeriodDescription()
        ];
    }

    private function prepareRecordInternationalTransferData(
        RecordInternationalTransfer $recordInternationalTransfer
    ): array {
        return [
            'organisation' => $recordInternationalTransfer->getOrganisation(),
            'description' => $recordInternationalTransfer->getDescription(),
            'country' => $recordInternationalTransfer->getCountry(),
            'documents' => $recordInternationalTransfer->getDocuments(),
        ];
    }
}
