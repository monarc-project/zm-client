<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Service;

use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\NonUniqueResultException;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Model\Entity\ObjectSuperClass;
use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\AnrObjectCategory;
use Monarc\FrontOffice\Model\Entity\Measure;
use Monarc\FrontOffice\Model\Entity\MonarcObject;
use Monarc\FrontOffice\Model\Entity\ObjectCategory;
use Monarc\FrontOffice\Model\Entity\ObjectObject;
use Monarc\FrontOffice\Model\Entity\Referential;
use Monarc\FrontOffice\Model\Entity\RolfRisk;
use Monarc\FrontOffice\Model\Entity\RolfTag;
use Monarc\FrontOffice\Model\Entity\SoaCategory;
use Monarc\FrontOffice\Model\Table\AnrObjectCategoryTable;
use Monarc\FrontOffice\Model\Table\MeasureTable;
use Monarc\FrontOffice\Model\Table\MonarcObjectTable;
use Monarc\FrontOffice\Model\Table\ObjectCategoryTable;
use Monarc\FrontOffice\Model\Table\ObjectObjectTable;
use Monarc\FrontOffice\Model\Table\ReferentialTable;
use Monarc\FrontOffice\Model\Table\RolfRiskTable;
use Monarc\FrontOffice\Model\Table\RolfTagTable;
use Monarc\FrontOffice\Model\Table\SoaCategoryTable;

class ObjectImportService
{
    /** @var MonarcObjectTable */
    private $monarcObjectTable;

    /** @var AssetImportService */
    private $assetImportService;

    /** @var RolfTagTable */
    private $rolfTagTable;

    /** @var RolfRiskTable */
    private $rolfRiskTable;

    /** @var MeasureTable */
    private $measureTable;

    /** @var ObjectObjectTable */
    private $objectObjectTable;

    /** @var ReferentialTable */
    private $referentialTable;

    /** @var SoaCategoryTable */
    private $soaCategoryTable;

    /** @var ObjectCategoryTable */
    private $objectCategoryTable;

    /** @var AnrObjectCategoryTable */
    private $anrObjectCategoryTable;

    /** @var array */
    private $cachedData = [];

    private UserSuperClass $connectedUser;

    public function __construct(
        MonarcObjectTable $monarcObjectTable,
        ObjectObjectTable $objectObjectTable,
        AssetImportService $assetImportService,
        RolfTagTable $rolfTagTable,
        RolfRiskTable $rolfRiskTable,
        MeasureTable $measureTable,
        ReferentialTable $referentialTable,
        SoaCategoryTable $soaCategoryTable,
        ObjectCategoryTable $objectCategoryTable,
        AnrObjectCategoryTable $anrObjectCategoryTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->monarcObjectTable = $monarcObjectTable;
        $this->objectObjectTable = $objectObjectTable;
        $this->assetImportService = $assetImportService;
        $this->rolfTagTable = $rolfTagTable;
        $this->rolfRiskTable = $rolfRiskTable;
        $this->measureTable = $measureTable;
        $this->referentialTable = $referentialTable;
        $this->soaCategoryTable = $soaCategoryTable;
        $this->objectCategoryTable = $objectCategoryTable;
        $this->anrObjectCategoryTable = $anrObjectCategoryTable;
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    /**
     * @throws NonUniqueResultException
     */
    public function importFromArray(array $data, Anr $anr, string $modeImport = 'merge'): ?MonarcObject
    {
        if (!isset($data['type'], $data['object']) || $data['type'] !== 'object') {
            return null;
        }

        $this->validateMonarcVersion($data);

        $objectData = $data['object'];

        if (isset($this->cachedData['objects'][$objectData['uuid']])) {
            return $this->cachedData['objects'][$objectData['uuid']];
        }

        $asset = $this->assetImportService->importFromArray($this->getMonarcVersion($data), $data['asset'], $anr);
        if ($asset === null) {
            return null;
        }

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
                ->setMode($objectData['mode'] ?? 1)
                ->setScope($objectData['scope'])
                ->setLabel($labelKey, $objectData[$labelKey])
                ->setDisponibility(isset($objectData['disponibility']) ? (float)$objectData['disponibility'] : 0)
                ->setPosition((int)$objectData['position'])
                ->setCreator($this->connectedUser->getEmail());
            try {
                $this->monarcObjectTable->findByAnrAndUuid($anr, $objectData['uuid']);
            } catch (EntityNotFoundException $e) {
                $monarcObject->setUuid($objectData['uuid']);
            }

            $this->setMonarcObjectName($monarcObject, $objectData, $nameFiledKey);
        }

        $monarcObject->addAnr($anr);

        $this->monarcObjectTable->saveEntity($monarcObject);

        $this->cachedData['objects'][$monarcObject->getUuid()] = $monarcObject;

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
                        ->setPosition($maxPosition + 1)
                        ->setCreator($this->connectedUser->getEmail());

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
            $objectCategory = (new ObjectCategory())
                ->setAnr($anr)
                ->setRoot($rootCategory)
                ->setParent($parentCategory)
                ->setLabels($categories[$categoryId])
                ->setPosition($maxPosition + 1)
                ->setCreator($this->connectedUser->getEmail());

            $this->objectCategoryTable->saveEntity($objectCategory);
        }

