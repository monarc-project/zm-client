<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Traits;

use Monarc\Core\Entity\InstanceRiskSuperClass;

trait ImportDataStructureAdapterTrait
{
    private int $recommendationsPositionsCounter = 0;

    /** Converts the scales, comments and impact types related data from the structure prior v2.13.1 to the new one. */
    public function adoptOldScalesDataStructureToNewFormat(array $data, int $languageIndex): array
    {
        $newStructure = $data['scales'];
        if (!empty($data['scalesComments'])) {
            foreach ($data['scalesComments'] as $scalesCommentData) {
                $scaleType = $scalesCommentData['scale']['type'];
                if (!empty($scalesCommentData['scaleImpactType'])) {
                    $typeOfScaleImpactType = $scalesCommentData['scaleImpactType']['type'];
                    if (!isset($newStructure[$scaleType]['scaleImpactTypes'][$typeOfScaleImpactType])) {
                        $newStructure[$scaleType]['scaleImpactTypes'][$typeOfScaleImpactType] = [
                            'type' => $typeOfScaleImpactType,
                            'label' => $scalesCommentData['scaleImpactType']['labels']['label' . $languageIndex],
                            'isSys' => $scalesCommentData['scaleImpactType']['isSys'],
                            'isHidden' => $scalesCommentData['scaleImpactType']['isHidden'],
                        ];
                    }
                    $newStructure[$scaleType]['scaleImpactTypes'][$typeOfScaleImpactType]['scaleComments'][] = [
                        'scaleIndex' => $scalesCommentData['scaleIndex'],
                        'scaleValue' => $scalesCommentData['scaleValue'],
                        'comment' => $scalesCommentData['comment' . $languageIndex],
                    ];
                    $newStructure[$scaleType]['scaleComments'] = [];
                } else {
                    $newStructure[$scaleType]['scaleImpactTypes'] = [];
                    $newStructure[$scaleType]['scaleComments'][] = [
                        'scaleIndex' => $scalesCommentData['scaleIndex'],
                        'scaleValue' => $scalesCommentData['scaleValue'],
                        'comment' => $scalesCommentData['comment' . $languageIndex],
                    ];
                }
            }
        }

        return $newStructure;
    }

    public function adaptOldSoaScaleCommentsToNewFormat(array $soaScaleCommentsData): array
    {
        $newStructure = [];
        foreach ($soaScaleCommentsData as $scaleCommentData) {
            $newStructure[$scaleCommentData['scaleIndex']] = $scaleCommentData;
        }

        return $newStructure;
    }

    public function adaptOldSoasToNewFormatWithSoaScaleCommentIndex(array $data): array
    {
        $newStructure = [];
        foreach ($data['soas'] as $soaData) {
            $newStructure[] = array_merge($soaData, isset($soaData['soaScaleComment']) ? [
                'soaScaleCommentIndex' => $data['soaScaleComment'][$soaData['soaScaleComment']]['scaleIndex'] ?? -1,
            ] : []);
        }

        return $newStructure;
    }

    /** Converts all the instance related data from the structure prior v2.13.1 to the new one. */
    public function adaptOldInstanceDataToNewFormat(array $data, int $languageIndex): array
    {
        return [
            'name' => $data['instance']['name' . $languageIndex],
            'label' => $data['instance']['label' . $languageIndex],
            'level' => $data['instance']['level'],
            'position' => $data['instance']['position'],
            'confidentiality' => $data['instance']['c'],
            'integrity' => $data['instance']['i'],
            'availability' => $data['instance']['d'],
            'isConfidentialityInherited' => $data['instance']['ch'],
            'isIntegrityInherited' => $data['instance']['ih'],
            'isAvailabilityInherited' => $data['instance']['dh'],
            'asset' => $data['object']['asset']['asset'],
            'object' => $this->adaptOldObjectDataStructureToNewFormat($data['object'], $languageIndex)['object'],
            'instanceMetadata' => $this->prepareInstanceMetadataData($data),
            'instanceRisks' => $this->prepareInstanceRisksData($data, $languageIndex),
            'operationalInstanceRisks' => $this->prepareOperationalInstanceRisksData($data, $languageIndex),
            'instancesConsequences' => $this->prepareInstanceConsequencesData($data, $languageIndex),
            'children' => $this->prepareChildrenInstancesData($data, $languageIndex),
        ];
    }

