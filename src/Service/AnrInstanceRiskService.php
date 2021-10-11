<?php

namespace Monarc\FrontOffice\Service;

use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Entity\InstanceRiskOwnerSuperClass;
use Monarc\Core\Model\Entity\InstanceRiskSuperClass;
use Monarc\Core\Service\InstanceRiskService;
use Monarc\Core\Service\TranslateService;
use Monarc\FrontOffice\Model\Entity\Instance;
use Monarc\FrontOffice\Model\Entity\InstanceRisk;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOwner;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskTable;
use Monarc\FrontOffice\Model\Table\InstanceTable;
use Monarc\FrontOffice\Model\Table\RecommandationRiskTable;
use Monarc\FrontOffice\Model\Table\RecommandationTable;
use Monarc\FrontOffice\Model\Table\UserAnrTable;
use Monarc\FrontOffice\Service\Traits\RecommendationsPositionsUpdateTrait;

class AnrInstanceRiskService extends InstanceRiskService
{
    use RecommendationsPositionsUpdateTrait;

    protected RecommandationTable $recommendationTable;

    protected RecommandationRiskTable $recommendationRiskTable;

    protected TranslateService $translateService;

    public function updateFromRiskTable(int $instanceRiskId, array $data)
    {
        /** @var InstanceRiskTable $instanceRiskTable */
        $instanceRiskTable = $this->get('table');
        $instanceRisk = $instanceRiskTable->getEntity($instanceRiskId);

        //security
        $data['specific'] = $instanceRisk->get('specific');

        if ($instanceRisk->threatRate != $data['threatRate']) {
            $data['mh'] = 0;
        }

        return $this->update($instanceRiskId, $data);
    }

    /**
     * @param int $id
     *
     * @return bool
     *
     * @throws EntityNotFoundException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function delete($id)
    {
        /** @var InstanceRiskTable $instanceRiskTable */
        $instanceRiskTable = $this->get('table');
        /** @var InstanceRisk $instanceRisk */
        $instanceRisk = $instanceRiskTable->findById($id);

        $instanceRiskTable->deleteEntity($instanceRisk);

        $this->processRemovedInstanceRiskRecommendationsPositions($instanceRisk);