        if ($objectCategory->getParent() === null) {
            $this->checkAndCreateAnrObjectCategoryLink($objectCategory);
        }

        return $objectCategory;
    }

    private function checkAndCreateAnrObjectCategoryLink(ObjectCategory $objectCategory): void
    {

        $anrObjectCategory = $this->anrObjectCategoryTable->findOneByAnrAndObjectCategory(
            $objectCategory->getAnr(),
            $objectCategory
        );
        if ($anrObjectCategory === null) {
            $maxPosition = $this->anrObjectCategoryTable->findMaxPositionByAnr($objectCategory->getAnr());
            $this->anrObjectCategoryTable->saveEntity(
                (new AnrObjectCategory())
                    ->setAnr($objectCategory->getAnr())
                    ->setCategory($objectCategory)
                    ->setPosition($maxPosition + 1)
                    ->setCreator($this->connectedUser->getEmail())
            );
        }
    }

    /**
     * @throws NonUniqueResultException
     */
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
                ->setLabels($rolfTagData)
                ->setCreator($this->connectedUser->getEmail());
        }

        if (!empty($rolfTagData['risks'])) {
            $this->cachedData['measures'] = array_merge(
                $this->cachedData['measures'] ?? [],
                $this->assetImportService->getCachedDataByKey('measures')
            );
            foreach ($rolfTagData['risks'] as $riskId) {
                if (!isset($data['rolfRisks'][$riskId])) {
                    continue;
                }

                $rolfRiskData = $data['rolfRisks'][$riskId];
                $rolfRiskCode = (string)$rolfRiskData['code'];

                if (!isset($this->cachedData['rolfRisks'][$rolfRiskCode])) {
                    $rolfRisk = $this->rolfRiskTable->findByAnrAndCode($anr, $rolfRiskCode);
                    if ($rolfRisk === null) {
                        $rolfRisk = (new RolfRisk())
                            ->setAnr($anr)
                            ->setCode($rolfRiskCode)
                            ->setLabels($rolfRiskData)
                            ->setDescriptions($rolfRiskData)
                            ->setCreator($this->connectedUser->getEmail());
                    }

                    foreach ($rolfRiskData['measures'] as $newMeasure) {
                        /*
                         * Backward compatibility.
                         * Prior v2.10.3 we did not set the measures data when exported.
                         */
                        $measureUuid = $newMeasure['uuid'] ?? $newMeasure;
                        $measure = $this->cachedData['measures'][$measureUuid]
                            ?? $this->measureTable->findByAnrAndUuid($anr, $measureUuid);
                        if ($measure === null
                            && isset($newMeasure['referential'], $newMeasure['category'])
                        ) {
                            $referential = $this->referentialTable->findByAnrAndUuid(
                                $anr,
                                $newMeasure['referential']['uuid']
                            );
                            if ($referential === null) {
                                $referential = (new Referential())
                                    ->setAnr($anr)
                                    ->setUuid($newMeasure['referential']['uuid'])
                                    ->setCreator($this->connectedUser->getEmail())
                                    ->{'setLabel' . $anr->getLanguage()}(
                                        $newMeasure['referential']['label' . $anr->getLanguage()]
                                    );
                                $this->referentialTable->saveEntity($referential);
                            }

                            $category = $this->soaCategoryTable->getEntityByFields([
                                'anr' => $anr->getId(),
                                'label' . $anr->getLanguage() => $newMeasure['category']['label' . $anr->getLanguage()],
                                'referential' => [
                                    'anr' => $anr->getId(),
                                    'uuid' => $referential->getUuid(),
                                ],
                            ]);
                            if (empty($category)) {
                                $category = (new SoaCategory())
                                    ->setAnr($anr)
                                    ->setReferential($referential)
                                    ->{'setLabel' . $anr->getLanguage()}(
                                        $newMeasure['category']['label' . $anr->getLanguage()]
                                    );
                                $this->soaCategoryTable->saveEntity($category, false);
                            } else {
                                $category = current($category);
                            }

                            $measure = (new Measure())
                                ->setAnr($anr)
                                ->setUuid($measureUuid)
                                ->setCategory($category)
                                ->setReferential($referential)
                                ->setCode($newMeasure['code'])
                                ->setLabels($newMeasure)
                                ->setCreator($this->connectedUser->getEmail());

                            $this->measureTable->saveEntity($measure);
                        }

                        if ($measure !== null) {
                            $measure->addOpRisk($rolfRisk);

                            $this->cachedData['measures'][$measureUuid] = $measure;
                        }
                    }

                    $this->rolfRiskTable->saveEntity($rolfRisk);

                    $this->cachedData['rolfRisks'][$rolfRiskCode] = $rolfRisk;
                }

                $rolfTag->addRisk($this->cachedData['rolfRisks'][$rolfRiskCode]);
            }
        }

        $this->rolfTagTable->saveEntity($rolfTag);

        $this->cachedData['rolfTags'][$rolfTagId] = $rolfTag;

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

    /**
     * @return object[]
     */
    public function getCachedDataByKey(string $key): array
    {
        return $this->cachedData[$key] ?? [];
    }
}
