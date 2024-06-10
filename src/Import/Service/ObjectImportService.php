<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHL.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Service;

use Doctrine\ORM\EntityNotFoundException;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Entity\ObjectSuperClass;
use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Table;
use Monarc\FrontOffice\Service\SoaCategoryService;

class ObjectImportService
{
    public const IMPORT_MODE_MERGE = 'merge';

    private Table\MonarcObjectTable $monarcObjectTable;

    private AssetImportService $assetImportService;

    private Table\RolfTagTable $rolfTagTable;

    private Table\RolfRiskTable $rolfRiskTable;

    private Table\MeasureTable $measureTable;

    private Table\ObjectObjectTable $objectObjectTable;

    private Table\ReferentialTable $referentialTable;

    private Table\ObjectCategoryTable $objectCategoryTable;

    private UserSuperClass $connectedUser;

    private ImportCacheHelper $importCacheHelper;

    private SoaCategoryService $soaCategoryService;

    public function __construct(
        Table\MonarcObjectTable $monarcObjectTable,
        Table\ObjectObjectTable $objectObjectTable,
        AssetImportService $assetImportService,
        Table\RolfTagTable $rolfTagTable,
        Table\RolfRiskTable $rolfRiskTable,
        Table\MeasureTable $measureTable,
        Table\ReferentialTable $referentialTable,
        Table\ObjectCategoryTable $objectCategoryTable,
        ConnectedUserService $connectedUserService,
        ImportCacheHelper $importCacheHelper,
        SoaCategoryService $soaCategoryService
    ) {
        $this->monarcObjectTable = $monarcObjectTable;
        $this->objectObjectTable = $objectObjectTable;
        $this->assetImportService = $assetImportService;
        $this->rolfTagTable = $rolfTagTable;
        $this->rolfRiskTable = $rolfRiskTable;
        $this->measureTable = $measureTable;
        $this->referentialTable = $referentialTable;
        $this->objectCategoryTable = $objectCategoryTable;
        $this->connectedUser = $connectedUserService->getConnectedUser();
        $this->importCacheHelper = $importCacheHelper;
        $this->soaCategoryService = $soaCategoryService;
    }

    public function importFromArray(
        array $data,
        Entity\Anr $anr,
        string $modeImport = self::IMPORT_MODE_MERGE
    ): ?Entity\MonarcObject {
        if (!isset($data['type'], $data['object']) || $data['type'] !== 'object') {
            return null;
        }

        $this->validateMonarcVersion($data);

        $objectData = $data['object'];

        /* The objects cache preparation is not called, because all the importing objects have to be processed. */
        $monarcObject = $this->importCacheHelper->getItemFromArrayCache('objects', $objectData['uuid']);
        if ($monarcObject !== null) {
            return $monarcObject;
        }

        $asset = $this->assetImportService->importFromArray($this->getMonarcVersion($data), $data['asset'], $anr);
        if ($asset === null) {
            return null;
        }

        $objectCategory = $this->importObjectCategories($data['categories'], (int)$objectData['category'], $anr);
        if ($objectCategory === null) {
            return null;
        }

        /* Import Operational Risks. */
        $rolfTag = $this->processRolfTagAndRolfRisks($data, $anr);

        /*
         * We merge objects with "local" scope or when the scope is "global" and the mode is by "merge".
         * Matching criteria: name, asset type, scope, category.
         */
        $objectScope = (int)$objectData['scope'];
        $nameFiledKey = 'name' . $anr->getLanguage();
        $monarcObject = null;
        if ($objectScope === ObjectSuperClass::SCOPE_LOCAL
            || (
                $objectScope === ObjectSuperClass::SCOPE_GLOBAL
                && $modeImport === self::IMPORT_MODE_MERGE
            )
        ) {
            $monarcObject = $this->monarcObjectTable->findOneByAnrAssetNameScopeAndCategory(
                $anr,
                $nameFiledKey,
                $objectData[$nameFiledKey],
                $asset,
                $objectScope,
                $objectCategory
            );
            if ($monarcObject !== null) {
                $this->objectObjectTable->deleteAllByParent($monarcObject);
            }
        }

        if ($monarcObject === null) {
            $labelKey = 'label' . $anr->getLanguage();
            $monarcObject = (new Entity\MonarcObject())
                ->setAnr($anr)
                ->setAsset($asset)
                ->setCategory($objectCategory)
                ->setRolfTag($rolfTag)
                ->setMode($objectData['mode'] ?? 1)
                ->setScope($objectData['scope'])
                ->setLabels([$labelKey => $objectData[$labelKey]])
                ->setPosition((int)$objectData['position'])
                ->setCreator($this->connectedUser->getEmail());
            try {
                $this->monarcObjectTable->find($anr, $objectData['uuid']);
            } catch (EntityNotFoundException $e) {
                $monarcObject->setUuid($objectData['uuid']);
            }

            $this->setMonarcObjectName($monarcObject, $objectData, $nameFiledKey);
        }

        $monarcObject->addAnr($anr);

        $this->monarcObjectTable->save($monarcObject);

        $this->importCacheHelper->addItemToArrayCache('objects', $monarcObject, $monarcObject->getUuid());

        if (!empty($data['children'])) {
            usort($data['children'], static function ($a, $b) {
                if (isset($a['object']['position'], $b['object']['position'])) {
                    return $a['object']['position'] <=> $b['object']['position'];
                }

                return 0;
            });

            foreach ($data['children'] as $childObjectData) {
                $childMonarcObject = $this->importFromArray($childObjectData, $anr, $modeImport);
                if ($childMonarcObject !== null) {
                    $maxPosition = $this->objectObjectTable->findMaxPosition([
                        'anr' => $anr,
                        'parent' => $monarcObject,
                    ]);
                    $objectsRelation = (new Entity\ObjectObject())
                        ->setAnr($anr)
                        ->setParent($monarcObject)
                        ->setChild($childMonarcObject)
                        ->setPosition($maxPosition + 1)
                        ->setCreator($this->connectedUser->getEmail());

                    $this->objectObjectTable->save($objectsRelation);
                }
            }
        }

        return $monarcObject;
    }

