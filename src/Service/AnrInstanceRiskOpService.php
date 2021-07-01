<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Service;

use Doctrine\ORM\EntityNotFoundException;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\Core\Service\ConfigService;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\TranslateService;
use Monarc\FrontOffice\Model\Entity\Instance;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOp;
use Monarc\FrontOffice\Model\Entity\OperationalInstanceRiskScale;
use Monarc\FrontOffice\Model\Entity\OperationalRiskScale;
use Monarc\FrontOffice\Model\Entity\OperationalRiskScaleComment;
use Monarc\FrontOffice\Model\Entity\RolfRisk;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskOpTable;
use Monarc\FrontOffice\Model\Table\InstanceTable;
use Monarc\FrontOffice\Model\Table\OperationalInstanceRiskScaleTable;
use Monarc\FrontOffice\Model\Table\OperationalRiskScaleTable;
use Monarc\FrontOffice\Model\Table\RolfRiskTable;
use Monarc\FrontOffice\Model\Table\TranslationTable;
use Monarc\FrontOffice\Service\Traits\RecommendationsPositionsUpdateTrait;

class AnrInstanceRiskOpService
{
    use RecommendationsPositionsUpdateTrait;

    private AnrTable $anrTable;

    private InstanceTable $instanceTable;

    private InstanceRiskOpTable $instanceRiskOpTable;

    private OperationalInstanceRiskScaleTable $operationalInstanceRiskScaleTable;

    private TranslationTable $translationTable;

    private RolfRiskTable $rolfRiskTable;

    private UserSuperClass $connectedUser;

    private OperationalRiskScaleTable $operationalRiskScaleTable;

    private ConfigService $configService;

    private TranslateService$translateService;

    public function __construct(
        AnrTable $anrTable,
        InstanceTable $instanceTable,
        InstanceRiskOpTable $instanceRiskOpTable,
        RolfRiskTable $rolfRiskTable,
        ConnectedUserService $connectedUserService,
        OperationalInstanceRiskScaleTable $operationalInstanceRiskScaleTable,
        OperationalRiskScaleTable $operationalRiskScaleTable,
        TranslationTable $translationTable,
        ConfigService $configService,
        TranslateService $translateService
    ) {
        $this->anrTable = $anrTable;
        $this->instanceTable = $instanceTable;
        $this->instanceRiskOpTable = $instanceRiskOpTable;
        $this->rolfRiskTable = $rolfRiskTable;
        $this->connectedUser = $connectedUserService->getConnectedUser();
        $this->operationalInstanceRiskScaleTable = $operationalInstanceRiskScaleTable;
        $this->operationalRiskScaleTable = $operationalRiskScaleTable;
        $this->translationTable = $translationTable;
        $this->configService = $configService;
        $this->translateService = $translateService;
    }

    public function createSpecificRiskOp(array $data): int
    {
        $instance = $this->instanceTable->findById((int)$data['instance']);
        $anr = $instance->getAnr();

        if ((int)$data['source'] === 2) {
            $rolfRisk = (new RolfRisk())
                ->setAnr($anr)
                ->setCode((string)$data['code'])
                ->setLabels(['label' . $anr->getLanguage() => $data['label']])
                ->setDescriptions(['description' . $anr->getLanguage() => $data['description'] ?? ''])
                ->setCreator($this->connectedUser->getFirstname() . ' ' . $this->connectedUser->getLastname());
            $this->rolfRiskTable->saveEntity($rolfRisk, true);
        } else {
            $rolfRisk = $this->rolfRiskTable->findById((int)$data['risk']);
            $operationalInstanceRisk = $this->instanceRiskOpTable->findByAnrInstanceAndRolfRisk(
                $anr,
                $instance,
                $rolfRisk
            );
            if ($operationalInstanceRisk !== null) {
                throw new Exception("This risk already exists in this instance", 412);
            }
        }

        $operationalInstanceRisk = (new InstanceRiskOp())
            ->setAnr($anr)
            ->setRolfRisk($rolfRisk)
            ->setInstance($instance)
            ->setObject($instance->getObject())
            ->setSpecific(1)
            ->setRiskCacheCode($rolfRisk->getCode())
            ->setRiskCacheLabels([
                'riskCacheLabel1' => $rolfRisk->getLabel(1),
                'riskCacheLabel2' => $rolfRisk->getLabel(2),
                'riskCacheLabel3' => $rolfRisk->getLabel(3),
                'riskCacheLabel4' => $rolfRisk->getLabel(4),
            ])
            ->setRiskCacheDescriptions([
                'riskCacheDescription1' => $rolfRisk->getDescription(1),
                'riskCacheDescription2' => $rolfRisk->getDescription(2),
                'riskCacheDescription3' => $rolfRisk->getDescription(3),
                'riskCacheDescription4' => $rolfRisk->getDescription(4),
            ])
            ->setCreator($this->connectedUser->getFirstname() . ' ' . $this->connectedUser->getLastname());

        $this->instanceRiskOpTable->saveEntity($operationalInstanceRisk, false);

        $operationalRiskScales = $this->operationalRiskScaleTable->findByAnr($instance->getAnr());
        foreach ($operationalRiskScales as $operationalRiskScale) {
            $operationalInstanceRiskScale = (new OperationalInstanceRiskScale())
                ->setAnr($anr)
                ->setOperationalInstanceRisk($operationalInstanceRisk)
                ->setOperationalRiskScale($operationalRiskScale)
                ->setCreator($this->connectedUser->getEmail());
            $this->operationalInstanceRiskScaleTable->save($operationalInstanceRiskScale, false);
        }

        $this->instanceRiskOpTable->getDb()->flush();

        return $operationalInstanceRisk->getId();
    }

