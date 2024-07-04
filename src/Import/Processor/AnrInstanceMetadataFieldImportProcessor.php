<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Processor;

use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Service\AnrInstanceMetadataFieldService;
use Monarc\FrontOffice\Table\AnrInstanceMetadataFieldTable;

class AnrInstanceMetadataFieldImportProcessor
{
    public function __construct(
        private AnrInstanceMetadataFieldTable $anrInstanceMetadataFieldTable,
        private ImportCacheHelper $importCacheHelper,
        private AnrInstanceMetadataFieldService $anrInstanceMetadataFieldService
    ) {
    }

    public function processAnrInstanceMetadataFields(Entity\Anr $anr, array $anrInstanceMetadataFieldsData): void
    {
        $this->prepareAnrInstanceMetadataFieldsCache($anr);
        foreach ($anrInstanceMetadataFieldsData as $anrInstanceMetadataFieldData) {
            $this->processAnrInstanceMetadataField($anr, $anrInstanceMetadataFieldData);
        }
    }

    public function processAnrInstanceMetadataField(
        Entity\Anr $anr,
        array $metadataFieldData
    ): Entity\AnrInstanceMetadataField {
        $anrInstanceMetadataField = $this->getAnrInstanceMetadataFieldsFromCache($metadataFieldData['label']);
        if ($anrInstanceMetadataField !== null) {
            return $anrInstanceMetadataField;
        }

        $metadataField = $this->anrInstanceMetadataFieldService->createAnrInstanceMetadataField(
            $anr,
            $metadataFieldData['label'],
            $metadataFieldData['isDeletable'] ?? true,
            false
        );
        $this->importCacheHelper->addItemToArrayCache(
            'anr_instance_metadata_fields',
            $metadataField,
            $metadataField->getLabel()
        );

        return $metadataField;
    }

    public function getAnrInstanceMetadataFieldsFromCache(string $label): Entity\AnrInstanceMetadataField
    {
        return $this->importCacheHelper->getItemFromArrayCache('anr_instance_metadata_fields', $label);
    }

    private function prepareAnrInstanceMetadataFieldsCache(Entity\Anr $anr): void
    {
        if (!$this->importCacheHelper->isCacheKeySet('anr_instance_metadata_fields')) {
            /** @var Entity\AnrInstanceMetadataField $anrInstanceMetadataField */
            foreach ($this->anrInstanceMetadataFieldTable->findByAnr($anr) as $anrInstanceMetadataField) {
                $this->importCacheHelper->addItemToArrayCache(
                    'anr_instance_metadata_fields',
                    $anrInstanceMetadataField,
                    $anrInstanceMetadataField->getLabel()
                );
            }
        }
    }
}