    /** Converts all the object's related data from the structure prior v2.13.1 to the new one. */
    public function adaptOldObjectDataStructureToNewFormat(array $data, int $languageIndex): array
    {
        $newStructure['object'] = [
            'uuid' => $data['object']['uuid'],
            'name' => $data['object']['name' . $languageIndex],
            'label' => $data['object']['label' . $languageIndex],
            'mode' => $data['object']['mode'],
            'scope' => $data['object']['scope'],
        ];
        $newStructure['object']['category'] = $this->adaptOldCategoryStructureToNewFormat($data);
        $newStructure['object']['asset'] = $data['asset']['asset'];
        $newStructure['object']['asset']['informationRisks'] = [];
        foreach ($data['asset']['amvs'] ?? [] as $amvData) {
            $threatData = $data['asset']['threats'][$amvData['threat']];
            $threatData['theme'] = !empty($data['asset']['themes'][$threatData['theme'] ?? -1])
                ? $data['asset']['themes'][$threatData['theme']]
                : null;
            $measuresData = [];
            foreach ($amvData['measures'] ?? [] as $measureUuid) {
                if (!empty($data['asset']['measures'][$measureUuid])) {
                    $measuresData[] = $data['asset']['measures'][$measureUuid];
                }
            }

            $newStructure['object']['asset']['informationRisks'][] = [
                'uuid' => $amvData['uuid'],
                'asset' => $data['asset']['asset'],
                'threat' => $threatData,
                'vulnerability' => $data['asset']['vuls'][$amvData['vulnerability']],
                'measures' => $measuresData,
                'status' => $amvData['status'],
            ];
        }
        $newStructure['object']['rolfTag'] = null;
        if (isset($data['object']['rolfTag']) && !empty($data['rolfTags'][$data['object']['rolfTag']])) {
            $newStructure['object']['rolfTag'] = $data['rolfTags'][$data['object']['rolfTag']];
            $newStructure['object']['rolfTag']['rolfRisks'] = [];
            foreach ($newStructure['object']['rolfTag']['risks'] ?? [] as $rolfRiskId) {
                if (!empty($data['object']['rolfRisks'][$rolfRiskId])) {
                    $rolfRiskData = $data['object']['rolfRisks'][$rolfRiskId];
                    $measuresData = [];
                    foreach ($rolfRiskData['measures'] ?? [] as $measureUuid) {
                        if (!empty($data['asset']['measures'][$measureUuid])) {
                            $measuresData[] = $data['asset']['measures'][$measureUuid];
                        }
                    }
                    $rolfRiskData['measures'] = $measuresData;
                    $newStructure['object']['rolfTag']['rolfRisks'][] = $rolfRiskData;
                }
            }
        }
        $newStructure['object']['children'] = [];
        foreach ($data['children'] ?? [] as $childObjectData) {
            $newStructure['object']['children'][] = $this->adaptOldObjectDataStructureToNewFormat(
                $childObjectData,
                $languageIndex
            )['object'];
        }

        return $newStructure;
    }

    public function adaptOldRecommendationSetsDataToNewFormat(array $data): array
    {
        $recommendationSetsData = [];
        foreach ($data['recs'] as $recommendationData) {
            $recommendationSetUuid = $recommendationData['recommandationSet'];
            if (!isset($recommendationSetsData[$recommendationSetUuid])) {
                $recommendationSetsData[$recommendationSetUuid] = $data['recSets'][$recommendationSetUuid];
                $recommendationSetsData[$recommendationSetUuid]['recommendations'] = [];
            }
            $recommendationSetsData[$recommendationSetUuid]['recommendations'][] = $recommendationData;
        }

        return $recommendationSetsData;
    }

    private function adaptOldCategoryStructureToNewFormat(array $data): array
    {
        $newCategoryStructure = [];
        if (isset($data['object']['category'], $data['categories'][$data['object']['category']])) {
            $newCategoryStructure = $data['categories'][$data['object']['category']];
            $newCategoryStructure['parent'] = $this
                ->prepareNewStructureOfParentsHierarchy($data['categories'], (int)$newCategoryStructure['parent']);
        }

        return $newCategoryStructure;
    }

    private function prepareNewStructureOfParentsHierarchy(array $categoriesData, int $parentId): ?array
    {
        $parentCategoryData = null;
        if (!empty($categoriesData[$parentId])) {
            $parentCategoryData = $categoriesData[$parentId];
            $parentCategoryData['parent'] = $this
                ->prepareNewStructureOfParentsHierarchy($categoriesData, (int)$parentCategoryData['parent']);
        }

        return $parentCategoryData;
    }

