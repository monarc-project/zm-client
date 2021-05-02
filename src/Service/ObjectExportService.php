<?php

namespace Monarc\FrontOffice\Service;

use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\NonUniqueResultException;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Model\Entity\ObjectCategorySuperClass;
use Monarc\Core\Service\ConfigService;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Table\MonarcObjectTable;
use Monarc\FrontOffice\Model\Table\ObjectObjectTable;

class ObjectExportService
{
    /** @var MonarcObjectTable */
    private $monarcObjectTable;

    /** @var AssetExportService */
    private $assetExportService;

    /** @var ObjectObjectTable */
    private $objectObjectTable;

    /** @var ConfigService */
    private $configService;

    public function __construct(
        MonarcObjectTable $monarcObjectTable,
        ObjectObjectTable $objectObjectTable,
        AssetExportService $assetExportService,
        ConfigService $configService
    ) {
        $this->monarcObjectTable = $monarcObjectTable;
        $this->objectObjectTable = $objectObjectTable;
        $this->assetExportService = $assetExportService;
        $this->configService = $configService;
    }

    /**
     * @throws EntityNotFoundException
     * @throws Exception
     */
    public function generateExportArray(string $uuid, Anr $anr, bool $withEval = false)
    {
        $monarcObject = $this->monarcObjectTable->findByAnrAndUuid($anr, $uuid);

        $result = [
            'monarc_version' => $this->configService->getAppVersion()['appVersion'],
            'type' => 'object',
            'object' => [
                'uuid' => $monarcObject->getUuid(),
                'mode' => $monarcObject->getMode(),
                'scope' => $monarcObject->getScope(),
                'name1' => $monarcObject->getName(1),
                'name2' => $monarcObject->getName(2),
                'name3' => $monarcObject->getName(3),
                'name4' => $monarcObject->getName(4),
                'label1' => $monarcObject->getLabel(1),
                'label2' => $monarcObject->getLabel(2),
                'label3' => $monarcObject->getLabel(3),
                'label4' => $monarcObject->getLabel(4),
                'disponibility' => $monarcObject->getDisponibility(),
                'position' => $monarcObject->getPosition(),
                'category' => null,
                'asset' => null,
                'rolfTag' => null,
            ],
            'categories' => [],
            'asset' => null,
            'rolfTags' => [],
            'rolfRisks' => [],
            'children' => [],
        ];

        if ($monarcObject->getCategory() !== null) {
            $result['object']['category'] = $monarcObject->getCategory()->getId();
            $result['categories'] = $this->getCategoryDataWithItsParents($monarcObject->getCategory());
        }

        if ($monarcObject->getAsset() !== null) {
            $result['object']['asset'] = $monarcObject->getAsset()->getUuid();
            $result['asset'] = $this->assetExportService->generateExportArray(
                $monarcObject->getAsset()->getUuid(),
                $anr,
                $withEval
            );
        }

        $rolfTag = $monarcObject->getRolfTag();
        if ($rolfTag !== null) {
            $result['object']['rolfTag'] = $rolfTag->getId();
            $result['rolfTags'][$rolfTag->getId()] = [
                'id' => $rolfTag->getId(),
                'code' => $rolfTag->getCode(),
                'label1' => $rolfTag->getLabel(1),
                'label2' => $rolfTag->getLabel(2),
                'label3' => $rolfTag->getLabel(3),
                'label4' => $rolfTag->getLabel(4),
                'risks' => [],
            ];

            $rolfRisks = $rolfTag->getRisks();
            if (!empty($rolfRisks)) {
                foreach ($rolfRisks as $rolfRisk) {
                    $rolfRiskId = $rolfRisk->getId();
                    $result['rolfTags'][$rolfTag->getId()]['risks'][$rolfRiskId] = $rolfRiskId;
                    $result['rolfRisks'][$rolfRiskId] = [
                        'id' => $rolfRiskId,
                        'code' => $rolfRisk->getCode(),
                        'label1' => $rolfRisk->getLabel(1),
                        'label2' => $rolfRisk->getLabel(2),
                        'label3' => $rolfRisk->getLabel(3),
                        'label4' => $rolfRisk->getLabel(4),
                        'description1' => $rolfRisk->getDescription(1),
                        'description2' => $rolfRisk->getDescription(2),
                        'description3' => $rolfRisk->getDescription(3),
                        'description4' => $rolfRisk->getDescription(4),
                        'measures' => [],
                    ];
                    foreach ($rolfRisk->getMeasures() as $measure) {
                        $result['rolfRisks'][$rolfRiskId]['measures'] = [
                            'uuid' => $measure->getUuid(),
                            'category' => $measure->getCategory()
                                ? [
                                    'id' => $measure->getCategory()->getId(),
                                    'status' => $measure->getCategory()->getStatus(),
                                    'label1' => $measure->getCategory()->getlabel1(),
                                    'label2' => $measure->getCategory()->getlabel2(),
                                    'label3' => $measure->getCategory()->getlabel3(),
                                    'label4' => $measure->getCategory()->getlabel4(),
                                ]
                                : null,
                            'referential' => [
                                'uuid' => $measure->getReferential()->getUuid(),
                                'label1' => $measure->getReferential()->getLabel1(),
                                'label2' => $measure->getReferential()->getLabel2(),
                                'label3' => $measure->getReferential()->getLabel3(),
                                'label4' => $measure->getReferential()->getLabel4(),
                            ],
                            'code' => $measure->getCode(),
                            'label1' => $measure->getLabel1(),
                            'label2' => $measure->getLabel2(),
                            'label3' => $measure->getLabel3(),
                            'label4' => $measure->getLabel4(),
                        ];
                    }
                }
            }
        }

        $children = $this->objectObjectTable->findChildrenByFather($monarcObject, ['position' => 'ASC']);
        if (!empty($children)) {
            $position = 1;
            foreach ($children as $child) {
                $childObjectUuid = $child->getChild()->getUuid();
                $result['children'][$childObjectUuid] = $this->generateExportArray(
                    $childObjectUuid,
                    $anr,
                    $withEval
                );
                $result['children'][$childObjectUuid]['object']['position'] = $position++;
            }
        }

        return $result;
    }