    /**
     * @throws EntityNotFoundException
     */
    public function update(int $id, array $data)
    {
        // TODO: implement Permissions validator and inject it here. similar to \Monarc\Core\Service\AbstractService::deleteFromAnr

        $operationalInstanceRisk = $this->instanceRiskOpTable->findById($id);
//        $operationalInstanceRisk->set

        /** @var InstanceRiskOp $instanceRiskOp */
        $instanceRiskOp = $this->instanceRiskOpTable->findById($id);

        $this->updateInstanceRiskRecommendationsPositions($instanceRiskOp);

        return $operationalInstanceRisk->getId();
    }

    public function getOperationalRisks(int $anrId, int $instanceId = null, array $params = [])
    {
        $instancesIds = [];
        if ($instanceId !== null) {
            $instance = $this->instanceTable->findById($instanceId);
            $this->instanceTable->initTree($instance);
            $instancesIds = $this->extractInstancesAndTheirChildrenIds([$instance->getId() => $instance]);
        }

        $anr = $this->anrTable->findById($anrId);
        $anrLanguage = $anr->getLanguage();

        $operationalInstanceRisks = $this->instanceRiskOpTable->findByAnrInstancesAndFilterParams(
            $anr,
            $instancesIds,
            $params
        );

        $result = [];
        $operationalRisksScalesTranslations = $this->translationTable->findByAnrTypesAndLanguageIndexedByKey(
            $anr,
            [OperationalRiskScale::class, OperationalRiskScaleComment::class],
            strtolower($this->configService->getLanguageCodes()[$anrLanguage])
        );
        foreach ($operationalInstanceRisks as $operationalInstanceRisk) {
            $recommendationUuids = [];
            foreach ($operationalInstanceRisk->getRecommendationRisks() as $recommendationRisk) {
                $recommendationUuids[] = $recommendationRisk->getRecommandation()->getUuid();
            }

            $scalesData = [];
            foreach ($operationalInstanceRisk->getOperationalInstanceRiskScales() as $operationalInstanceRiskScale) {
                $scalesData[] = [
                    'id' => $operationalInstanceRiskScale->getId(),
                    'label' => $operationalRisksScalesTranslations[
                        $operationalInstanceRiskScale->getOperationalRiskScale()->getLabelTranslationKey()
                    ]->getValue(),
                    'values' => [
                        'net' => $operationalInstanceRiskScale->getNetValue(),
                        'brut' => $operationalInstanceRiskScale->getBrutValue(),
                        'target' => $operationalInstanceRiskScale->getTargetedValue(),
                    ],
                ];
            }

            $result[] = [
                'id' => $operationalInstanceRisk->getId(),
                'rolfRisk' => $operationalInstanceRisk->getRolfRisk()->getId(),
                'label1' => $operationalInstanceRisk->getRiskCacheLabel(1),
                'label2' => $operationalInstanceRisk->getRiskCacheLabel(2),
                'label3' => $operationalInstanceRisk->getRiskCacheLabel(3),
                'label4' => $operationalInstanceRisk->getRiskCacheLabel(4),
                'description1' => $operationalInstanceRisk->getRiskCacheDescription(1),
                'description2' => $operationalInstanceRisk->getRiskCacheDescription(2),
                'description3' => $operationalInstanceRisk->getRiskCacheDescription(3),
                'description4' => $operationalInstanceRisk->getRiskCacheDescription(4),
                'netProb' => $operationalInstanceRisk->getNetProb(),
                'brutProb' => $operationalInstanceRisk->getBrutProb(),
                'targetProb' => $operationalInstanceRisk->getTargetedProb(),
                'scales' => $scalesData,
                'cacheNetRisk' => $operationalInstanceRisk->getCacheNetRisk(),
                'cacheBrutRisk' => $operationalInstanceRisk->getCacheBrutRisk(),
                'cacheTargetRisk' => $operationalInstanceRisk->getCacheTargetedRisk(),
                'kindOfMeasure' => $operationalInstanceRisk->getKindOfMeasure(),
                'comment' => $operationalInstanceRisk->getComment(),
                'specific' => $operationalInstanceRisk->getSpecific(),
                't' => $operationalInstanceRisk->getKindOfMeasure() === InstanceRiskOp::KIND_NOT_TREATED ? 0 : 1,
                'position' => $operationalInstanceRisk->getInstance()->getPosition(),
                'instanceInfos' => [
                    'id' => $operationalInstanceRisk->getInstance()->getId(),
                    'scope' => $operationalInstanceRisk->getInstance()->getObject()->getScope(),
                    'name' . $anrLanguage => $operationalInstanceRisk->getInstance()->{'getName' . $anrLanguage}(),
                ],
                'recommendations' => implode(',', $recommendationUuids),
            ];
        }

        return $result;
    }

