<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Model\Entity as CoreEntity;
use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Entity\InstanceRiskSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\TranslateService;
use Monarc\FrontOffice\Model\Entity as FrontOfficeEntity;
use Monarc\FrontOffice\Table;
use Monarc\FrontOffice\Model\Table\RecommandationRiskTable;
use Monarc\FrontOffice\Model\Table\RecommandationTable;
use Monarc\FrontOffice\Service\Traits\RecommendationsPositionsUpdateTrait;

// TODO: consider to drop the inheritance.
class AnrInstanceRiskService
{
    use RecommendationsPositionsUpdateTrait;

    private Table\InstanceRiskTable $instanceRiskTable;

    private Table\InstanceRiskOwnerTable $instanceRiskOwnerTable;

    private RecommandationTable $recommendationTable;

    private RecommandationRiskTable $recommendationRiskTable;

    private Table\AmvTable $amvTable;

    private TranslateService $translateService;

    private CoreEntity\UserSuperClass $connectedUser;

    public function __construct(
        Table\InstanceRiskTable $instanceRiskTable,
        Table\InstanceRiskOwnerTable $instanceRiskOwnerTable,
        Table\AmvTable $amvTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->instanceRiskTable = $instanceRiskTable;
        $this->instanceRiskOwnerTable = $instanceRiskOwnerTable;
        $this->amvTable = $amvTable;
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    // TODO: Process context and risks owner.
    public function createInstanceRisks(
        FrontOfficeEntity\Instance $instance,
        FrontOfficeEntity\Object $object,
        array $params = [],
        bool $saveInDb = true
    ): void {
    /*
        $otherInstance = $this->instanceTable
            ->findOneByAnrAndObjectExcludeInstance($instance->getAnr(), $object, $instance);

        if ($otherInstance !== null && $object->isScopeGlobal()) {
            foreach ($otherInstance->getInstanceRisks() as $instanceRisk) {
                $newInstanceRisk = $this->getConstructedFromObjectInstanceRisk($instanceRisk)
                    ->setAnr($instance->getAnr())
                    ->setInstance($instance)
                    ->setAsset($instanceRisk->getAsset())
                    ->setThreat($instanceRisk->getThreat())
                    ->setVulnerability($instanceRisk->getVulnerability())
                    ->setAmv($instanceRisk->getAmv())
                    ->setInstanceRiskOwner($instanceRisk->getInstanceRiskOwner())
                    ->setCreator($this->connectedUser->getEmail());

                $this->recalculateRiskRates($newInstanceRisk, false);

                $this->instanceRiskTable->save($newInstanceRisk, false);

                // TODO: this is mission og BO
                $this->duplicateRecommendationRisk($instanceRisk, $newInstanceRisk);
            }
        } else {
            foreach ($object->getAsset()->getAmvs() as $amv) {
                $instanceRisk = $this->createInstanceRiskObject()
                    ->setAnr($instance->getAnr())
                    ->setInstance($instance)
                    ->setAmv($amv)
                    ->setAsset($amv->getAsset())
                    ->setThreat($amv->getThreat())
                    ->setVulnerability($amv->getVulnerability())
                    ->setCreator($this->connectedUser->getEmail());

                /* Set risk owner and context in case of import.
                if (!empty($params['risks'])) {
                    $riskKey = array_search($amv->getUuid(), array_column($params['risks'], 'amv'), true);
                    if ($riskKey !== false) {
                        $instanceRiskData = array_values($params['risks'])[$riskKey];
                        $instanceRisk->setContext($instanceRiskData['context'] ?? '');
                        if (!empty($instanceRiskData['riskOwner'])) {
                            $instanceRiskOwner = $this->getOrCreateInstanceRiskOwner(
                                $instance->getAnr(),
                                $instanceRiskData['riskOwner']
                            );
                            $instanceRisk->setInstanceRiskOwner($instanceRiskOwner);
                        }
                    }
                }

                $this->instanceRiskTable->save($instanceRisk, false);

                $this->recalculateRiskRates($instanceRisk, false);
            }
        }

        if ($saveInDb) {
            $this->instanceRiskTable->flush();
        }
    */
    }

    public function getInstanceRisks(Entity\AnrSuperClass $anr, ?int $instanceId, array $params = []): array
    {
                $result[$key] = $this->addCustomFieldsToInstanceRiskResult($instanceRisk, [
                    ....
                    'context' => $instanceRisk->getContext(),
                    'owner' => $instanceRisk->getInstanceRiskOwner()
                        ? $instanceRisk->getInstanceRiskOwner()->getName()
                        : '',
    }

    private function updateInstanceRiskData(Entity\InstanceRisk $instanceRisk, array $data): void
    {
        // TODO: this is missing on Core.
        if (isset($data['owner'])) {
            $this->processRiskOwnerName((string)$data['owner'], $instanceRisk);
        }
        if (isset($data['context'])) {
            $instanceRisk->setContext($data['context']);
        }
        if (isset($data['reductionAmount'])) {
            $instanceRisk->setReductionAmount((int)$data['reductionAmount']);
        }
        if (isset($data['threatRate'])) {
            $instanceRisk->setThreatRate((int)$data['threatRate']);
        }
        if (isset($data['vulnerabilityRate'])) {
            $instanceRisk->setVulnerabilityRate((int)$data['vulnerabilityRate']);
        }
        if (isset($data['comment'])) {
            $instanceRisk->setComment($data['comment']);
        }
        if (isset($data['kindOfMeasure'])) {
            $instanceRisk->setKindOfMeasure((int)$data['kindOfMeasure']);
        }

        $instanceRisk->setUpdater($this->connectedUser->getEmail());

        $this->recalculateRiskRates($instanceRisk, false);
    }
    */


    private function processRiskOwnerName(
        string $ownerName,
        Entity\InstanceRisk $instanceRisk
    ): void {
        if (empty($ownerName)) {
            $instanceRisk->setInstanceRiskOwner(null);
        } else {
            $instanceRiskOwner = $this->instanceRiskOwnerTable->findByAnrAndName(
                $instanceRisk->getAnr(),
                $ownerName
            );
            if ($instanceRiskOwner === null) {
                $instanceRiskOwner = $this->createInstanceRiskOwnerObject($instanceRisk->getAnr(), $ownerName);

                $this->instanceRiskOwnerTable->save($instanceRiskOwner, false);

                $instanceRisk->setInstanceRiskOwner($instanceRiskOwner);
            } elseif ($instanceRisk->getInstanceRiskOwner() === null
                || $instanceRisk->getInstanceRiskOwner()->getId() !== $instanceRiskOwner->getId()
            ) {
                $instanceRisk->setInstanceRiskOwner($instanceRiskOwner);
            }
        }
    }

    private function getOrCreateInstanceRiskOwner(
        FrontOfficeEntity\Anr $anr,
        string $ownerName
    ): FrontOfficeEntity\InstanceRiskOwner {
        if (!isset($this->cachedData['instanceRiskOwners'][$ownerName])) {
            $instanceRiskOwner = $this->instanceRiskOwnerTable->findByAnrAndName($anr, $ownerName);
            if ($instanceRiskOwner === null) {
                $instanceRiskOwner = $this->createInstanceRiskOwnerObject($anr, $ownerName);

                $this->instanceRiskOwnerTable->save($instanceRiskOwner, false);
            }

            $this->cachedData['instanceRiskOwners'][$ownerName] = $instanceRiskOwner;
        }

        return $this->cachedData['instanceRiskOwners'][$ownerName];
    }

    protected function createInstanceRiskOwnerObject(
        FrontOfficeEntity\Anr $anr,
        string $ownerName
    ): FrontOfficeEntity\InstanceRiskOwner {
        return (new FrontOfficeEntity\InstanceRiskOwner())
            ->setAnr($anr)
            ->setName($ownerName)
            ->setCreator($this->connectedUser->getEmail());
    }

    public function updateFromRiskTable(
        FrontOfficeEntity\Anr $anr,
        int $instanceRiskId,
        array $data
    ): FrontOfficeEntity\InstanceRisk {
        /** @var FrontOfficeEntity\InstanceRisk $instanceRisk */
        $instanceRisk = $this->instanceRiskTable->findByIdAndAnr($instanceRiskId, $anr);

        if ($instanceRisk->getThreatRate() !== (int)$data['threatRate']) {
            $instanceRisk->setIsThreatRateNotSetOrModifiedExternally(false);
        }

        // TODO:
        // return $this->update($instanceRiskId, $data);
        return $instanceRisk;
    }

    public function delete(FrontOfficeEntity\Anr $anr, int $id): void
    {
        /** @var FrontOfficeEntity\InstanceRisk $instanceRisk */
        $instanceRisk = $this->instanceRiskTable->findByIdAndAnr($id, $anr);

        $this->instanceRiskTable->remove($instanceRisk);

        $this->processRemovedInstanceRiskRecommendationsPositions($instanceRisk);

    }

    public function recalculateRiskRates(InstanceRiskSuperClass $instanceRisk, bool $saveInDb = true): void
    {
        parent::recalculateRiskRates($instanceRisk, $saveInDb);

        $this->updateInstanceRiskRecommendationsPositions($instanceRisk);
    }

    public function getInstanceRisksInCsv(Anr $anr, $instanceId = null, $params = []): string
    {
        $anrLanguage = $anr->getLanguage();

        // Fill in the header
        $output = implode(';', [
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

        $instanceRisks = $this->getInstanceRisks($anr, $instanceId, $params);

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
                $this->getRecommendationsInCsv($anr, explode(",", (string)$instanceRisk['recommendations'])),
                $instanceRisk['amv'] === null ? null : $this->getMeasuresInCsv($anr, $instanceRisk['amv']),
            ];

            $output .= '"';
            $search = ['"'];
            $replace = ["'"];
            $output .= implode('";"', str_replace($search, $replace, $values));
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

        /* TODO: check why this was needed in getFilterForService and used in Db::buildFilteredQuery, getList below
        $filterJoin = [
            ['as' => 'th', 'rel' => 'threat',],
            ['as' => 'v', 'rel' => 'vulnerability',],
            ['as' => 'i', 'rel' => 'instance',],
        ];
        $filterLeft = [
            ['as' => 'th1', 'rel' => 'threat',],
            ['as' => 'v1', 'rel' => 'vulnerability',],

        ];
        */
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

        /** @var InstanceTable $instanceTable */
        $instanceTable = $this->get('instanceTable');
        $instance = $instanceTable->getEntity($data['instance']);
        $data['asset'] = ['uuid' => $instance->getAsset()->getUuid(), 'anr' => $data['anr']];
        $instanceRisk->exchangeArray($data);
        $dependencies = property_exists($this, 'dependencies') ? $this->dependencies : [];
        $this->setDependencies($instanceRisk, $dependencies);

        /** @var InstanceRiskTable $instanceRiskTable */
        $instanceRiskTable = $this->get('table');
        $instanceRiskTable->save($instanceRisk);

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
                $instanceRiskTable->save($newRisk, $i === $nbBrothers);
                $i++;
            }
        }

        return $instanceRisk->getId();
    }

    /**
     * TODO: review the logic, probably can be merged with self::delete. Moved from AnrRiskService.
     */
    public function deleteInstanceRisk(FrontOfficeEntity\Anr $anr, int $id): void
    {
        /** @var FrontOfficeEntity\InstanceRisk $instanceRisk */
        $instanceRisk = $this->instanceRiskTable->findByIdAndAnr($id, $anr);

        if (!$instanceRisk->isSpecific()) {
            throw new Exception('You can not delete a not specific risk', 412);
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
                        $instanceRiskTable->remove($instanceRisk, false);
                    }
                }
            }
        }

