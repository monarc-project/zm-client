<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Service\AbstractService;
use Monarc\Core\Service\TranslateService;
use Monarc\FrontOffice\Model\Entity\Instance;
use Monarc\FrontOffice\Model\Entity\InstanceRisk;
use Monarc\FrontOffice\Model\Entity\MonarcObject;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskTable;
use Monarc\FrontOffice\Model\Table\InstanceTable;
use Monarc\FrontOffice\Model\Table\RecommandationTable;
use Monarc\FrontOffice\Model\Table\UserAnrTable;
use Monarc\FrontOffice\Service\Traits\RecommendationsPositionsUpdateTrait;

/**
 * This class is the service that handles risks within an ANR.
 * @package Monarc\FrontOffice\Service
 */
class AnrRiskService extends AbstractService
{
    use RecommendationsPositionsUpdateTrait;

    protected $filterColumns = [];
    protected $dependencies = ['anr', 'vulnerability', 'threat', 'instance', 'asset'];
    protected $anrTable;
    protected $userAnrTable;
    protected $instanceTable;
    protected $instanceRiskTable;
    protected $threatTable;
    protected $vulnerabilityTable;
    protected $translateService;

    /** @var RecommandationTable */
    protected $recommandationTable;

    public function getRisks($anrId, $instanceId = null, $params = []): array
    {
        /** @var AnrTable $anrTable */
        $anrTable = $this->get('anrTable');
        $anr = $anrTable->findById($anrId);

        $instance = null;
        if ($instanceId !== null) {
            /** @var InstanceTable $instanceTable */
            $instanceTable = $this->get('instanceTable');
            $instance = $instanceTable->findById($instanceId);

            if ($instance->getAnr()->getId() !== $anrId) {
                throw new Exception('Anr ID and instance anr ID are different', 412);
            }
        }

        /** @var InstanceRiskTable $instanceRiskTable */
        $instanceRiskTable = $this->get('table');

        // TODO: drop the context and pass the objects instead of IDs!
        return $instanceRiskTable
            ->getFilteredInstancesRisks($anrId, $instanceId, $params, \Monarc\Core\Model\Entity\AbstractEntity::FRONT_OFFICE);
    }

    public function getCsvRisks($anrId, $instanceId = null, $params = [])
    {
        /** @var AnrTable $anrTable */
        $anrTable = $this->get('anrTable');
        $anr = $anrTable->findById($anrId);
        $anrLanguage = $anr->getLanguage();

        /** @var TranslateService $translateService */
        $translateService = $this->get('translateService');

        // Fill in the header
        $output = implode(',', [
            $translateService->translate('Asset', $anrLanguage),
            $translateService->translate('C Impact', $anrLanguage),
            $translateService->translate('I Impact', $anrLanguage),
            $translateService->translate('A Impact', $anrLanguage),
            $translateService->translate('Threat', $anrLanguage),
            $translateService->translate('Prob.', $anrLanguage),
            $translateService->translate('Vulnerability', $anrLanguage),
            $translateService->translate('Existing controls', $anrLanguage),
            $translateService->translate('Qualif.', $anrLanguage),
            $translateService->translate('Current risk', $anrLanguage). " C",
            $translateService->translate('Current risk', $anrLanguage) . " I",
            $translateService->translate('Current risk', $anrLanguage) . " "
                . $translateService->translate('A', $anrLanguage),
            $translateService->translate('Treatment', $anrLanguage),
            $translateService->translate('Residual risk', $anrLanguage),
            $translateService->translate('Risk owner', $anrLanguage),
            $translateService->translate('Risk context', $anrLanguage),
            $translateService->translate('Recommendations', $anrLanguage),
            $translateService->translate('Security referentials', $anrLanguage),
        ]) . "\n";

        // TODO: fetch objects list instead of array of values.
        $instanceRisks = $this->getRisks($anrId, $instanceId, $params);

        // Fill in the content
        foreach ($instanceRisks as $instanceRisk) {
            $values = [
                $instanceRisk['instanceName' . $anrLanguage],
                $instanceRisk['c_impact'] === -1 ? null : $instanceRisk['c_impact'],
                $instanceRisk['i_impact'] === -1 ? null : $instanceRisk['i_impact'],
                $instanceRisk['d_impact'] === -1 ? null : $instanceRisk['d_impact'],
                $instanceRisk['threatLabel' . $anrLanguage],
                $instanceRisk['threatRate'] === -1 ? null : $instanceRisk['threatRate'],
                $instanceRisk['vulnLabel' . $anrLanguage],
                $instanceRisk['comment'],
                $instanceRisk['vulnerabilityRate'] === -1 ? null : $instanceRisk['vulnerabilityRate'],
                $instanceRisk['c_risk_enabled'] === 0 || $instanceRisk['c_risk'] === -1
                    ? null
                    : $instanceRisk['c_risk'],
                $instanceRisk['i_risk_enabled'] === 0  || $instanceRisk['i_risk'] === -1
                    ? null
                    : $instanceRisk['i_risk'],
                $instanceRisk['d_risk_enabled'] === 0  || $instanceRisk['d_risk'] === -1
                    ? null
                    : $instanceRisk['d_risk'],
                $translateService->translate(
                    InstanceRisk::getAvailableMeasureTypes()[$instanceRisk['kindOfMeasure']],
                    $anrLanguage
                ),
                $instanceRisk['target_risk'] === -1 ? null : $instanceRisk['target_risk'],
                $instanceRisk['owner'],
                $instanceRisk['context'],
                $this->getCsvRecommendations($anr, (string)$instanceRisk['recommendations']),
                $this->getCsvMeasures($anrLanguage, $instanceRisk['id'])
            ];

            $output .= '"';
            $search = ['"', "\n"];
            $replace = ["'", ' '];
            $output .= implode('","', str_replace($search, $replace, $values));
            $output .= "\"\r\n";
        }

        return $output;
    }

