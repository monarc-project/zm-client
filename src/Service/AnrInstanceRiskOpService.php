<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Service;

use Doctrine\ORM\EntityNotFoundException;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\Instance;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOp;
use Monarc\FrontOffice\Model\Entity\OperationalInstanceRiskScale;
use Monarc\FrontOffice\Model\Entity\RolfRisk;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskOpTable;
use Monarc\FrontOffice\Model\Table\InstanceTable;
use Monarc\FrontOffice\Model\Table\OperationalInstanceRiskScaleTable;
use Monarc\FrontOffice\Model\Table\OperationalRiskScaleTable;
use Monarc\FrontOffice\Model\Table\RolfRiskTable;
use Monarc\FrontOffice\Model\Table\RolfTagTable;
use Monarc\FrontOffice\Model\Table\TranslationTable;
use Monarc\FrontOffice\Service\Traits\RecommendationsPositionsUpdateTrait;

class AnrInstanceRiskOpService
{
    use RecommendationsPositionsUpdateTrait;

    private AnrTable $anrTable;

    private InstanceTable $instanceTable;

    private InstanceRiskOpTable $instanceRiskOpTable;

    private OperationalInstanceRiskScaleTable $operationalInstanceRiskScaleTable;

    private RolfTagTable $rolfTagTable;

    private TranslationTable $translationTable;

    private RolfRiskTable $rolfRiskTable;

    private UserSuperClass $connectedUser;

    private OperationalRiskScaleTable $operationalRiskScaleTable;

    public function __construct(
        AnrTable $anrTable,
        InstanceTable $instanceTable,
        InstanceRiskOpTable $instanceRiskOpTable,
        RolfRiskTable $rolfRiskTable,
        ConnectedUserService $connectedUserService,
        OperationalInstanceRiskScaleTable $operationalInstanceRiskScaleTable,
        OperationalRiskScaleTable $operationalRiskScaleTable,

        RolfTagTable $rolfTagTable,
        TranslationTable $translationTable
    ) {
        // TODO: do we need the parent Core class usage/injection, maybe it's not used, also params not needed then
        $this->anrTable = $anrTable;
        $this->instanceTable = $instanceTable;
        $this->instanceRiskOpTable = $instanceRiskOpTable;
        $this->rolfRiskTable = $rolfRiskTable;
        $this->connectedUser = $connectedUserService->getConnectedUser();
        $this->operationalInstanceRiskScaleTable = $operationalInstanceRiskScaleTable;
        $this->operationalRiskScaleTable = $operationalRiskScaleTable;

        $this->rolfTagTable = $rolfTagTable;
        $this->translationTable = $translationTable;
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

        $operationalInstanceRisks = $this->instanceRiskOpTable->findByAnrInstancesAndFilterParams(
            $anr,
            $instancesIds,
            $params
        );

        $result = [];
        foreach ($operationalInstanceRisks as $operationalInstanceRisk) {
            $result[] = [

            ];


//                $row['instanceInfos'] = [
//                    'id' => $row['iid'],
//                    'scope' => $row['scope'],
//                    'name' . $language => $row['name' . $language],
//                ];
        }

        return $result;
    }

    public function getOperationalRisksInCsv(int $anrId, int $instance = null, array $params = []): string
    {
        $translate = $this->get('translateService');

        // TODO: it was moved out. this method should be as well.
        $risks = $this->getOperationalRisks($anrId, $instance, $params);
        $lang = $this->anrTable->getEntity($anrId)->language;
        $ShowBrut = $this->anrTable->getEntity($anrId)->showRolfBrut;

        $output = '';
        if (count($risks) > 0) {
            $fields_1 = [
                'instanceInfos' => $translate->translate('Asset', $lang),
                'label' . $lang => $translate->translate('Risk description', $lang),
            ];
            if ($ShowBrut == 1) {
                $fields_2 = [
                    'brutProb' => $translate->translate('Prob.', $lang) . "(" . $translate->translate('Inherent risk', $lang) . ")",
                    'brutR' => 'R' . " (" . $translate->translate('Inherent risk', $lang) . ")",
                    'brutO' => 'O' . " (" . $translate->translate('Inherent risk', $lang) . ")",
                    'brutL' => 'L' . " (" . $translate->translate('Inherent risk', $lang) . ")",
                    'brutF' => 'F' . " (" . $translate->translate('Inherent risk', $lang) . ")",
                    'brutF' => 'P' . " (" . $translate->translate('Inherent risk', $lang) . ")",
                    'cacheBrutRisk' => $translate->translate('Inherent risk', $lang),
                ];
            } else {
                $fields_2 = [];
            }
            $fields_3 = [
                'netProb' => $translate->translate('Prob.', $lang) . "(" . $translate->translate('Net risk', $lang) . ")",
                'netR' => 'R' . " (" . $translate->translate('Net risk', $lang) . ")",
                'netO' => 'O' . " (" . $translate->translate('Net risk', $lang) . ")",
                'netL' => 'L' . " (" . $translate->translate('Net risk', $lang) . ")",
                'netF' => 'F' . " (" . $translate->translate('Net risk', $lang) . ")",
                'netF' => 'P' . " (" . $translate->translate('Net risk', $lang) . ")",
                'cacheNetRisk' => $translate->translate('Current risk', $lang) . " (" . $translate->translate('Net risk', $lang) . ")",
                'comment' => $translate->translate('Existing controls', $lang),
                'kindOfMeasure' => $translate->translate('Treatment', $lang),
                'cacheTargetedRisk' => $translate->translate('Residual risk', $lang),
            ];
            $fields = $fields_1 + $fields_2 + $fields_3;

            // Fill in the headers
            $output .= implode(',', array_values($fields)) . "\n";
            foreach ($risks as $risk) {
                foreach ($fields as $k => $v) {
                    if ($k == 'kindOfMeasure') {
                        switch ($risk[$k]) {
                            case 1:
                                $array_values[] = 'Reduction';
                                break;
                            case 2:
                                $array_values[] = 'Denied';
                                break;
                            case 3:
                                $array_values[] = 'Accepted';
                                break;
                            default:
                                $array_values[] = 'Not treated';
                        }
                    } elseif ($k == 'instanceInfos') {
                        $array_values[] = $risk[$k]['name' . $lang];
                    } elseif ($risk[$k] == '-1') {
                        $array_values[] = null;
                    } else {
                        $array_values[] = $risk[$k];
                    }
                }
                $output .= '"';
                $search = ['"', "\n"];
                $replace = ["'", ' '];
                $output .= implode('","', str_replace($search, $replace, $array_values));
                $output .= "\"\r\n";
                $array_values = null;
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
