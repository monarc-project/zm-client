<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Service;

use Monarc\Core\Helper\EncryptDecryptHelperTrait;
use Monarc\FrontOffice\Entity\Instance;
use Monarc\FrontOffice\Entity\RecommendationRisk;
use Monarc\FrontOffice\Service\Export\ObjectExportService;
use Monarc\FrontOffice\Export\Service\OperationalRiskScalesExportService;

class AnrInstanceExportService
{
    use EncryptDecryptHelperTrait;

    private AmvExportService $amvExportService;

    public function __construct(AmvExportService $amvExportService)
    {
        $this->amvExportService = $amvExportService;
    }

    // TODO: return an array [filename + export content]
    public function export($data): array
    {
        if (empty($data['id'])) {
            throw new Exception('Instance to export is required', 412);
        }

        $filename = '';

        $withEval = isset($data['assessments']) && $data['assessments'];
        $withControls = isset($data['controls']) && $data['controls'];
        $withRecommendations = isset($data['recommendations']) && $data['recommendations'];
        $withScale = true;

        $exportedInstance = json_encode($this->generateExportArray(
            (int)$data['id'],
            $filename,
            $withEval,
            $withScale,
            $withControls,
            $withRecommendations,
            false
        ));
        $data['filename'] = $filename;

        if (!empty($data['password'])) {
            $exportedInstance = $this->encrypt($exportedInstance, $data['password']);
        }

        return $exportedInstance;
    }