    private function prepareInstanceMetadataData(array $data): array
    {
        $instanceMetadataData = [];
        foreach ($data['instancesMetadatas'] ?? [] as $metadataFiledId => $instanceMetadataDatum) {
            if (isset($data['anrMetadatasOnInstances'][$metadataFiledId]['label'])) {
                $instanceMetadataData[] = [
                    'comment' => $instanceMetadataDatum['comment'],
                    'anrInstanceMetadataField' => [
                        'label' => $data['anrMetadatasOnInstances'][$metadataFiledId]['label'],
                    ],
                ];
            }
        }

        return $instanceMetadataData;
    }

    private function prepareInstanceRisksData(array $data, int $languageIndex): array
    {
        $instanceRisksData = [];
        foreach ($data['risks'] ?? [] as $instanceRiskDatum) {
            $informationRiskData = null;
            $threatData = $data['threats'][$instanceRiskDatum['threat']] ?? ['uuid' => $instanceRiskDatum['threat']];
            if (!empty($threatData['theme'])) {
                $threatData['theme'] = $data['object']['asset']['themes'][$threatData['theme']];
            }
            $vulnerabilityData = $data['vuls'][$instanceRiskDatum['vulnerability']] ?? [
                    'uuid' => $instanceRiskDatum['vulnerability']
                ];
            if (!empty($instanceRiskDatum['amv']) && isset($data['amvs'][$instanceRiskDatum['amv']])) {
                $measuresData = [];
                foreach ($data['amvs'][$instanceRiskDatum['amv']]['measures'] ?? [] as $measureUuid) {
                    if (!empty($data['measures'][$measureUuid])) {
                        $measuresData[] = $data['measures'][$measureUuid];
                    }
                }
                $informationRiskData = [
                    'uuid' => $instanceRiskDatum['amv'],
                    'asset' => $data['object']['asset']['asset'],
                    'threat' => $threatData,
                    'vulnerability' => $vulnerabilityData,
                    'measures' => $measuresData,
                    'status' => $data['amvs'][$instanceRiskDatum['amv']]['status'],
                ];
            }
            $recommendationsData = [];
            if (!empty($data['recos'][$instanceRiskDatum['id']])) {
                foreach ($data['recos'][$instanceRiskDatum['id']] as $recommendationData) {
                    $recommendationsData[] = $this->prepareRecommendationData(
                        $data,
                        $recommendationData,
                        $instanceRiskDatum['kindOfMeasure'] !== InstanceRiskSuperClass::KIND_NOT_TREATED,
                        $languageIndex
                    );
                }
            }

            $instanceRisksData[] = [
                'informationRisk' => $informationRiskData,
                'threat' => $threatData,
                'vulnerability' => $vulnerabilityData,
                'specific' => $instanceRiskDatum['specific'],
                'isThreatRateNotSetOrModifiedExternally' => $instanceRiskDatum['mh'],
                'threatRate' => $instanceRiskDatum['threatRate'],
                'vulnerabilityRate' => $instanceRiskDatum['vulnerabilityRate'],
                'kindOfMeasure' => $instanceRiskDatum['kindOfMeasure'],
                'reductionAmount' => $instanceRiskDatum['reductionAmount'],
                'comment' => $instanceRiskDatum['comment'],
                'commentAfter' => $instanceRiskDatum['commentAfter'],
                'cacheMaxRisk' => $instanceRiskDatum['cacheMaxRisk'],
                'cacheTargetedRisk' => $instanceRiskDatum['cacheTargetedRisk'],
                'riskConfidentiality' => $instanceRiskDatum['riskC'],
                'riskIntegrity' => $instanceRiskDatum['riskI'],
                'riskAvailability' => $instanceRiskDatum['riskD'],
                'context' => $instanceRiskDatum['context'],
                'riskOwner' => $instanceRiskDatum['riskOwner'],
                'recommendations' => $recommendationsData,
            ];
        }

        return $instanceRisksData;
    }

    private function prepareInstanceConsequencesData(array $data, int $languageIndex): array
    {
        $instanceConsequencesData = [];
        foreach ($data['consequences'] as $consequenceData) {
            $instanceConsequencesData[] = [
                'confidentiality' => $consequenceData['c'],
                'integrity' => $consequenceData['i'],
                'availability' => $consequenceData['d'],
                'isHidden' => (bool)$consequenceData['isHidden'],
                'scaleImpactType' => [
                    'type' => $consequenceData['scaleImpactType']['type'],
                    'label' => $consequenceData['scaleImpactType']['label' . $languageIndex],
                    'isSys' => (bool)$consequenceData['scaleImpactType']['isSys'],
                    'isHidden' => (bool)$consequenceData['scaleImpactType']['isHidden'],
                    'scaleComments' => [],
                    'scale' => [],
                ],
            ];
        }

        return $instanceConsequencesData;
    }

