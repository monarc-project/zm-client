<?php

namespace Monarc\FrontOffice\Service;

use Doctrine\ORM\EntityNotFoundException;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\AnrObjectCategory;
use Monarc\FrontOffice\Model\Entity\MonarcObject;
use Monarc\FrontOffice\Model\Entity\ObjectCategory;
use Monarc\FrontOffice\Model\Entity\ObjectObject;
use Monarc\FrontOffice\Model\Entity\RolfRisk;
use Monarc\FrontOffice\Model\Entity\RolfTag;
use Monarc\FrontOffice\Model\Table\AnrObjectCategoryTable;
use Monarc\FrontOffice\Model\Table\AssetTable;
use Monarc\FrontOffice\Model\Table\MeasureTable;
use Monarc\FrontOffice\Model\Table\MonarcObjectTable;
use Monarc\FrontOffice\Model\Table\ObjectCategoryTable;
use Monarc\FrontOffice\Model\Table\ObjectObjectTable;
use Monarc\FrontOffice\Model\Table\RolfRiskTable;
use Monarc\FrontOffice\Model\Table\RolfTagTable;

class ObjectExportService
{
    /** @var MonarcObjectTable */
    private $monarcObjectTable;

    /** @var AnrAssetService */
    private $assetService;

    /** @var RolfTagTable */
    private $rolfTagTable;

    /** @var RolfRiskTable */
    private $rolfRiskTable;

    /** @var MeasureTable */
    private $measureTable;

    /** @var ObjectObjectTable */
    private $objectObjectTable;

    /** @var AssetTable */
    private $assetTable;

    public function __construct(
        MonarcObjectTable $monarcObjectTable,
        ObjectObjectTable $objectObjectTable,
        AnrAssetService $assetService,
        RolfTagTable $rolfTagTable,
        RolfRiskTable $rolfRiskTable,
        MeasureTable $measureTable,
        AssetTable $assetTable
    ) {
        $this->monarcObjectTable = $monarcObjectTable;
        $this->objectObjectTable = $objectObjectTable;
        $this->assetService = $assetService;
        $this->rolfTagTable = $rolfTagTable;
        $this->rolfRiskTable = $rolfRiskTable;
        $this->measureTable = $measureTable;
        // TODO: remove after refactoring.
        $this->assetTable = $assetTable;
    }

    /** @var array */
    private $cachedData = [];