    //TODO: $id => Instance $instance and so on
    public function generateExportArray(
        $id,
        &$filename = "",
        $withEval = false,
        $withScale = true,
        $withControls = false,
        $withRecommendations = false,
        $withUnlinkedRecommendations = true
    ) {
        /** @var Instance $instance */
        $instance = $this->instanceTable->findById((int)$id);

        $filename = preg_replace("/[^a-z0-9\._-]+/i", '', $instance->getName($this->getLanguage()));

        // TODO: ObjectExportService can be a class from client or core.
        /** @var ObjectExportService $objectExportService */
        $objectExportService = $this->get('objectExportService');
        $return = [
            'type' => 'instance',
            'monarc_version' => $this->get('configService')->getAppVersion()['appVersion'],
            'with_eval' => $withEval,
            'instance' => [
                'id' => $instance->getId(),
                'name1' => $instance->getName(1),
                'name2' => $instance->getName(2),
                'name3' => $instance->getName(3),
                'name4' => $instance->getName(4),
                'label1' => $instance->getLabel(1),
                'label2' => $instance->getLabel(2),
                'label3' => $instance->getLabel(3),
                'label4' => $instance->getLabel(4),
                'level' => $instance->getLevel(),
                'assetType' => $instance->getAssetType(),
                'exportable' => $instance->getExportable(),
                'position' => $instance->getPosition(),
                'c' => $withEval ? $instance->getConfidentiality() : -1,
                'i' => $withEval ? $instance->getIntegrity() : -1,
                'd' => $withEval ? $instance->getAvailability() : -1,
                'ch' => $withEval ? (int)$instance->isConfidentialityInherited() : 1,
                'ih' => $withEval ? (int)$instance->isIntegrityInherited() : 1,
                'dh' => $withEval ? (int)$instance->isAvailabilityInherited() : 1,
                'asset' => $instance->getAsset()->getUuid(),
                'object' => $instance->getObject()->getUuid(),
                'root' => 0,
                'parent' => $instance->getParent() ? $instance->getParent()->getId() : 0,
            ],
            // TODO: we don't need to pass anr param for the BackOffice export.
            'object' => $objectExportService->generateExportArray(
                $instance->getObject()->getUuid(),
                $instance->getAnr(),
                $withEval
            ),
        ];

        $instanceMetadataFieldsExportService = $this->get('instanceMetadataFieldsExportService');
        // TODO: of FrontOffice side add the backward compatibility to support the 'anrMetadatasOnInstances'
        $return['instanceMetadataFields'] = $instanceMetadataFieldsExportService->generateExportArray(
            $instance->getAnr()
        );
        if ($withEval) {
            // TODO: of FrontOffice side add the backward compatibility to support the 'anrMetadatasOnInstances'
            $return['instanceMetadataFields'] = $this->generateExportArrayOfInstanceMetadataFields($instance);
        }

        // Scales
        if ($withEval && $withScale) {
            $return['scales'] = $this->generateExportArrayOfScales($instance->getAnr());
            /** @var OperationalRiskScalesExportService $operationalRiskScalesExportService */
            $operationalRiskScalesExportService = $this->get('operationalRiskScalesExportService');
            $return['operationalRiskScales'] = $operationalRiskScalesExportService->generateExportArray(
                $instance->getAnr()
            );
        }

        // Instance risk
        $return['risks'] = [];

        $instanceRiskArray = [
            'id' => 'id',
            'specific' => 'specific',
            'mh' => 'mh',
            'threatRate' => 'threatRate',
            'vulnerabilityRate' => 'vulnerabilityRate',
            'kindOfMeasure' => 'kindOfMeasure',
            'reductionAmount' => 'reductionAmount',
            'comment' => 'comment',
            'commentAfter' => 'commentAfter',
            'riskC' => 'riskC',
            'riskI' => 'riskI',
            'riskD' => 'riskD',
            'cacheMaxRisk' => 'cacheMaxRisk',
            'cacheTargetedRisk' => 'cacheTargetedRisk',
        ];

        $treatsObj = [
            'uuid' => 'uuid',
            'mode' => 'mode',
            'code' => 'code',
            'label1' => 'label1',
            'label2' => 'label2',
            'label3' => 'label3',
            'label4' => 'label4',
            'description1' => 'description1',
            'description2' => 'description2',
            'description3' => 'description3',
            'description4' => 'description4',
            'c' => 'c',
            'i' => 'i',
            'a' => 'a',
            'status' => 'status',
        ];
        if ($withEval) {
            $treatsObj = array_merge(
                $treatsObj,
                [
                    'trend' => 'trend',
                    'comment' => 'comment',
                    'qualification' => 'qualification'
                ]
            );
        };
        $vulsObj = [
            'uuid' => 'uuid',
            'mode' => 'mode',
            'code' => 'code',
            'label1' => 'label1',
            'label2' => 'label2',
            'label3' => 'label3',
            'label4' => 'label4',
            'description1' => 'description1',
            'description2' => 'description2',
            'description3' => 'description3',
            'description4' => 'description4',
            'status' => 'status',
        ];
        $riskIds = [];
        foreach ($instance->getInstanceRisks() as $instanceRisk) {
            $riskIds[$instanceRisk->getId()] = $instanceRisk->getId();
            if (!$withEval) {
                $instanceRisk->set('vulnerabilityRate', -1);
                $instanceRisk->set('threatRate', -1);
                $instanceRisk->set('kindOfMeasure', 0);
                $instanceRisk->set('reductionAmount', 0);
                $instanceRisk->set('cacheMaxRisk', -1);
                $instanceRisk->set('cacheTargetedRisk', -1);
                $instanceRisk->set('comment', '');
                $instanceRisk->set('commentAfter', '');
                $instanceRisk->set('mh', 1);
            }
            if (!$withControls) {
                $instanceRisk->set('comment', '');
                $instanceRisk->set('commentAfter', '');
            }

            $instanceRisk->set('riskC', -1);
            $instanceRisk->set('riskI', -1);
            $instanceRisk->set('riskD', -1);
            $return['risks'][$instanceRisk->getId()] = $instanceRisk->getJsonArray($instanceRiskArray);

            $irAmv = $instanceRisk->get('amv');
            $return['risks'][$instanceRisk->getId()]['amv'] = is_null($irAmv) ? null : $irAmv->getUuid();
            if (!empty($return['risks'][$instanceRisk->getId()]['amv'])
                && empty($return['amvs'][$instanceRisk->getAmv()->getUuid()])
            ) {
                [$amv, $threats, $vulns, $themes, $measures] = $this->amvExportService->generateExportArray(
                    $instanceRisk->getAmv(),
                    $instanceRisk->getAnr() !== null ? $instanceRisk->getAnr()->getId() : null,
                    $withEval
                );
                $return['amvs'][$instanceRisk->getAmv()->getUuid()] = $amv;
                if (empty($return['threats'])) {
                    $return['threats'] = [];
                }
                if (empty($return['vuls'])) {
                    $return['vuls'] = [];
                }
                if (empty($return['measures'])) {
                    $return['measures'] = [];
                }
                $return['threats'] += $threats;
                $return['vuls'] += $vulns;
                $return['measures'] += $measures;
            }

            $threat = $instanceRisk->getThreat();
            if (!empty($threat)) {
                if (empty($return['threats'][$threat->getUuid()])) {
                    // TODO: we can't do getJsonArray anymore.
                    $return['threats'][$instanceRisk->getThreat()->getUuid()] =
                        $instanceRisk->get('threat')->getJsonArray($treatsObj);
                }
                $return['risks'][$instanceRisk->getId()]['threat'] = $instanceRisk->getThreat()->getUuid();
            } else {
                $return['risks'][$instanceRisk->getId()]['threat'] = null;
            }

            $vulnerability = $instanceRisk->get('vulnerability');
            if (!empty($vulnerability)) {
                // TODO: we can't do getJsonArray anymore.
                if (empty($return['vuls'][$instanceRisk->getVulnerability()->getUuid()])) {
                    $return['vuls'][$instanceRisk->getVulnerability()->getUuid()] =
                        $instanceRisk->get('vulnerability')->getJsonArray($vulsObj);
                }
                $return['risks'][$instanceRisk->getId()]['vulnerability'] =
                    $instanceRisk->getVulnerability()->getUuid();
            } else {
                $return['risks'][$instanceRisk->getId()]['vulnerability'] = null;
            }

            $return['risks'][$instanceRisk->getId()]['context'] = $instanceRisk->getContext();
            $return['risks'][$instanceRisk->getId()]['riskOwner'] = $instanceRisk->getInstanceRiskOwner()
                ? $instanceRisk->getInstanceRiskOwner()->getName()
                : '';
        }

        // Operational instance risks.
        $return['risksop'] = $this->generateExportArrayOfOperationalInstanceRisks($instance, $withEval, $withControls);

        $return = array_merge($return, $this->generateExportArrayOfRecommendations(
            $instance,
            $withEval,
            $withRecommendations,
            $withUnlinkedRecommendations,
            $riskIds,
            !empty($return['risksop']) ? array_keys($return['risksop']) : []
        ));

        // Instance consequence
        if ($withEval) {
            $instanceConseqArray = [
                'id' => 'id',
                'isHidden' => 'isHidden',
                'c' => 'c',
                'i' => 'i',
                'd' => 'd',
            ];
            $scaleTypeArray = [
                'id' => 'id',
                'label1' => 'label1',
                'label2' => 'label2',
                'label3' => 'label3',
                'label4' => 'label4',
                'isSys' => 'isSys',
                'isHidden' => 'isHidden',
                'type' => 'type',
                'position' => 'position',
            ];
            $return['consequences'] = [];
            $instanceConseqTable = $this->instanceConsequenceService->get('table');
            $instanceConseqResults = $instanceConseqTable->getRepository()
                ->createQueryBuilder('t')
                ->where("t.instance = :i")
                ->setParameter(':i', $instance->getId())->getQuery()->getResult();
            foreach ($instanceConseqResults as $ic) {
                $return['consequences'][$ic->getId()] = $ic->getJsonArray($instanceConseqArray);
                $return['consequences'][$ic->getId()]['scaleImpactType'] =
                    $ic->get('scaleImpactType')->getJsonArray($scaleTypeArray);
                $return['consequences'][$ic->getId()]['scaleImpactType']['scale'] =
                    $ic->get('scaleImpactType')->getScale()->getId();
            }
        }

        /** @var Instance[] $childrenInstances */
        $childrenInstances = $this->instanceTable->getRepository()
            ->createQueryBuilder('t')
            ->where('t.parent = :p')
            ->setParameter(':p', $instance->getId())
            ->orderBy('t.position', 'ASC')->getQuery()->getResult();
        $return['children'] = [];
        $f = '';
        foreach ($childrenInstances as $i) {
            $return['children'][$i->getId()] = $this->generateExportArray(
                $i->getId(),
                $f,
                $withEval,
                false,
                $withControls,
                $withRecommendations,
                $withUnlinkedRecommendations
            );
        }

        return $return;
    }