        return true;
    }

    public function updateRisks(InstanceRiskSuperClass $instanceRisk, bool $last = true): void
    {
        parent::updateRisks($instanceRisk, $last);

        $this->updateInstanceRiskRecommendationsPositions($instanceRisk);
    }

    public function getInstanceRisksInCsv($anrId, $instanceId = null, $params = []): string
    {
        /** @var AnrTable $anrTable */
        $anrTable = $this->get('anrTable');
        $anr = $anrTable->findById($anrId);
        $anrLanguage = $anr->getLanguage();

        // Fill in the header
        $output = implode(',', [
                $this->translateService->translate('Asset', $anrLanguage),
                $this->translateService->translate('C Impact', $anrLanguage),
                $this->translateService->translate('I Impact', $anrLanguage),
                $this->translateService->translate('A Impact', $anrLanguage),
                $this->translateService->translate('Threat', $anrLanguage),
                $this->translateService->translate('Prob.', $anrLanguage),
                $this->translateService->translate('Vulnerability', $anrLanguage),
                $this->translateService->translate('Existing controls', $anrLanguage),
                $this->translateService->translate('Qualif.', $anrLanguage),
                $this->translateService->translate('Current risk', $anrLanguage). " C",
                $this->translateService->translate('Current risk', $anrLanguage) . " I",
                $this->translateService->translate('Current risk', $anrLanguage) . " "
                    . $this->translateService->translate('A', $anrLanguage),
                $this->translateService->translate('Treatment', $anrLanguage),
                $this->translateService->translate('Residual risk', $anrLanguage),
                $this->translateService->translate('Risk owner', $anrLanguage),
                $this->translateService->translate('Risk context', $anrLanguage),
                $this->translateService->translate('Recommendations', $anrLanguage),
                $this->translateService->translate('Security referentials', $anrLanguage),
            ]) . "\n";

        // TODO: fetch objects list instead of array of values.
        $instanceRisks = $this->getInstanceRisks($anrId, $instanceId, $params);

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
                $this->translateService->translate(
                    InstanceRisk::getAvailableMeasureTypes()[$instanceRisk['kindOfMeasure']],
                    $anrLanguage
                ),
                $instanceRisk['target_risk'] === -1 ? null : $instanceRisk['target_risk'],
                $instanceRisk['owner'],
                $instanceRisk['context'],
                $this->getCsvRecommendations($anr, explode(",", (string)$instanceRisk['recommendations'])),
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
     * TODO: review the logic. Moved from AnrRiskService.
     */
    public function createInstanceRisk($data)
    {
        $data['specific'] = 1;

        // Check that we don't already have a risk with this vuln/threat/instance combo
        $instanceRisks = $this->getList(0, 1, null, null, [
            'anr' => $data['anr'],
            'th.anr' => $data['anr'],
            'v.anr' => $data['anr'],
            'v.uuid' => $data['vulnerability']['uuid'],
            'th.uuid' => $data['threat']['uuid'],
            'i.id' => $data['instance']
        ]);
        if (!empty($instanceRisks)) {
            throw new Exception("This risk already exists in this instance", 412);
        }

        $instanceRisk = new InstanceRisk();
        $instanceRisk->setLanguage($this->getLanguage());
        $instanceRisk->setDbAdapter($this->get('table')->getDb());

        //retrieve asset
        /** @var InstanceTable $instanceTable */
        $instanceTable = $this->get('instanceTable');
        $instance = $instanceTable->getEntity($data['instance']);
        $data['asset'] = ['uuid' => $instance->getAsset()->getUuid(), 'anr' => $data['anr']];
        $instanceRisk->exchangeArray($data);
        $dependencies = property_exists($this, 'dependencies') ? $this->dependencies : [];
        $this->setDependencies($instanceRisk, $dependencies);

        /** @var InstanceRiskTable $instanceRiskTable */
        $instanceRiskTable = $this->get('table');
        $instanceRiskTable->saveEntity($instanceRisk);

        //if global object, save risk of all instance of global object for this anr
        if ($instanceRisk->getInstance()->getObject()->isScopeGlobal()) {
            $brothers = $instanceTable->getEntityByFields([
                'anr' => $instanceRisk->getAnr()->getId(),
                'object' => [
                    'anr' => $instanceRisk->getAnr()->getId(),
                    'uuid' => $instanceRisk->getInstance()->getObject()->getUuid()
                ],
                'id' => [
                    'op' => '!=',
                    'value' => $instance->getId(),
                ]
            ]);
            $i = 1;
            $nbBrothers = \count($brothers);
            foreach ($brothers as $brother) {
                $newRisk = clone $instanceRisk;
                $newRisk->setInstance($brother);
                $instanceRiskTable->saveEntity($newRisk, $i === $nbBrothers);
                $i++;
            }
        }

        return $instanceRisk->getId();
    }

    /**
     * TODO: review the logic, probably can be merged with self::delete. Moved from AnrRiskService.
     */
    public function deleteInstanceRisk(int $id, int $anrId)
    {
        /** @var InstanceRiskTable $instanceRiskTable */
        $instanceRiskTable = $this->get('table');
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

    protected function duplicateRecommendationRisk(
        InstanceRiskSuperClass $instanceRisk,
        InstanceRiskSuperClass $newInstanceRisk
    ): void {
        /** @var RecommandationRiskTable $recommandationRiskTable */
        $recommandationRiskTable = $this->get('recommandationRiskTable');
        $recommendationRisks = $recommandationRiskTable->findByAnrAndInstanceRisk(
            $newInstanceRisk->getAnr(),
            $instanceRisk
        );
        foreach ($recommendationRisks as $recommandationRisk) {
            $newRecommendationRisk = (clone $recommandationRisk)
                ->setId(null)
                ->setInstance($newInstanceRisk->getInstance())
                ->setInstanceRisk($newInstanceRisk);

            $recommandationRiskTable->saveEntity($newRecommendationRisk, false);
        }
    }

    protected function createInstanceRiskOwnerObject(AnrSuperClass $anr, string $ownerName): InstanceRiskOwnerSuperClass
    {
        return (new InstanceRiskOwner())
            ->setAnr($anr)
            ->setName($ownerName)
            ->setCreator($this->getConnectedUser()->getEmail());
    }

    protected function getLanguageIndex(AnrSuperClass $anr): int
    {
        return $anr->getLanguage();
    }

    /**
     * @param InstanceRisk $instanceRisk
     * @param array $instanceRiskResult
     *
     * @return array
     */
    private function addCustomFieldsToInstanceRiskResult(
        InstanceRiskSuperClass $instanceRisk,
        array $instanceRiskResult
    ): array {
        $recommendationsUuids = [];
        foreach ($instanceRisk->getRecommendationRisks() as $recommendationRisk) {
            if ($recommendationRisk->getRecommandation() !== null) {
                $recommendationsUuids[] = $recommendationRisk->getRecommandation()->getUuid();
            }
        }

        return array_merge(
            $instanceRiskResult,
            ['recommendations' => implode(',', $recommendationsUuids)]
        );
    }

    private function getCsvRecommendations(AnrSuperClass $anr, array $recsUuids): string
    {
        $recommendationsUuidsNumber = \count($recsUuids);
        $csvString = '';
        foreach ($recsUuids as $index => $recUuid) {
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

    private function getCsvMeasures(int $anrLanguage, int $instanceRiskId): string
    {
        /** @var InstanceRiskTable $instanceRiskTable */
        $instanceRiskTable = $this->get('table');
        $instanceRisk = $instanceRiskTable->findById($instanceRiskId);
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