    private function importObjectCategories(
        array $categories,
        int $categoryId,
        Entity\Anr $anr
    ): ?Entity\ObjectCategory {
        if (empty($categories[$categoryId])) {
            return null;
        }

        $parentCategory = $categories[$categoryId]['parent'] === null
            ? null
            : $this->importObjectCategories($categories, (int)$categories[$categoryId]['parent'], $anr);

        $labelKey = 'label' . $anr->getLanguage();

        $objectCategory = $this->objectCategoryTable->findByAnrParentAndLabel(
            $anr,
            $parentCategory,
            $labelKey,
            $categories[$categoryId][$labelKey]
        );

        if ($objectCategory === null) {
            $maxPosition = $this->objectCategoryTable->findMaxPositionByAnrAndParent($anr, $parentCategory);
            $rootCategory = null;
            if ($parentCategory !== null) {
                $rootCategory = $parentCategory->getRoot() ?? $parentCategory;
            }
            $objectCategory = (new Entity\ObjectCategory())
                ->setAnr($anr)
                ->setRoot($rootCategory)
                ->setParent($parentCategory)
                ->setLabels($categories[$categoryId])
                ->setPosition($maxPosition + 1)
                ->setCreator($this->connectedUser->getEmail());

            $this->objectCategoryTable->save($objectCategory);
        }

        return $objectCategory;
    }