    protected function generateExportArrayOfOperationalInstanceRisks(
        InstanceSuperClass $instance,
        bool $withEval,
        bool $withControls
    ): array {
        $result = [];

        foreach ($instance->getOperationalInstanceRisks() as $operationalInstanceRisk) {
            $operationalInstanceRiskId = $operationalInstanceRisk->getId();
            $result[$operationalInstanceRiskId] = [
                'id' => $operationalInstanceRiskId,
                'rolfRisk' => $operationalInstanceRisk->getRolfRisk()
                    ? $operationalInstanceRisk->getRolfRisk()->getId()
                    : null,
                'riskCacheLabel1' => $operationalInstanceRisk->getRiskCacheLabel(1),
                'riskCacheLabel2' => $operationalInstanceRisk->getRiskCacheLabel(2),
                'riskCacheLabel3' => $operationalInstanceRisk->getRiskCacheLabel(3),
                'riskCacheLabel4' => $operationalInstanceRisk->getRiskCacheLabel(4),
                'riskCacheDescription1' => $operationalInstanceRisk->getRiskCacheDescription(1),
                'riskCacheDescription2' => $operationalInstanceRisk->getRiskCacheDescription(2),
                'riskCacheDescription3' => $operationalInstanceRisk->getRiskCacheDescription(3),
                'riskCacheDescription4' => $operationalInstanceRisk->getRiskCacheDescription(4),
                'brutProb' => $withEval ? $operationalInstanceRisk->getBrutProb() : -1,
                'netProb' => $withEval ? $operationalInstanceRisk->getNetProb() : -1,
                'targetedProb' => $withEval ? $operationalInstanceRisk->getTargetedProb() : -1,
                'cacheBrutRisk' => $withEval ? $operationalInstanceRisk->getCacheBrutRisk() : -1,
                'cacheNetRisk' => $withEval ? $operationalInstanceRisk->getCacheNetRisk() : -1,
                'cacheTargetedRisk' => $withEval ? $operationalInstanceRisk->getCacheTargetedRisk() : -1,
                'kindOfMeasure' => $withEval
                    ? $operationalInstanceRisk->getKindOfMeasure()
                    : InstanceRiskOp::KIND_NOT_TREATED,
                'comment' => $withEval && $withControls ? $operationalInstanceRisk->getComment() : '',
                'mitigation' => $withEval ? $operationalInstanceRisk->getMitigation() : '',
                'specific' => $operationalInstanceRisk->getSpecific(),
                'context' => $operationalInstanceRisk->getContext(),
                'riskOwner' => $operationalInstanceRisk->getInstanceRiskOwner()
                    ? $operationalInstanceRisk->getInstanceRiskOwner()->getName()
                    : '',
            ];
            $result[$operationalInstanceRiskId]['scalesValues'] = [];
            if ($withEval) {
                foreach ($operationalInstanceRisk->getOperationalInstanceRiskScales() as $instanceRiskScale) {
                    $scaleType = $instanceRiskScale->getOperationalRiskScaleType();
                    $result[$operationalInstanceRiskId]['scalesValues'][$scaleType->getId()] = [
                        'operationalRiskScaleTypeId' => $scaleType->getId(),
                        'netValue' => $instanceRiskScale->getNetValue(),
                        'brutValue' => $instanceRiskScale->getBrutValue(),
                        'targetedValue' => $instanceRiskScale->getTargetedValue(),
                    ];
                }
            }
        }

        return $result;
    }