    /**
     * @param array $data The object data
     * @param Anr $anr The ANR object
     * @param string $modeImport The import mode possible values: 'merge', 'duplicate'.
     */
    public function importFromArray(array $data, Anr $anr, string $modeImport = 'merge'): ?MonarcObject
    {
        if (!isset($data['type']) || $data['type'] !== 'object') {
            return null;
        }

        $objectData = $data['object'];

        if (isset($this->cachedData['objects'][$objectData['uuid']])) {
            return $this->cachedData['objects'][$objectData['uuid']];
        }

        // TODO: return the asset object.
        // This is a temporary solution. has to be removed when done the refactoring and the assetTable dependency also.
        $assetUuid = $this->assetService->importFromArray($this->getMonarcVersion($data), $data['asset'], $anr);
        if ($assetUuid === null) {
            return null;
        }
        $asset = $this->assetTable->findByAnrAndUuid($anr, $assetUuid);
//        $asset = $this->assetService->importFromArray($this->getMonarcVersion($data), $data['asset'], $anr);
//        if ($asset === null) {
//            return null;
//        }

        $objectCategory = $this->importObjectCategories($data['categories'], (int)$objectData['category'], $anr);
        if ($objectCategory === null) {
            return null;
        }

        /*
         * Import Operational Risks.
         */
        $rolfTag = $this->processRolfTagAndRolfRisks($data, $anr);

        /*
         * We merge objects with "local" scope or when the scope is "global" and the mode is by "merge".
         * Matching criteria: name, asset type, scope, category.
         */
        $objectScope = (int)$objectData['scope'];
        $nameFiledKey = 'name' . $anr->getLanguage();
        $monarcObject = null;
        if ($objectScope === MonarcObject::SCOPE_LOCAL
            || (
                $objectScope === MonarcObject::SCOPE_GLOBAL
                && $modeImport === 'merge'
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
                $this->objectObjectTable->deleteAllByFather($monarcObject);
            }
        }

        if ($monarcObject === null) {
            $labelKey = 'label' . $anr->getLanguage();
            $monarcObject = (new MonarcObject())
                ->setAnr($anr)
                ->setAsset($asset)
                ->setCategory($objectCategory)
                ->setRolfTag($rolfTag)
                ->addAnr($anr)
                ->setMode($objectData['mode'])
                ->setScope($objectData['scope'])
                ->setLabel($labelKey, $objectData[$labelKey])
                ->setDisponibility($objectData['disponibility'])
                ->setPosition($objectData['position']);
            try {
                $this->monarcObjectTable->findByAnrAndUuid($anr, $objectData['uuid']);
            } catch (EntityNotFoundException $e) {
                $monarcObject->setUuid($objectData['uuid']);
            }

            $this->setMonarcObjectName($monarcObject, $objectData, $nameFiledKey);
        }

        $monarcObject->addAnr($anr);

        $this->monarcObjectTable->saveEntity($monarcObject);

        $this->cachedData['objects'][$objectData['uuid']] = $monarcObject;

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
                    $maxPosition = $this->objectObjectTable->findMaxPositionByAnrAndFather($anr, $monarcObject);
                    $objectsRelation = (new ObjectObject())
                        ->setAnr($anr)
                        ->setFather($monarcObject)
                        ->setChild($childMonarcObject)
                        ->setPosition($maxPosition + 1);

                    $this->objectObjectTable->saveEntity($objectsRelation);
                }
            }
        }

        return $monarcObject;
    }

    private function importObjectCategories(
        array $categories,
        int $categoryId,
        Anr $anr
    ): ?ObjectCategory {
        if (empty($categories[$categoryId])) {
            return null;
        }

        $parentCategory = $categories[$categoryId]['parent'] === null
            ? null
            : $this->importObjectCategories($categories, (int)$categories[$categoryId]['parent'], $anr);

        $labelKey = 'label' . $this->getLanguage();

        /** @var ObjectCategoryTable $objectCategoryTable */
        $objectCategoryTable = $this->get('categoryTable');
        $objectCategory = $objectCategoryTable->findByAnrParentAndLabel(
            $anr,
            $parentCategory,
            $labelKey,
            $categories[$categoryId][$labelKey]
        );

        if ($objectCategory === null) {
            $maxPosition = $objectCategoryTable->findMaxPositionByAnrAndParent($anr, $parentCategory);
            $objectCategory = (new ObjectCategory())
                ->setAnr($anr)
                ->setParent($parentCategory)
                ->setRoot($categories[$categoryId] ?? null)
                ->setLabels($categories[$categoryId])
                ->setPosition($maxPosition + 1);

            $objectCategoryTable->saveEntity($objectCategory);
        }

        if ($objectCategory->getParent() === null) {
            $this->checkAndCreateAnrObjectCategoryLink($objectCategory);
        }

        return $objectCategory;
    }

    private function checkAndCreateAnrObjectCategoryLink(ObjectCategory $objectCategory): void
    {
        /** @var AnrObjectCategoryTable $anrObjectCategoryTable */
        $anrObjectCategoryTable = $this->get('anrObjectCategoryTable');
        $anrObjectCategory = $anrObjectCategoryTable->findOneByAnrAndObjectCategory(
            $objectCategory->getAnr(),
            $objectCategory
        );
        if ($anrObjectCategory === null) {
            $maxPosition = $anrObjectCategoryTable->findMaxPositionByAnr($objectCategory->getAnr());
            $anrObjectCategoryTable->saveEntity(
                (new AnrObjectCategory())
                    ->setAnr($objectCategory->getAnr())
                    ->setCategory($objectCategory)
                    ->setPosition($maxPosition)
            );
        }
    }

    private function processRolfTagAndRolfRisks(array $data, Anr $anr): ?RolfTag
    {
        if (empty($data['object']['rolfTag']) || empty($data['rolfTags'][$data['object']['rolfTag']])) {
            return null;
        }

        $rolfTagId = (int)$data['object']['rolfTag'];
        if (isset($this->cachedData['rolfTags'][$rolfTagId])) {
            return $this->cachedData['rolfTags'][$rolfTagId];
        }

        $rolfTagData = $data['rolfTags'][$rolfTagId];
        $rolfTag = $this->rolfTagTable->findByAnrAndCode($anr, $rolfTagData['code']);
        if ($rolfTag === null) {
            $rolfTag = (new RolfTag())
                ->setAnr($anr)
                ->setCode($rolfTagData['code'])
                ->setLabels($rolfTagData['label']);
        }

        if (!empty($rolfTagData['risks'])) {
            foreach ($rolfTagData['risks'] as $riskId) {
                if (!isset($data['rolfRisks'][$riskId])) {
                    continue;
                }

                $rolfRiskData = $data['rolfRisks'][$riskId];

                if (!isset($this->cachedData['rolfRisks'][$rolfRiskData['id']])) {
                    $rolfRiskCode = (string)$rolfRiskData['code'];
                    $rolfRisk = $this->rolfRiskTable->findByAnrAndCode($anr, $rolfRiskCode);
                    if ($rolfRisk === null) {
                        $rolfRisk = (new RolfRisk())
                            ->setAnr($anr)
                            ->setCode($rolfRiskCode)
                            ->setLabels($rolfRiskData)
                            ->setDescriptions($rolfRiskData);
                    }

                    $measures = null;
                    foreach ($rolfRiskData['measures'] as $measureUuid) {
                        // TODO: reuse/replace the part from feature/320 from zm_core, move to a separate method.
                        $measure = $this->measureTable->findByAnrAndUuid($anr, $measureUuid);
                        if ($measure !== null) {
                            $measure->addOpRisk($rolfRisk);
                            $measures[] = $measure;
                        }
                    }

                    $rolfRisk->setMeasures($measures);

                    $this->rolfRiskTable->saveEntity($rolfRisk);

                    $this->cachedData['rolfRisks'][$rolfRiskData['id']] = $rolfRisk;
                }

                $rolfTag->addRisk($this->cachedData['rolfRisks'][$rolfRiskData['id']]);
            }
        }

        $this->rolfTagTable->saveEntity($rolfTag);

        $this->cachedData['rolfTags'][$rolfTagId] = $rolfTag;

        return $rolfTag;
    }

    private function setMonarcObjectName(
        MonarcObject $monarcObject,
        array $objectData,
        string $nameFiledKey,
        int $index = 1
    ): MonarcObject {
        $existedObject = $this->monarcObjectTable->findOneByAnrAndName(
            $monarcObject->getAnr(),
            $nameFiledKey,
            $objectData[$nameFiledKey]
        );
        if ($existedObject !== null) {
            $objectData[$nameFiledKey] .= ' - Imp. #' . $index;

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
}
