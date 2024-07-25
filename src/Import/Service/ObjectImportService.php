<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHL.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Service;

use Monarc\Core\Entity\MonarcObject as CoreMonarcObject;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\Export\ObjectExportService as CoreObjectExportService;
use Monarc\Core\Table\MonarcObjectTable as CoreMonarcObjectTable;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Import\Processor;
use Monarc\FrontOffice\Import\Traits;
use Monarc\FrontOffice\Table;

class ObjectImportService
{
    use Traits\ImportFileContentTrait;
    use Traits\ImportValidationTrait;
    use Traits\ImportDataStructureAdapterTrait;

    public const IMPORT_MODE_MERGE = 'merge';
    public const IMPORT_MODE_DUPLICATE = 'duplicate';

    public function __construct(
        private Processor\ObjectImportProcessor $objectImportProcessor,
        private Processor\ObjectCategoryImportProcessor $objectCategoryImportProcessor,
        private Table\ClientTable $clientTable,
        private CoreMonarcObjectTable $coreMonarcObjectTable,
        private CoreObjectExportService $coreObjectExportService,
        private ImportCacheHelper $importCacheHelper
    ) {
    }

    public function getObjectsDataFromCommonDatabase(Entity\Anr $anr, string $filter)
    {
        /* Fetch all the objects with mode generic and specific that are linked to available clients_models. */
        $clientModelIds = $this->getLinkedSpecificModelIds();

        $languageIndex = $anr->getLanguage();
        $objects = $this->coreMonarcObjectTable->findGenericOrSpecificByModelIdsFilteredByNamePart(
            $clientModelIds,
            $filter,
            $languageIndex
        );

        $result = [];
        foreach ($objects as $object) {
            $result[] = [
                'uuid' => $object->getUuid(),
                'mode' => $object->getMode(),
                'scope' => $object->getScope(),
                'name' . $languageIndex => $object->getName($languageIndex),
                'label' . $languageIndex => $object->getLabel($languageIndex),
                'category' => $object->getCategory() !== null ? [
                    'id' => $object->getCategory()->getId(),
                    'label' . $languageIndex => $object->getCategory()->getLabel($languageIndex),
                ] : null,
                'asset' => [
                    'uuid' => $object->getAsset()->getUuid(),
                    'label' . $languageIndex => $object->getAsset()->getLabel($languageIndex),
                    'description' . $languageIndex => $object->getAsset()->getDescription($languageIndex),
                    'type' => $object->getAsset()->getType(),
                    'mode' => $object->getAsset()->getMode(),
                    'status' => $object->getAsset()->getStatus(),
                ],
            ];
        }

        return $result;
    }

    public function getObjectDataFromCommonDatabase(Entity\Anr $anr, string $uuid): array
    {
        $object = $this->validateAndGetObjectFromCommonDatabase($uuid);

        $languageIndex = $anr->getLanguage();
        $informationRisksData = [];
        foreach ($object->getAsset()->getAmvs() as $amv) {
            $informationRisksData[] = [
                'id' => $amv->getUuid(),
                'threatLabel' . $languageIndex => $amv->getThreat()->getLabel($languageIndex),
                'vulnLabel' . $languageIndex => $amv->getVulnerability()->getLabel($languageIndex),
            ];
        }
        $operationalRisksData = [];
        if ($object->getRolfTag() !== null && $object->getAsset()->isPrimary()) {
            foreach ($object->getRolfTag()->getRisks() as $rolfRisk) {
                $operationalRisksData[] = ['label' . $languageIndex => $rolfRisk->getLabel($languageIndex)];
            }
        }

        return [
            'uuid' => $object->getUuid(),
            'scope' => $object->getScope(),
            'name' . $languageIndex => $object->getName($languageIndex),
            'label' . $languageIndex => $object->getLabel($languageIndex),
            'risks' => $informationRisksData,
            'oprisks' => $operationalRisksData,
        ];
    }

    public function importFromCommonDatabase(Entity\Anr $anr, string $uuid, array $data): Entity\MonarcObject
    {
        $importMode = $data['mode'] ?? self::IMPORT_MODE_MERGE;
        $object = $this->validateAndGetObjectFromCommonDatabase($uuid);

        $objectExportData = $this->coreObjectExportService->prepareExportData($object);

        return $this->importFromArray($anr, $objectExportData, $importMode);
    }

    public function importFromFile(Entity\Anr $anr, array $importParams): array
    {
        /* Mode may either be 'merge' or 'duplicate' */
        $importMode = empty($importParams['mode']) ? self::IMPORT_MODE_MERGE : $importParams['mode'];
        $createdObjectsUuids = [];
        $importErrors = [];
        foreach ($importParams['file'] as $file) {
            if (isset($file['error']) && $file['error'] === UPLOAD_ERR_OK && file_exists($file['tmp_name'])) {
                $data = $this->getArrayDataOfJsonFileContent($file['tmp_name'], $importParams['password'] ?? null);
                if ($data !== false) {
                    $object = $this->importFromArray($anr, $data, $importMode);
                    if ($object !== null) {
                        $createdObjectsUuids[] = $object->getUuid();
                    }
                } else {
                    $importErrors[] = 'The file "' . $file['name'] . '" can\'t be imported';
                }
            }
        }

        return [$createdObjectsUuids, $importErrors];
    }

    public function importFromArray(
        Entity\Anr $anr,
        array $data,
        string $importMode = self::IMPORT_MODE_MERGE
    ): Entity\MonarcObject {
        if (!isset($data['type'], $data['object']) || $data['type'] !== 'object') {
            throw new Exception('The "object" and "type" parameters have to be passed in the import data.', 412);
        }

        $this->setAndValidateImportingDataVersion($data);

        $this->importCacheHelper->setArrayCacheValue('import_type', InstanceImportService::IMPORT_TYPE_OBJECT);

        /* Convert the old structure format to the new one if the import is from MOSP or importing version is below. */
        if (!empty($data['mosp']) || $this->isImportingDataVersionLowerThan('2.13.1')) {
            $data = $this->adaptOldObjectDataStructureToNewFormat($data, $anr->getLanguage());
        }

        $objectCategory = $this->objectCategoryImportProcessor
            ->processObjectCategoryData($anr, $data['object']['category'], $importMode);

        return $this->objectImportProcessor->processObjectData($anr, $objectCategory, $data['object'], $importMode);
    }

    private function validateAndGetObjectFromCommonDatabase(string $uuid): CoreMonarcObject
    {
        /** @var CoreMonarcObject $object */
        $object = $this->coreMonarcObjectTable->findByUuid($uuid);

        /* If the object is specific, the model's access has to be validated. */
        if ($object->isModeSpecific()) {
            $clientModelIds = $this->getLinkedSpecificModelIds();
            $isObjectLinkedToAvailableSpecificModel = false;
            if (!empty($clientModelIds)) {
                foreach ($object->getAnrs() as $linkedAnr) {
                    if (\in_array($linkedAnr->getModel()->getId(), $clientModelIds, true)) {
                        $isObjectLinkedToAvailableSpecificModel = true;
                    }
                }
            }
            if (!$isObjectLinkedToAvailableSpecificModel) {
                throw new Exception('Asset was not found.', 412);
            }
        }

        return $object;
    }

    private function getLinkedSpecificModelIds(): array
    {
        $client = $this->clientTable->findFirstClient();
        $clientModelIds = [];
        if ($client !== null) {
            foreach ($client->getClientModels() as $clientModel) {
                $clientModelIds[] = $clientModel->getModelId();
            }
        }

        return $clientModelIds;
    }
}