    private function getCategoryDataWithItsParents(ObjectCategorySuperClass $objectCategory): array
    {
        $objectCategories[$objectCategory->getId()] = [
            'id' => $objectCategory->getId(),
            'label1' => $objectCategory->getLabel(1),
            'label2' => $objectCategory->getLabel(2),
            'label3' => $objectCategory->getLabel(3),
            'label4' => $objectCategory->getLabel(4),
            'parent' => null,
        ];
        if ($objectCategory->getParent() !== null) {
            $objectCategories[$objectCategory->getId()]['parent'] = $objectCategory->getParent()->getId();
            $objectCategories = array_merge(
                $objectCategories,
                $this->getCategoryDataWithItsParents($objectCategory->getParent())
            );
        }

        return $objectCategories;
    }

    /**
     * @throws EntityNotFoundException
     * @throws Exception
     */
    public function generateExportMospArray(string $uuid, Anr $anr): array
    {
        $languageIndex = $anr->getLanguage();
        $languageCode = $this->configService->getLanguageCodes()[$languageIndex];

        $monarcObject = $this->monarcObjectTable->findByAnrAndUuid($anr, $uuid);

        $result = [
            'object' => [
                'uuid' => $monarcObject->getUuid(),
                'scope' => $monarcObject->getScopeName(),
                'name' => $monarcObject->getName($languageIndex),
                'label' => $monarcObject->getLabel($languageIndex),
                'language' => $languageCode,
                'version' => 1,
            ],
            'asset' => null,
            'rolfRisks' => [],
            'rolfTags' => [],
            'children' => [],
        ];

        if ($monarcObject->getAsset() !== null) {
            $result['asset'] = $this->assetExportService->generateExportMospArray(
                $monarcObject->getAsset()->getUuid(),
                $anr,
                $languageCode
            );
        }

        $rolfTag = $monarcObject->getRolfTag();
        if ($rolfTag !== null) {
            $result['rolfTags'][] = [
                'code' => $rolfTag->getCode(),
                'label' => $rolfTag->getLabel($languageIndex),
            ];

            if (!empty($rolfTag->getRisks())) {
                foreach ($rolfTag->getRisks() as $rolfRisk) {
                    $measuresData = [];
                    foreach ($rolfRisk->getMeasures() as $measure) {
                        $measuresData[] = [
                            'uuid' => $measure->getUuid(),
                            'code' => $measure->getCode(),
                            'label' => $measure->{'getLabel' . $languageIndex}(),
                            'category' => $measure->getCategory()->{'getLabel' . $languageIndex}(),
                            'referential' => $measure->getReferential()->getUuid(),
                            'referential_label' => $measure->getReferential()->{'getLabel' . $languageIndex}(),
                        ];
                    }

                    $result['rolfRisks'][] = [
                        'code' => $rolfRisk->getCode(),
                        'label' => $rolfRisk->getLabel($languageIndex),
                        'description' => $rolfRisk->getDescription($languageIndex),
                        'measures' => $measuresData,
                    ];
                }
            }
        }

        $children = $this->objectObjectTable->findChildrenByFather($monarcObject, ['position' => 'ASC']);
        if (!empty($children)) {
            foreach ($children as $child) {
                $result['children'][] = $this->generateExportMospArray(
                    $child->getChild()->getUuid(),
                    $anr
                );
            }
        }

        return ['object' => $result];
    }

    /**
     * @throws EntityNotFoundException
     * @throws NonUniqueResultException
     */
    public function generateExportFileName(string $uuid, Anr $anr, bool $isForMosp = false): string
    {
        $monarcObject = $this->monarcObjectTable->findByAnrAndUuid($anr, $uuid);

        return preg_replace(
            "/[^a-z0-9._-]+/i",
            '',
            $monarcObject->getName($anr->getLanguage()) . $isForMosp ? '_MOSP' : ''
        );
    }
}