    private function processRolfTagAndRolfRisks(array $data, Entity\Anr $anr): ?Entity\RolfTag
    {
        if (empty($data['object']['rolfTag']) || empty($data['rolfTags'][$data['object']['rolfTag']])) {
            return null;
        }

        $rolfTagData = $data['rolfTags'][(int)$data['object']['rolfTag']];
        $rolfTag = $this->importCacheHelper->getItemFromArrayCache('rolfTags', $rolfTagData['code']);
        if ($rolfTag !== null) {
            return $rolfTag;
        }

        $rolfTag = $this->rolfTagTable->findByAnrAndCode($anr, $rolfTagData['code']);
        if ($rolfTag === null) {
            $rolfTag = (new Entity\RolfTag())
                ->setAnr($anr)
                ->setCode($rolfTagData['code'])
                ->setLabels($rolfTagData)
                ->setCreator($this->connectedUser->getEmail());
        }

        if (!empty($rolfTagData['risks'])) {
            foreach ($rolfTagData['risks'] as $riskId) {
                if (!isset($data['rolfRisks'][$riskId])) {
                    continue;
                }

                $rolfRiskData = $data['rolfRisks'][$riskId];
                $rolfRiskCode = (string)$rolfRiskData['code'];
                $rolfRisk = $this->importCacheHelper->getItemFromArrayCache('rolf_risks_by_old_ids', $riskId);
                if ($rolfRisk === null) {
                    $rolfRisk = $this->rolfRiskTable->findByAnrAndCode($anr, $rolfRiskCode);
                    if ($rolfRisk === null) {
                        $rolfRisk = (new Entity\RolfRisk())
                            ->setAnr($anr)
                            ->setCode($rolfRiskCode)
                            ->setLabels($rolfRiskData)
                            ->setDescriptions($rolfRiskData)
                            ->setCreator($this->connectedUser->getEmail());
                    }

                    if (!empty($rolfRiskData['measures'])) {
                        $this->processMeasuresAndReferentialData($anr, $rolfRisk, $rolfRiskData['measures']);
                    }

                    $this->rolfRiskTable->save($rolfRisk, false);

                    /* The cache with IDs is required to link them with operational risks in InstanceImportService. */
                    $this->importCacheHelper->addItemToArrayCache('rolf_risks_by_old_ids', $rolfRisk, (int)$riskId);
                }

                $rolfTag->addRisk($rolfRisk);
            }
        }

        $this->rolfTagTable->save($rolfTag, false);

        $this->importCacheHelper->addItemToArrayCache('rolfTags', $rolfTag, $rolfTagData['code']);

        return $rolfTag;
    }

    private function setMonarcObjectName(
        ObjectSuperClass $monarcObject,
        array $objectData,
        string $nameFiledKey,
        int $index = 1
    ): ObjectSuperClass {
        $existedObject = $this->monarcObjectTable->findOneByAnrAndName(
            $monarcObject->getAnr(),
            $nameFiledKey,
            $objectData[$nameFiledKey]
        );
        if ($existedObject !== null) {
            if (strpos($objectData[$nameFiledKey], ' - Imp. #') !== false) {
                $objectData[$nameFiledKey] = preg_replace('/#\d+/', '#' . $index, $objectData[$nameFiledKey]);
            } else {
                $objectData[$nameFiledKey] .= ' - Imp. #' . $index;
            }

            return $this->setMonarcObjectName($monarcObject, $objectData, $nameFiledKey, $index + 1);
        }

        return $monarcObject->setName($nameFiledKey, $objectData[$nameFiledKey]);
    }

    private function getMonarcVersion(array $data): string
    {
        if (isset($data['monarc_version'])) {
            return strpos($data['monarc_version'], 'master') === false ? $data['monarc_version'] : '99';
        }

        return '1';
    }

    /**
     * @throws Exception
     */
    private function validateMonarcVersion(array $data): void
    {
        if (version_compare($this->getMonarcVersion($data), '2.8.2') < 0) {
            throw new Exception(
                'Import of files exported from MONARC v2.8.1 or lower are not supported.'
                . ' Please contact us for more details.'
            );
        }
    }