    /**
     * @inheritdoc
     */
    public function create($data, $last = true)
    {
        $data['specific'] = 1;

        // Check that we don't already have a risk with this vuln/threat/instance combo
        $entity = $this->getList(0, 1, null, null, [
            'anr' => $data['anr'],
            'th.anr' => $data['anr'],
            'v.anr' => $data['anr'],
            'v.uuid' => $data['vulnerability']['uuid'],
            'th.uuid' => $data['threat']['uuid'],
            'i.id' => $data['instance']
        ]);
        if (!empty($entity)) {
            throw new Exception("This risk already exists in this instance", 412);
        }

        $class = $this->get('entity');
        /** @var InstanceRisk $entity */
        $entity = new $class();
        $entity->setLanguage($this->getLanguage());
        $entity->setDbAdapter($this->get('table')->getDb());

        //retrieve asset
        /** @var InstanceTable $instanceTable */
        $instanceTable = $this->get('instanceTable');
        $instance = $instanceTable->getEntity($data['instance']);
        $data['asset'] = ['uuid' => $instance->getAsset()->getUuid(), 'anr' => $data['anr']];
        $entity->exchangeArray($data);
        $dependencies = (property_exists($this, 'dependencies')) ? $this->dependencies : [];
        $this->setDependencies($entity, $dependencies);

        /** @var AnrTable $table */
        $table = $this->get('table');
        $id = $table->save($entity, $last);

        //if global object, save risk of all instance of global object for this anr
        if ($entity->getInstance()->getObject()->getScope() === MonarcObject::SCOPE_GLOBAL) {
            $brothers = $instanceTable->getEntityByFields([
                'anr' => $entity->getAnr()->getId(),
                'object' => [
                    'anr' => $entity->getAnr()->getId(),
                    'uuid' => $entity->getInstance()->getObject()->getUuid()
                ],
                'id' => [
                    'op' => '!=',
                    'value' => $instance->id,
                ]
            ]);
            $i = 1;
            $nbBrothers = count($brothers);
            foreach ($brothers as $brother) {
                $newRisk = clone $entity;
                $newRisk->instance = $brother;
                $table->save($newRisk, ($i == $nbBrothers));
                $i++;
            }
        }

        return $id;
    }