    private function generateExportArrayOfScales(AnrSuperClass $anr): array
    {
        $result = [];
        /** @var ScaleTable $scaleTable */
        $scaleTable = $this->get('scaleTable');
        $scales = $scaleTable->findByAnr($anr);
        foreach ($scales as $scale) {
            $result[$scale->getType()] = [
                'min' => $scale->getMin(),
                'max' => $scale->getMax(),
                'type' => $scale->getType(),
            ];
        }

        return $result;
    }

    protected function generateExportArrayOfInstanceMetadataFields(Instance $instance): array
    {
        $result = [];
        $anr = $instance->getAnr();
        $translationTable = $this->get('translationTable');
        $language = $this->getAnrLanguageCode($anr);
        $translations = $translationTable->findByAnrTypesAndLanguageIndexedByKey(
            $anr,
            [Translation::INSTANCE_METADATA, Translation::ANR_METADATAS_ON_INSTANCES],
            $language
        );

        foreach ($instance->getInstanceMetadata() as $instanceMetadata) {
            $translationComment = $translations[$instanceMetadata->getCommentTranslationKey()] ?? null;
            $translationLabel = $translations[$instanceMetadata->getAnrInstanceMetadataFields()->getLabelTranslationKey()] ?? null;
            $result[$instanceMetadata->getAnrInstanceMetadataFields()->getId()] = [
                'label' => $translationLabel !== null ? $translationLabel->getValue() : '',
                'id' => $instanceMetadata->getId(),
                'comment' => $translationComment !== null ? $translationComment->getValue() : '',
            ];
        }

        return $result;
    }