    private function processMeasuresAndReferentialData(Entity\Anr $anr, Entity\RolfRisk $rolfRisk, array $measuresData): void
    {
        $labelKey = 'label' . $anr->getLanguage();
        foreach ($measuresData as $measureData) {
            /* Backward compatibility. Prior v2.10.3 measures data were not exported. */
            $measureUuid = $measureData['uuid'] ?? $measureData;
            $measure = $this->importCacheHelper->getItemFromArrayCache('measures', $measureUuid)
                ?: $this->measureTable->findByUuidAndAnr($measureUuid, $anr);

            if ($measure === null && isset($measureData['referential'], $measureData['category'])) {
                $referentialUuid = $measuresData['referential']['uuid'];
                $referential = $this->importCacheHelper->getItemFromArrayCache('referentials', $referentialUuid)
                    ?: $this->referentialTable->findByUuidAndAnr($referentialUuid, $anr);

                if ($referential === null) {
                    $referential = (new Entity\Referential())
                        ->setAnr($anr)
                        ->setUuid($referentialUuid)
                        ->setCreator($this->connectedUser->getEmail())
                        ->setLabels([$labelKey => $measureData['referential'][$labelKey]]);

                    $this->referentialTable->save($referential, false);

                    $this->importCacheHelper->addItemToArrayCache('referentials', $referential, $referentialUuid);
                }

                $soaCategory = $this->soaCategoryService->getOrCreateSoaCategory(
                    $this->importCacheHelper,
                    $anr,
                    $referential,
                    $measureData['category'][$labelKey] ?? ''
                );

                $measure = (new Entity\Measure())
                    ->setAnr($anr)
                    ->setUuid($measureUuid)
                    ->setCategory($soaCategory)
                    ->setReferential($referential)
                    ->setCode($measureData['code'])
                    ->setLabels($measureData)
                    ->setCreator($this->connectedUser->getEmail());

                $this->importCacheHelper->addItemToArrayCache('measures', $measure, $measureUuid);
            }

            if ($measure !== null) {
                $measure->addRolfRisk($rolfRisk);

                $this->measureTable->save($measure, false);
            }
        }
    }

    // TODO : ...
    /**
     * Imports a previously exported object from an uploaded file into the current ANR. It may be imported using two
     * different modes: self::IMPORT_MODE_MERGE, which will update the existing objects using the file's data, or 'duplicate' which
     * will create a new object using the data.
     *
     * @param int $anrId The ANR ID
     * @param array $data The data that has been posted to the API
     *
     * @return array An array where the first key is the generated IDs, and the second are import errors
     * @throws Exception If the uploaded data is invalid, or the ANR invalid
     */
    public function importFromFile($anrId, $data)
    {
        // Mode may either be self::IMPORT_MODE_MERGE or 'duplicate'
        $mode = empty($data['mode']) ? self::IMPORT_MODE_MERGE : $data['mode'];

        // We can have multiple files imported with the same password (we'll emit warnings if the password mismatches)
        if (empty($data['file'])) {
            throw new Exception('File missing', 412);
        }

        $ids = [];
        $errors = [];
        /** @var Table\AnrTable $anrTable */
        $anrTable = $this->get('anrTable');
        $anr = $anrTable->findById($anrId);

        foreach ($data['file'] as $f) {
            // Ensure the file has been uploaded properly, silently skip the files that are erroneous
            if (isset($f['error']) && $f['error'] === UPLOAD_ERR_OK && file_exists($f['tmp_name'])) {
                if (empty($data['password'])) {
                    $file = json_decode(trim(file_get_contents($f['tmp_name'])), true);
                    if ($file === false) { // support legacy export which were base64 encoded
                        $file = json_decode(
                            trim($this->decrypt(base64_decode(file_get_contents($f['tmp_name'])), '')),
                            true
                        );
                    }
                } else {
                    // Decrypt the file and store the JSON data as an array in memory
                    $key = $data['password'];
                    $file = json_decode(trim($this->decrypt(file_get_contents($f['tmp_name']), $key)), true);
                    if ($file === false) { // support legacy export which were base64 encoded
                        $file = json_decode(
                            trim($this->decrypt(base64_decode(file_get_contents($f['tmp_name'])), $key)),
                            true
                        );
                    }
                }

                /** @var ObjectImportService $objectImportService */
                $objectImportService = $this->get('objectImportService');
                $monarcObject = null;
                if ($file !== false) {
                    $monarcObject = $objectImportService->importFromArray($file, $anr, $mode);
                    if ($monarcObject !== null) {
                        $ids[] = $monarcObject->getUuid();
                    }
                }
                if ($monarcObject === null) {
                    $errors[] = 'The file "' . $f['name'] . '" can\'t be imported';
                }
            }
        }

        return [$ids, $errors];
    }