    public function getOperationalRisksInCsv(int $anrId, int $instance = null, array $params = []): string
    {
        $operationalRisks = $this->getOperationalRisks($anrId, $instance, $params);
        $anr = $this->anrTable->findById($anrId);
        $lang = $anr->getLanguage();

        $output = '';
        if (!empty($operationalRisks)) {
            $fields_1 = [
                'instanceInfos' => $this->translateService->translate('Asset', $lang),
                'label' . $lang => $this->translateService->translate('Risk description', $lang),
            ];
            if ($anr->getShowRolfBrut() === 1) {
                $fields_2 = [
                    'brutProb' => $this->translateService->translate('Prob.', $lang)
                        . "(" . $this->translateService->translate('Inherent risk', $lang) . ")",
                    'brutR' => 'R' . " (" . $this->translateService->translate('Inherent risk', $lang) . ")",
                    'brutO' => 'O' . " (" . $this->translateService->translate('Inherent risk', $lang) . ")",
                    'brutL' => 'L' . " (" . $this->translateService->translate('Inherent risk', $lang) . ")",
                    'brutF' => 'F' . " (" . $this->translateService->translate('Inherent risk', $lang) . ")",
                    'brutF' => 'P' . " (" . $this->translateService->translate('Inherent risk', $lang) . ")",
                    'cacheBrutRisk' => $this->translateService->translate('Inherent risk', $lang),
                ];
            } else {
                $fields_2 = [];
            }
            $fields_3 = [
                'netProb' => $this->translateService->translate('Prob.', $lang) . "("
                    . $this->translateService->translate('Net risk', $lang) . ")",
                'netR' => 'R' . " (" . $this->translateService->translate('Net risk', $lang) . ")",
                'netO' => 'O' . " (" . $this->translateService->translate('Net risk', $lang) . ")",
                'netL' => 'L' . " (" . $this->translateService->translate('Net risk', $lang) . ")",
                'netF' => 'F' . " (" . $this->translateService->translate('Net risk', $lang) . ")",
                'netF' => 'P' . " (" . $this->translateService->translate('Net risk', $lang) . ")",
                'cacheNetRisk' => $this->translateService->translate('Current risk', $lang) . " ("
                    . $this->translateService->translate('Net risk', $lang) . ")",
                'comment' => $this->translateService->translate('Existing controls', $lang),
                'kindOfMeasure' => $this->translateService->translate('Treatment', $lang),
                'cacheTargetedRisk' => $this->translateService->translate('Residual risk', $lang),
            ];
            $fields = $fields_1 + $fields_2 + $fields_3;

            // Fill in the headers
            $output .= implode(',', array_values($fields)) . "\n";
            foreach ($operationalRisks as $risk) {
                foreach ($fields as $k => $v) {
                    if ($k === 'kindOfMeasure') {
                        switch ($risk[$k]) {
                            case 1:
                                $arrayValues[] = 'Reduction';
                                break;
                            case 2:
                                $arrayValues[] = 'Denied';
                                break;
                            case 3:
                                $arrayValues[] = 'Accepted';
                                break;
                            default:
                                $arrayValues[] = 'Not treated';
                        }
                    } elseif ($k === 'instanceInfos') {
                        $arrayValues[] = $risk[$k]['name' . $lang];
                    } elseif ($risk[$k] === -1) {
                        $arrayValues[] = null;
                    } else {
                        $arrayValues[] = $risk[$k];
                    }
                }
                $output .= '"';
                $search = ['"', "\n"];
                $replace = ["'", ' '];
                $output .= implode('","', str_replace($search, $replace, $arrayValues));
                $output .= "\"\r\n";
                $arrayValues = null;
            }
        }

        return $output;
    }

    public function deleteFromAnr(int $id, int $anrId): void
    {
        $operationalInstanceRisk = $this->instanceRiskOpTable->findById($id);
        if (!$operationalInstanceRisk->isSpecific()) {
            throw new Exception('Only specific risks can be deleted.', 412);
        }

        // TODO: implement Permissions validator and inject it here. similar to \Monarc\Core\Service\AbstractService::deleteFromAnr

        $this->instanceRiskOpTable->deleteEntity($operationalInstanceRisk);

        $this->processRemovedInstanceRiskRecommendationsPositions($operationalInstanceRisk);
    }

    /**
     * @param Instance[] $instances
     *
     * @return array
     */
    private function extractInstancesAndTheirChildrenIds(array $instances): array
    {
        $instancesIds = [];
        $childInstancesIds = [];
        foreach ($instances as $instanceId => $instance) {
            $instancesIds[] = $instanceId;
            $childInstancesIds = $this->extractInstancesAndTheirChildrenIds($instance->getParameterValues('children'));
        }

        return array_merge($instancesIds, $childInstancesIds);
    }
}