    protected function generateExportArrayOfRecommendations(
        Instance $instance,
        bool $withEval,
        bool $withRecommendations,
        bool $withUnlinkedRecommendations,
        array $riskIds,
        array $riskOpIds
    ): array {
        $result = [];

        if ($withEval && $withRecommendations) {
            $result['recSets'] = [];

            $recommendationsSets = $this->recommendationSetTable->findByAnr($instance->getAnr());
            foreach ($recommendationsSets as $recommendationSet) {
                $result['recSets'][$recommendationSet->getUuid()] = [
                    'uuid' => $recommendationSet->getUuid(),
                    'label' => $recommendationSet->getLabel(),
                ];
            }
        }

        $recoIds = [];
        if ($withEval && $withRecommendations && !empty($riskIds)) {
            $recosObj = [
                'uuid' => 'uuid',
                'recommendationSet' => 'recommendationSet',
                'code' => 'code',
                'description' => 'description',
                'importance' => 'importance',
                'comment' => 'comment',
                'status' => 'status',
                'responsable' => 'responsable',
                'duedate' => 'duedate',
                'counterTreated' => 'counterTreated',
            ];
            $result['recos'] = [];
            if (!$withUnlinkedRecommendations) {
                $result['recs'] = [];
            }

            // TODO: can't work...
            /** @var RecommendationRisk[] $recoRisk */
            $recoRisk = $this->recommendationRiskTable->getEntityByFields(
                ['anr' => $instance->getAnr()->getId(), 'instanceRisk' => $riskIds],
                ['id' => 'ASC']
            );
            foreach ($recoRisk as $rr) {
                $recommendation = $rr->getRecommendation();
                if ($recommendation !== null) {
                    $recommendationUuid = $recommendation->getUuid();
                    $instanceRiskId = $rr->getInstanceRisk()->getId();
                    $result['recos'][$instanceRiskId][$recommendationUuid] = $recommendation->getJsonArray($recosObj);
                    $result['recos'][$instanceRiskId][$recommendationUuid]['recommendationSet'] =
                        $recommendation->getRecommendationSet()->getUuid();
                    $result['recos'][$instanceRiskId][$recommendationUuid]['commentAfter'] = $rr->getCommentAfter();
                    if (!$withUnlinkedRecommendations && !isset($recoIds[$recommendationUuid])) {
                        $result['recs'][$recommendationUuid] = $recommendation->getJsonArray($recosObj);
                        $result['recs'][$recommendationUuid]['recommendationSet'] =
                            $recommendation->getRecommendationSet()->getUuid();
                    }
                    $recoIds[$recommendationUuid] = $recommendationUuid;
                }
            }
        }

        if ($withEval && $withRecommendations && !empty($riskOpIds)) {
            $recosObj = [
                'uuid' => 'uuid',
                'recommendationSet' => 'recommendationSet',
                'code' => 'code',
                'description' => 'description',
                'importance' => 'importance',
                'comment' => 'comment',
                'status' => 'status',
                'responsable' => 'responsable',
                'duedate' => 'duedate',
                'counterTreated' => 'counterTreated',
            ];
            $result['recosop'] = [];
            if (!$withUnlinkedRecommendations) {
                $result['recs'] = [];
            }
            $recoRisk = $this->recommendationRiskTable->getEntityByFields(
                ['anr' => $instance->getAnr()->getId(), 'instanceRiskOp' => $riskOpIds],
                ['id' => 'ASC']
            );
            foreach ($recoRisk as $rr) {
                $recommendation = $rr->getRecommendation();
                if ($recommendation !== null) {
                    $instanceRiskOpId = $rr->getInstanceRiskOp()->getId();
                    $recommendationUuid = $recommendation->getUuid();
                    $result['recosop'][$instanceRiskOpId][$recommendationUuid] =
                        $recommendation->getJsonArray($recosObj);
                    $result['recosop'][$instanceRiskOpId][$recommendationUuid]['recommendationSet'] =
                        $recommendation->getRecommendationSet()->getUuid();
                    $result['recosop'][$instanceRiskOpId][$recommendationUuid]['commentAfter'] =
                        $rr->getCommentAfter();
                    if (!$withUnlinkedRecommendations && !isset($recoIds[$recommendationUuid])) {
                        $result['recs'][$recommendationUuid] = $recommendation->getJsonArray($recosObj);
                        $result['recs'][$recommendationUuid]['recommendationSet'] =
                            $recommendation->getRecommendationSet()->getUuid();
                    }
                    $recoIds[$recommendationUuid] = $recommendationUuid;
                }
            }
        }

        // Recommendation unlinked to recommendations-risks
        if ($withUnlinkedRecommendations && $withEval && $withRecommendations) {
            $recosObj = [
                'uuid' => 'uuid',
                'recommendationSet' => 'recommendationSet',
                'code' => 'code',
                'description' => 'description',
                'importance' => 'importance',
                'comment' => 'comment',
                'status' => 'status',
                'responsable' => 'responsable',
                'duedate' => 'duedate',
                'counterTreated' => 'counterTreated',
            ];
            $result['recs'] = [];
            $recommendations = $this->recommendationTable->findByAnr($instance->getAnr());
            foreach ($recommendations as $recommendation) {
                if (!isset($recoIds[$recommendation->getUuid()])) {
                    $result['recs'][$recommendation->getUuid()] = $recommendation->getJsonArray($recosObj);
                    $result['recs'][$recommendation->getUuid()]['recommendationSet'] =
                        $recommendation->getRecommendationSet()->getUuid();
                    $recoIds[$recommendation->getUuid()] = $recommendation->getUuid();
                }
            }
        }

        return $result;
    }
}