    /**
     * Imports an object from the common database into the specified ANR. The ANR id must be set in $data['anr'].
     *
     * @param int $id The common object ID
     * @param array $data An array with ['anr' => 'The anr id', 'mode' => 'merge or duplicate']
     *
     * @throws Exception If the ANR is invalid, or the object ID is not found
     */
    public function importFromCommon($id, $data): ?Entity\MonarcObject
    {
        if (empty($data['anr'])) {
            throw new Exception('Anr id missing', 412);
        }
        $anr = $this->get('anrTable')->getEntity($data['anr']); // on a une erreur si inconnue

        // TODO: the method doesn't exist anymore we have to replace to simply find by UUID with use core table.

        $object = current($this->get('selfCoreService')->getAnrObjects(
            1,
            -1,
            'name' . $anr->get('language'),
            [],
            ['uuid' => $id],
            $anr->getModel(),
            null,
            AbstractEntity::FRONT_OFFICE
        ));
        if (empty($object)) {
            throw new Exception('Object not found', 412);
        }

        // Export
        $json = $this->get('selfCoreService')->get('objectExportService')->generateExportArray($id);
        if ($json) {
            /** @var ObjectImportService $objectImportService */
            $objectImportService = $this->get('objectImportService');

            return $objectImportService->importFromArray($json, $anr, isset($data['mode']) ? $data['mode'] : self::IMPORT_MODE_MERGE);
        }

        return null;
    }

    /**
     * Fetches and returns the list of objects from the common database. This will only return a limited set of fields,
     * use {#getCommonEntity} to get the entire object definition.
     * @see #getCommonEntity
     *
     * @param int $anrId The target ANR ID, $filter Keywords to search
     *
     * @return array An array of available objects from the common database (knowledge base)
     * @throws Exception If the ANR ID is not set or invalid
     */
    public function getCommonObjects($anrId, $filter = null)
    {
        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }

        $anr = $this->get('anrTable')->getEntity($anrId); // throws an Monarc\Core\Exception\Exception if unknown

        // Fetch the objects from the common database

        // TODO: the method doesn't exist anymore, we need to use getListSpecific or create a new custom one.
        // 'name' . $anr->get('language') - order by, $filter - search by name, label, $anr->getModel() - we get by the id and it will exclude all the attached to the model objects.

        $objects = $this->get('selfCoreService')->getAnrObjects(
            1,
            -1,
            'name' . $anr->get('language'),
            $filter,
            null,
            $anr->getModel(),
            null,
            AbstractEntity::FRONT_OFFICE
        );

        // List of fields we want to keep
        $fields = [
            'uuid',
            'mode',
            'scope',
            'name' . $anr->get('language'),
            'label' . $anr->get('language'),
            'position',
        ];
        $fields = array_combine($fields, $fields);

        foreach ($objects as $k => $o) {
            // Filter out the fields we don't want
            foreach ($o as $k2 => $v2) {
                if (!isset($fields[$k2])) {
                    unset($objects[$k][$k2]);
                }
            }

            // Fetch the category details, if one is set
            if ($o['category']) {
                $objects[$k]['category'] = $o['category']->getJsonArray([
                    'id',
                    'root',
                    'parent',
                    'label' . $anr->get('language'),
                    'position',
                ]);
            }

            // Append the object to our array
            $objects[$k]['asset'] = $o['asset']->getJsonArray([
                'uuid',
                'label' . $anr->get('language'),
                'description' . $anr->get('language'),
                'mode',
                'type',
                'status',
            ]);
        }

        return $objects;
    }

    /**
     * Fetches and returns the details of a specific object from the common database.
     *
     * @param int $anrId The target ANR ID
     * @param int $id The common object ID
     *
     * @return Object The fetched object
     * @throws Exception If the ANR is invalid, or the object ID is not found
     */
    public function getCommonEntity($anrId, $id)
    {
        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }

        $anr = $this->get('anrTable')->getEntity($anrId); // on a une erreur si inconnue

        // TODO: the method doesn't exist anymore we have to replace to simply find by UUID with use core table.

        $object = current($this->get('selfCoreService')->getAnrObjects(
            1,
            -1,
            'name' . $anr->get('language'),
            [],
            ['uuid' => $id],
            $anr->getModel(),
            null,
            AbstractEntity::FRONT_OFFICE
        ));
        if (empty($object)) {
            throw new Exception('Object not found', 412);
        }

        return $this->get('selfCoreService')->getObjectData($id);
    }
}