        $instanceRiskTable->remove($instanceRisk);

        $this->processRemovedInstanceRiskRecommendationsPositions($instanceRisk);

        return true;
    }

    protected function duplicateRecommendationRisk(
        InstanceRiskSuperClass $instanceRisk,
        InstanceRiskSuperClass $newInstanceRisk
    ): void {
        $recommendationRisks = $this->recommendationRiskTable->findByAnrAndInstanceRisk(
            $newInstanceRisk->getAnr(),
            $instanceRisk
        );
        foreach ($recommendationRisks as $recommandationRisk) {
            $newRecommendationRisk = (clone $recommandationRisk)
                ->setId(null)
                ->setInstance($newInstanceRisk->getInstance())
                ->setInstanceRisk($newInstanceRisk);

            $this->recommendationRiskTable->saveEntity($newRecommendationRisk, false);
        }
    }

    protected function getConstructedFromObjectInstanceRisk(
        CoreEntity\InstanceRiskSuperClass $instanceRisk
    ): CoreEntity\InstanceRiskSuperClass {
        return FrontOfficeEntity\InstanceRisk::constructFromObject($instanceRisk);
    }

    protected function createInstanceRiskObject(): CoreEntity\InstanceRiskSuperClass
    {
        return new FrontOfficeEntity\InstanceRisk();
    }

    protected function createInstanceRiskOwnerObject(AnrSuperClass $anr, string $ownerName): InstanceRiskOwner
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
    protected function addCustomFieldsToInstanceRiskResult(
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

    private function getRecommendationsInCsv(AnrSuperClass $anr, array $recsUuids): string
    {
        $csvData = [];
        foreach ($recsUuids as $recUuid) {
            if (!empty($recUuid)) {
                $recommendation = $this->recommendationTable->findByAnrAndUuid($anr, $recUuid);
                $csvData[] = $recommendation->getCode() . " - " . $recommendation->getDescription();
            }
        }

        return implode("\n", $csvData);
    }

    private function getMeasuresInCsv(FrontOfficeEntity\Anr $anr, string $amvUuid): string
    {
        /** @var FrontOfficeEntity\Amv $amv */
        $amv = $this->amvTable->findByUuidAndAnr($amvUuid, $anr);
        $csvData = [];
        foreach ($amv->getMeasures() as $measure) {
            $csvData[] = "[" . $measure->getReferential()->getLabel($anr->getLanguage()) . "] "
                . $measure->getCode() . " - " . $measure->getLabel($anr->getLanguage());
        }

        return implode("\n", $csvData);
    }
}