    private function prepareChildrenInstancesData(array $data, int $languageIndex): array
    {
        $childInstancesData = [];
        foreach ($data['children'] as $childInstanceData) {
            $childInstancesData[] = $this->adaptOldInstanceDataToNewFormat($childInstanceData, $languageIndex);
        }

        return $childInstancesData;
    }

    private function prepareOperationalInstanceRisksData(array $data, int $languageIndex): array
    {
        $operationalInstanceRisksData = [];
        foreach ($data['risksop'] as $operationalInstanceRiskData) {
            $rolfRiskData = null;
            if (!empty($operationalInstanceRiskData['rolfRisk'])) {
                $rolfRiskData = $data['object']['rolfRisks'][$operationalInstanceRiskData['rolfRisk']];
                $rolfTagsData = [];
                foreach ($data['object']['rolfTags'] as $rolfTagData) {
                    if (isset($rolfTagData['risks'][$rolfRiskData['id']])) {
                        $rolfTagsData[] = [
                            'label' => $rolfTagData['label' . $languageIndex],
                            'code' => $rolfTagData['code'],
                        ];
                    }
                }
                $rolfRiskData['rolfTags'] = $rolfTagsData;
            }
            $recommendationsData = [];
            if (!empty($data['recosop'][$operationalInstanceRiskData['id']])) {
                foreach ($data['recosop'][$operationalInstanceRiskData['id']] as $recommendationData) {
                    $recommendationsData[] = $this->prepareRecommendationData(
                        $data,
                        $recommendationData,
                        $operationalInstanceRiskData['kindOfMeasure'] !== InstanceRiskSuperClass::KIND_NOT_TREATED,
                        $languageIndex
                    );
                }
            }

            $operationalInstanceRisksData[] = [
                'operationalRisk' => $rolfRiskData,
                'riskCacheCode' => $operationalInstanceRiskData['riskCacheCode'] ?? 'empty-code-' . time(),
                'riskCacheLabel' => $operationalInstanceRiskData['riskCacheLabel' . $languageIndex],
                'riskCacheDescription' => $operationalInstanceRiskData['riskCacheDescription' . $languageIndex],
                'brutProb' => $operationalInstanceRiskData['brutProb'],
                'netProb' => $operationalInstanceRiskData['netProb'],
                'targetedProb' => $operationalInstanceRiskData['targetedProb'],
                'cacheBrutRisk' => $operationalInstanceRiskData['cacheBrutRisk'],
                'cacheNetRisk' => $operationalInstanceRiskData['cacheNetRisk'],
                'cacheTargetedRisk' => $operationalInstanceRiskData['cacheTargetedRisk'],
                'kindOfMeasure' => $operationalInstanceRiskData['kindOfMeasure'],
                'comment' => $operationalInstanceRiskData['comment'],
                'mitigation' => $operationalInstanceRiskData['mitigation'],
                'specific' => $operationalInstanceRiskData['specific'],
                'context' => $operationalInstanceRiskData['context'],
                'riskOwner' => $operationalInstanceRiskData['riskOwner'],
                'recommendations' => $recommendationsData,
                'operationalInstanceRiskScales' => $operationalInstanceRiskData['scalesValues'],
            ];
        }

        return $operationalInstanceRisksData;
    }

    private function prepareRecommendationData(
        array $data,
        array $recommendationData,
        bool $isRiskTreated,
        int $languageIndex
    ): array {
        $recommendationSetUuid = $recommendationData['recommendationSet'] ?? '';

        return [
            'uuid' => $recommendationData['uuid'],
            'code' => $recommendationData['code'],
            'description' => $recommendationData['description'],
            'importance' => $recommendationData['importance'],
            'comment' => $recommendationData['comment'],
            'status' => $recommendationData['status'],
            'responsible' => $recommendationData['responsable'],
            'duedate' => $recommendationData['duedate'],
            'counterTreated' => $recommendationData['counterTreated'],
            'commentAfter' => $recommendationData['commentAfter'],
            'position' => $isRiskTreated ? ++$this->recommendationsPositionsCounter : 0,
            'recommendationSet' => [
                'uuid' => $recommendationSetUuid,
                'label' => $data['recSets'][$recommendationSetUuid]['label' . $languageIndex] ?? 'Imported',
            ],
        ];
    }
}