    /**
     * @inheritdoc
     */
    public function deleteFromAnr($id, $anrId = null)
    {
        /** @var InstanceRiskTable $instanceRiskTable */
        $instanceRiskTable = $this->get('table');
        /** @var InstanceRisk $instanceRisk */
        $instanceRisk = $instanceRiskTable->findById($id);

        if (!$instanceRisk->isSpecific()) {
            throw new Exception('You can not delete a not specific risk', 412);
        }

        if ($instanceRisk->getAnr()->getId() !== $anrId) {
            throw new Exception('Anr id error', 412);
        }

        /** @var UserAnrTable $userAnrTable */
        $userAnrTable = $this->get('userAnrTable');
        $userAnr = $userAnrTable->findByAnrAndUser($instanceRisk->getAnr(), $instanceRiskTable->getConnectedUser());
        if ($userAnr === null || $userAnr->getRwd() === 0) {
            throw new Exception('You are not authorized to do this action', 412);
        }

        // If the object is global, delete all risks link to brothers instances
        if ($instanceRisk->getInstance()->getObject()->isScopeGlobal()) {
            // Retrieve brothers
            /** @var InstanceTable $instanceTable */
            $instanceTable = $this->get('instanceTable');
            /** @var Instance[] $brothers */
            $brothers = $instanceTable->getEntityByFields([
                'anr' => $instanceRisk->getAnr()->getId(),
                'object' => [
                    'anr' => $instanceRisk->getAnr()->getId(),
                    'uuid' => $instanceRisk->getInstance()->getObject()->getUuid(),
                ],
            ]);

            // Retrieve instances with same risk
            $instancesRisks = $instanceRiskTable->getEntityByFields([
                'asset' => [
                    'uuid' => $instanceRisk->getAsset()->getUuid(),
                    'anr' => $instanceRisk->getAnr()->getId(),
                ],
                'threat' => [
                    'uuid' => $instanceRisk->getThreat()->getUuid(),
                    'anr' => $instanceRisk->getAnr()->getId(),
                ],
                'vulnerability' => [
                    'uuid' => $instanceRisk->getVulnerability()->getUuid(),
                    'anr' => $instanceRisk->getAnr()->getId(),
                ],
            ]);

            foreach ($instancesRisks as $instanceRisk) {
                foreach ($brothers as $brother) {
                    if ($brother->getId() === $instanceRisk->getInstance()->getId() && $instanceRisk->getId() !== $id) {
                        $instanceRiskTable->deleteEntity($instanceRisk, false);
                    }
                }
            }
        }

        $instanceRiskTable->deleteEntity($instanceRisk);

        $this->processRemovedInstanceRiskRecommendationsPositions($instanceRisk);

        return true;
    }

    protected function getCsvRecommendations(AnrSuperClass $anr, string $recsUuidsString): string
    {
        $recsUuidsArray = explode(",", $recsUuidsString);
        $recommendationsUuidsNumber = \count($recsUuidsArray);
        $csvString = '';
        foreach ($recsUuidsArray as $index => $recUuid) {
            if (!empty($recUuid)) {
                $recommendation = $this->recommandationTable->findByAnrAndUuid($anr, $recUuid);
                $csvString .= $recommendation->getCode() . " - " . $recommendation->getDescription();
                if ($index !== $recommendationsUuidsNumber - 1) {
                    $csvString .= "\r";
                }
            }
        }

        return $csvString;
    }

    protected function getCsvMeasures(int $anrLanguage, int $instanceRiksId): string
    {
        /** @var InstanceRiskTable $instanceRiskTable */
        $instanceRiskTable = $this->get('table');
        $instanceRisk = $instanceRiskTable->findById($instanceRiksId);
        $measures = $instanceRisk->getAmv()->getMeasures();
        $measuresNumber = \count($measures);
        $csvString = '';

        foreach ($measures as $index => $measure) {
            $csvString .= "[" . $measure->getReferential()->{'getLabel' . $anrLanguage}() . "] " .
               $measure->getCode() . " - " .
               $measure->{'getLabel' . $anrLanguage}();
            if ($index !== $measuresNumber - 1) {
                $csvString .= "\r";
            }
        }

        return $csvString;
    }
}
