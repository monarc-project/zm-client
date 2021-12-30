<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\OptimisticLockException;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\AbstractService;
use Monarc\FrontOffice\Model\Entity\Instance;
use Monarc\FrontOffice\Model\Entity\InstanceRisk;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOp;
use Monarc\FrontOffice\Model\Entity\MonarcObject;
use Monarc\FrontOffice\Model\Entity\Recommandation;
use Monarc\FrontOffice\Model\Entity\RecommandationHistoric;
use Monarc\FrontOffice\Model\Entity\RecommandationRisk;
use Monarc\FrontOffice\Model\Table;
use Monarc\FrontOffice\Service\Traits\RecommendationsPositionsUpdateTrait;

/**
 * This class is the service that handles risks' recommendations within an ANR.
 * @package Monarc\FrontOffice\Service
 */
class AnrRecommandationRiskService extends AbstractService
{
    use RecommendationsPositionsUpdateTrait;

    protected Table\AnrTable $anrTable;

    protected Table\UserAnrTable $userAnrTable;

    protected Table\RecommandationTable $recommendationTable;

    protected Table\RecommendationHistoricTable $recommendationHistoricTable;

    protected Table\InstanceRiskTable $instanceRiskTable;

    protected Table\InstanceRiskOpTable $instanceRiskOpTable;

    protected AnrService $anrService;

    protected Table\InstanceTable $instanceTable;

    protected $dependencies = [
        'recommandation',
        'anr',
        'asset',
        'threat',
        'vulnerability',
        'instance',
        'instanceRisk',
        'instanceRiskOp'
    ];

    /**
     * @inheritdoc
     */
    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null)
    {
        [$filterJoin, $filterLeft, $filtersCol] = $this->get('entity')->getFiltersForService();
        /** @var Table\RecommandationRiskTable $table */
        $table = $this->get('table');
        $recosRisks = $table->fetchAllFiltered(
            array_keys($this->get('entity')->getJsonArray()),
            $page,
            $limit,
            $this->parseFrontendOrder($order),
            $this->parseFrontendFilter($filter, $this->filterColumns),
            $filterAnd,
            $filterJoin,
            $filterLeft
        );

        foreach ($recosRisks as $key => $recoRisk) {
            if (!empty($recoRisk['recommandation'])) {
                if (empty($recoRisk['recommandation']->duedate) || $recoRisk['recommandation']->duedate == '1970-01-01 00:00:00') {
                } else {
                    if ($recoRisk['recommandation']->duedate instanceof \DateTime) {
                        $recoRisk['recommandation']->duedate = $recoRisk['recommandation']->duedate->getTimestamp();
                    } else {
                        $recoRisk['recommandation']->duedate = strtotime($recoRisk['recommandation']->duedate);
                    }
                    $recoRisk['recommandation']->duedate = date('d-m-Y', $recoRisk['recommandation']->duedate);
                }

                $recoRisk['recommandation']->recommandationSet->anr = $recoRisk['anr']->id;
                $recosRisks[$key]['recommandation'] = $recoRisk['recommandation'];
            }
        }

        // Filter out duplicate global objects
        $knownGlobObjId = $objectCache = [];

        if (isset($filterAnd['r.uuid']) && isset($filterAnd['r.anr'])) {
            return array_filter($recosRisks, function ($recoRisk) use (&$knownGlobObjId, &$objectCache) {
                $vulnerability = $recoRisk['vulnerability'];
                $threat = $recoRisk['threat'];
                if ($recoRisk['instanceRiskOp'] instanceof InstanceRiskOp
                    || $vulnerability === null
                    || $threat === null
                ) {
                    return true;
                }

                /** @var Instance $instance */
                $instance = $this->instanceTable->getEntity($recoRisk['instance']);
                $objId = $instance->getObject()->getUuid();

                if (!isset($knownGlobObjId[$objId][$threat->getUuid()][$vulnerability->getUuid()])) {
                    $objectCache[$objId] = $instance->getObject();
                    if ($objectCache[$objId]->isScopeGlobal()) {
                        $knownGlobObjId[$objId][$threat->getUuid()][$vulnerability->getUuid()] = $objId;
                    }

                    return true;
                }

                return false;
            });
        }

        return $recosRisks;
    }

    /**
     * @throws EntityNotFoundException
     * @throws Exception
     * @throws OptimisticLockException
     */
    public function delete($id)
    {
        /** @var Table\RecommandationRiskTable $recommendationRiskTable */
        $recommendationRiskTable = $this->get('table');
        $recommendationRisk = $recommendationRiskTable->findById($id);
        $recommendation = $recommendationRisk->getRecommandation();

        if ($recommendationRisk->getInstanceRisk() !== null) {
            $instanceRisk = $recommendationRisk->getInstanceRisk();

            if ($instanceRisk->getInstance()->getObject()->getScope() === MonarcObject::SCOPE_GLOBAL) {
                if ($instanceRisk->getAmv() === null && $instanceRisk->isSpecific()) {//case specific amv_id = null
                    $brothers = $this->instanceRiskTable->getEntityByFields([
                        'asset' => [
                            'anr' => $instanceRisk->getAnr()->getId(),
                            'uuid' => $instanceRisk->getAsset()->getUuid(),
                        ],
                        'threat' => [
                            'anr' => $instanceRisk->getAnr()->getId(),
                            'uuid' => $instanceRisk->getThreat()->getUuid(),
                        ],
                        'vulnerability' => [
                            'anr' => $instanceRisk->getAnr()->getId(),
                            'uuid' => $instanceRisk->getVulnerability()->getUuid(),
                        ],
                    ]);
                } else {
                    $brothers = $this->instanceRiskTable->findByAmv($instanceRisk->getAmv());
                }
                $brothersIds = [];
                foreach ($brothers as $brother) {
                    if ($instanceRisk->getInstance()->getObject()->isEqualTo($brother->getInstance()->getObject())) {
                        $brothersIds[] = $brother->getId();
                    }
                }

                foreach ($recommendation->getRecommendationRisks() as $recommendationRiskRelatedToReco) {
                    if ($recommendationRiskRelatedToReco->getInstanceRisk()
                        && \in_array($recommendationRiskRelatedToReco->getInstanceRisk()->getId(), $brothersIds, true)
                    ) {
                        $recommendationRiskTable->deleteEntity($recommendationRiskRelatedToReco);
                    }
                }
            } else {
                $recommendationRiskTable->deleteEntity($recommendationRisk);
            }
        } else {
            $instanceRiskOp = $recommendationRisk->getInstanceRiskOp();

            if ($instanceRiskOp->getObject()->get('scope') === MonarcObject::SCOPE_GLOBAL) {
                $brothers = $this->instanceRiskOpTable->getEntityByFields([
                    'anr' => $recommendationRisk->getInstanceRiskOp()->getAnr()->getId(),
                    'rolfRisk' => $recommendationRisk->getInstanceRiskOp()->getRolfRisk()->getId(),
                ]);
                $brothersIds = [];
                foreach ($brothers as $brother) {
                    if ($instanceRiskOp->getInstance()->getObject()->isEqualTo($brother->getInstance()->getObject())) {
                        $brothersIds[] = $brother->id;
                    }
                }

                foreach ($recommendation->getRecommendationRisks() as $recommendationRiskRelatedToReco) {
                    if ($recommendationRiskRelatedToReco->getInstanceRiskOp()
                        && \in_array($recommendationRiskRelatedToReco->getInstanceRiskOp()->getId(), $brothersIds, true)
                    ) {
                        $recommendationRiskTable->deleteEntity($recommendationRiskRelatedToReco);
                    }
                }
            } else {
                $recommendationRiskTable->deleteEntity($recommendationRisk);
            }
        }

        // Reset the recommendation's position if it's not linked to risks anymore.
        if (!$recommendation->hasLinkedRecommendationRisks()) {
            $this->resetRecommendationsPositions(
                $recommendation->getAnr(),
                [$recommendation->getUuid() => $recommendation]
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function create($data, $last = true)
    {
        //verify not already exist
        /** @var Table\RecommandationRiskTable $table */
        $table = $this->get('table');
        if ($data['op']) {
            $exist = $table->getEntityByFields([
                'anr' => $data['anr'],
                'recommandation' => ['anr' => $data['anr'], 'uuid' => $data['recommandation']],
                'instanceRiskOp' => $data['risk']
            ]);
            $tableUsed = $this->instanceRiskOpTable;
        } else {
            $exist = $table->getEntityByFields([
                'anr' => $data['anr'],
                'recommandation' => ['anr' => $data['anr'], 'uuid' => $data['recommandation']],
                'instanceRisk' => $data['risk']
            ]);
            $tableUsed = $this->instanceRiskTable;
        }
        if (count($exist)) {
            throw new Exception('Risk already link to this recommendation', 412);
        }

        $gRisk = $tableUsed->getEntity($data['risk']);
        $id = $this->createRecommandationRisk($data, $gRisk);
        if ($gRisk->getInstance()->getObject()->get('scope') == MonarcObject::SCOPE_GLOBAL && !$data['op']) {

            $instances = $this->instanceTable->getEntityByFields([
                'object' => [
                    'anr' => $gRisk->anr->id,
                    'uuid' => $gRisk->getInstance()->getObject()->getUuid()
                ],
                'anr' => $gRisk->anr->id,
                'id' => ['op' => '!=', 'value' => $gRisk->getInstance()->get('id')],
            ]);
            $instanceIds = [];
            foreach ($instances as $i) {
                $instanceIds[$i->get('id')] = $i->get('id');
            }

            if (!empty($instanceIds)) {
                $brothers = $tableUsed->getEntityByFields([
                    'asset' => ['anr' => $gRisk->getAnr()->getId(), 'uuid' => $gRisk->getAsset()->getUuid()],
                    'threat' => ['anr' => $gRisk->getAnr()->getId(), 'uuid' => $gRisk->getThreat()->getUuid()],
                    'vulnerability' => ['anr' => $gRisk->getAnr()->getId(), 'uuid' => $gRisk->getVulnerability()->getUuid()],
                    'instance' => ['op' => 'IN', 'value' => $instanceIds],
                    'anr' => $gRisk->getAnr()->getId()
                ]);

                foreach ($brothers as $brother) {
                    $this->createRecommandationRisk($data, $brother);
                }
            }
        }

        $this->updateInstanceRiskRecommendationsPositions($gRisk);

        return $id;
    }

    public function getTreatmentPlan(int $anrId, string $uuid = null): array
    {
        $anr = $this->anrTable->findById($anrId);

        if ($uuid !== null) {
            $recommendation = $this->recommendationTable->findByAnrAndUuid($anr, $uuid);
            $linkedRecommendations = $recommendation === null ? [] : [$recommendation];
        } else {
            $linkedRecommendations = $this->recommendationTable
                ->findLinkedWithRisksByAnrWithSpecifiedImportanceAndPositionAndExcludeRecommendations(
                    $anr,
                    [],
                    ['r.position' => 'ASC']
                );
        }

        $treatmentPlan = [];
        foreach ($linkedRecommendations as $linkedRecommendation) {
            $instanceRisks = [];
            $globalObjects = [];
            foreach ($linkedRecommendation->getRecommendationRisks() as $index => $recommendationRisk) {
                $instanceRisk = $recommendationRisk->getInstanceRisk();
                $type = 'risks';
                if ($instanceRisk === null) {
                    $instanceRisk = $recommendationRisk->getInstanceRiskOp();
                    $type = 'risksop';
                }

                if ($type === 'risks' && $recommendationRisk->hasGlobalObjectRelation()) {
                    $path = $recommendationRisk->getInstance()->{'getName' . $anr->getLanguage()}();

                    $globalObjectUuid = $recommendationRisk->getGlobalObject()->getUuid();
                    $assetUuid = $recommendationRisk->getAsset()->getUuid();
                    $threatUuid = $recommendationRisk->getThreat()->getUuid();
                    $vulnerabilityUuid = $recommendationRisk->getVulnerability()->getUuid();
                    if (!empty($globalObjects[$globalObjectUuid][$assetUuid][$threatUuid][$vulnerabilityUuid])) {
                        if ($globalObjects[$globalObjectUuid][$assetUuid][$threatUuid][$vulnerabilityUuid]['maxRisk']
                            < $instanceRisk->getCacheMaxRisk()
                        ) {
                            $globalObjects[$globalObjectUuid][$assetUuid][$threatUuid][$vulnerabilityUuid]['maxRisk'] =
                                $instanceRisk->getCacheMaxRisk();
                            $ind =
                                $globalObjects[$globalObjectUuid][$assetUuid][$threatUuid][$vulnerabilityUuid]['index'];
                            $instanceRisks[$type][$ind] = array_merge($instanceRisk->getJsonArray(), [
                                'path' => $path,
                                'isGlobal' => true,
                            ]);
                        }

                        continue;
                    }

                    $globalObjects[$globalObjectUuid][$assetUuid][$threatUuid][$vulnerabilityUuid] = [
                        'index' => $index,
                        'maxRisk' => $instanceRisk->getCacheMaxRisk(),
                    ];
                } else {
                    $path = $instanceRisk->getInstance()->getHierarchyString();
                }

                $instanceRisks[$type][$index] = array_merge($instanceRisk->getJsonArray(), [
                    'path' => $path,
                    'isGlobal' => $recommendationRisk->hasGlobalObjectRelation(),
                ]);
                // TODO: add only required fields instead of getJsonArray.
                unset(
                    $instanceRisks[$type][$index]['__initializer__'],
                    $instanceRisks[$type][$index]['__cloner__'],
                    $instanceRisks[$type][$index]['__isInitialized__'],
                    $instanceRisks[$type][$index]['instance'],
                    $instanceRisks[$type][$index]['amv'],
                    $instanceRisks[$type][$index]['anr'],
                    $instanceRisks[$type][$index]['asset'],
                    $instanceRisks[$type][$index]['threat'],
                    $instanceRisks[$type][$index]['vulnerability']
                );
            }

            $treatmentPlan[] = array_merge([
                'uuid' => $linkedRecommendation->getUuid(),
                'code' => $linkedRecommendation->getCode(),
                'description' => $linkedRecommendation->getDescription(),
                'importance' => $linkedRecommendation->getImportance(),
                'position' => $linkedRecommendation->getPosition(),
                'comment' => $linkedRecommendation->getComment(),
                'status' => $linkedRecommendation->getStatus(),
                'responsable' => $linkedRecommendation->getResponsable(),
                'duedate' => $linkedRecommendation->getDueDate() !== null
                    ? $linkedRecommendation->getDueDate()->format('Y-m-d')
                    : '',
                'counterTreated' => $linkedRecommendation->getCounterTreated(),
            ], $instanceRisks);
        }

        return $treatmentPlan;
    }

    /**
     * Creates a new recommendation for the provided risk
     *
     * @param array $data The data from the API
     * @param InstanceRisk|InstanceRiskOp $risk The target risk or OP risk
     *
     * @return RecommandationRisk The created/saved recommendation risk
     */
    public function createRecommandationRisk($data, $risk)
    {
        /** @var Table\RecommandationRiskTable $table */
        $table = $this->get('table');

        $class = $this->get('entity');
        $entity = new $class();
        $entity->set('anr', $risk->anr);
        $entity->setLanguage($this->getLanguage());
        $entity->setDbAdapter($this->get('table')->getDb());
        $entity->exchangeArray($data);

        $dependencies = (property_exists($this, 'dependencies')) ? $this->dependencies : [];
        $this->setDependencies($entity, $dependencies);
        if ($data['op']) {
            $entity->setInstanceRisk(null);
            $entity->setInstanceRiskOp($risk);
            $entity->setAnr(null);
            $table->save($entity);
            $entity->set('anr', $risk->anr); //TO IMPROVE  double save to have the correct anr_id with risk op
        } else {
            $entity->setInstanceRisk($risk);
            $entity->setInstanceRiskOp(null);

            $entity->setAsset($risk->getAsset());
            $entity->setThreat($risk->getThreat());
            $entity->setVulnerability($risk->getVulnerability());
        }

        $entity->setInstance($risk->getInstance());
        if ($risk->getInstance()->getObject()->get('scope') == MonarcObject::SCOPE_GLOBAL) {
            $entity->setGlobalObject($risk->getInstance()->getObject());
        }

        return $table->save($entity);
    }

    /**
     * @throws EntityNotFoundException
     * @throws OptimisticLockException
     */
    public function resetRecommendationsPositionsToDefault(int $anrId): void
    {
        $anr = $this->anrTable->findById($anrId);

        /** @var Table\RecommandationRiskTable $recommendationRiskTable */
        $recommendationRiskTable = $this->get('table');
        $recommendationRisks = $recommendationRiskTable->findByAnr($anr, ['r.importance' => 'DESC', 'r.code' => 'ASC']);

        $position = 1;
        $updatedRecommendationsUuid = [];
        foreach ($recommendationRisks as $recommendationRisk) {
            $recommendation = $recommendationRisk->getRecommandation();
            if (!isset($updatedRecommendationsUuid[$recommendation->getUuid()])) {
                $this->recommendationTable->saveEntity($recommendation->setPosition($position++), false);
                $updatedRecommendationsUuid[$recommendation->getUuid()] = true;
            }
        }
        $this->recommendationTable->getDb()->flush();
    }

    /**
     * Validates a recommendation risk. Operational risks may not be validated, and will throw an error.
     */
    public function validateFor(int $recommendationRiskId, array $data): void
    {
        /** @var Table\RecommandationRiskTable $recommendationRiskTable */
        $recommendationRiskTable = $this->get('table');
        $recommendationRisk = $recommendationRiskTable->findById($recommendationRiskId);

        // validate for operational risks
        if ($recommendationRisk->getInstanceRiskOp() !== null) {
            // Verify if recommendation risk is final (are there more recommendations linked to the risk)
            $isRiskRelatedRecommendationFinal = \count($recommendationRiskTable->findByAnrAndOperationalInstanceRisk(
                $recommendationRisk->getAnr(),
                $recommendationRisk->getInstanceRiskOp()
            )) === 1;

            // Automatically record the change in history before modifying values
            $this->createRecoRiskOpHistoric($data, $recommendationRisk, $isRiskRelatedRecommendationFinal);

            if ($isRiskRelatedRecommendationFinal) {
                // Overload observation for volatile comment (after measure)
                $cacheCommentAfter = [];

                $riskRecoHistos = $this->recommendationHistoricTable->getEntityByFields([
                    'instanceRiskOp' => $recommendationRisk->getInstanceRiskOp()->getId()
                ], ['id' => 'DESC']);
                $c = 0;

                foreach ($riskRecoHistos as $riskRecoHisto) {
                    /*
                    On ne prend que:
                    - le dernier "final"
                    - et les précédent "non final"
                    */
                    if (!$riskRecoHisto->get('final') || ($riskRecoHisto->get('final') && $c <= 0)) {
                        if (strlen($riskRecoHisto->get('cacheCommentAfter'))) {
                            $cacheCommentAfter[] = $riskRecoHisto->get('cacheCommentAfter');
                        }
                        $c++;
                    } else {
                        break;
                    }
                }

                /** @var InstanceRiskOp $instanceRiskOp */
                $instanceRiskOp = $recommendationRisk->getInstanceRiskOp();
                // array_reverse because "['id' => 'DESC']"
                $instanceRiskOp->setComment(implode("\n\n", array_reverse($cacheCommentAfter)));
                $instanceRiskOp->setMitigation('');
                $instanceRiskOp->setNetProb($instanceRiskOp->getTargetedProb());
                $instanceRiskOp->setCacheNetRisk($instanceRiskOp->getCacheTargetedRisk());
                foreach ($instanceRiskOp->getOperationalInstanceRiskScales() as $operationalInstanceRiskScale) {
                    $operationalInstanceRiskScale->setNetValue($operationalInstanceRiskScale->getTargetedValue());
                    $operationalInstanceRiskScale->setTargetedValue(-1);
                }

                $instanceRiskOp->setTargetedProb(-1);
                $instanceRiskOp->setCacheTargetedRisk(-1);
                $instanceRiskOp->setKindOfMeasure(InstanceRiskOp::KIND_NOT_TREATED);

                $this->instanceRiskOpTable->saveEntity($instanceRiskOp);
            }
        } elseif ($recommendationRisk->getInstanceRisk() !== null) { // validate for information risks
            // Verify if recommendation risk is final (are there more recommendations linked to the risk)
            $isRiskRelatedRecommendationFinal = \count($recommendationRiskTable->findByAnrAndInstanceRisk(
                $recommendationRisk->getAnr(),
                $recommendationRisk->getInstanceRisk()
            )) === 1;

            // Automatically record the change in history before modifying values
            $this->createRecoRiskHistoric($data, $recommendationRisk, $isRiskRelatedRecommendationFinal);

            if ($isRiskRelatedRecommendationFinal) {
                // Overload observation for volatile comment (after measure)
                $cacheCommentAfter = [];

                $riskRecoHistos = $this->recommendationHistoricTable->getEntityByFields([
                    'instanceRisk' => $recommendationRisk->getInstanceRisk()->getId()
                ], ['id' => 'DESC']);
                $c = 0;

                foreach ($riskRecoHistos as $riskRecoHisto) {
                    /*
                    On ne prend que:
                    - le dernier "final"
                    - et les précédent "non final"
                    */
                    if (!$riskRecoHisto->get('final') || ($riskRecoHisto->get('final') && $c <= 0)) {
                        if (strlen($riskRecoHisto->get('cacheCommentAfter'))) {
                            $cacheCommentAfter[] = $riskRecoHisto->get('cacheCommentAfter');
                        }
                        $c++;
                    } else {
                        break;
                    }
                }

                // Update instance risk
                $instanceRisk = $recommendationRisk->getInstanceRisk();

                $instanceRisk->setComment(
                    implode("\n\n", array_reverse($cacheCommentAfter))
                ); // array_reverse because "['id' => 'DESC']"
                $instanceRisk->setCommentAfter('');

                // Apply reduction vulnerability on risk
                $oldVulRate = $instanceRisk->getVulnerabilityRate();
                $newVulnerabilityRate = $instanceRisk->getVulnerabilityRate() - $instanceRisk->getReductionAmount();
                $instanceRisk->setVulnerabilityRate($newVulnerabilityRate >= 0 ? $newVulnerabilityRate : 0);

                $instanceRisk->setRiskConfidentiality(
                    $this->getRiskC(
                        $instanceRisk->getInstance()->getConfidentiality(),
                        $instanceRisk->getThreatRate(),
                        $instanceRisk->getVulnerabilityRate()
                    )
                );
                $instanceRisk->setRiskIntegrity(
                    $this->getRiskI(
                        $instanceRisk->getInstance()->getIntegrity(),
                        $instanceRisk->getThreatRate(),
                        $instanceRisk->getVulnerabilityRate()
                    )
                );
                $instanceRisk->setRiskAvailability(
                    $this->getRiskD(
                        $instanceRisk->getInstance()->getAvailability(),
                        $instanceRisk->getThreatRate(),
                        $instanceRisk->getVulnerabilityRate()
                    )
                );

                $risks = [];
                $impacts = [];
                if ($instanceRisk->getThreat()->getConfidentiality()) {
                    $risks[] = $instanceRisk->getRiskConfidentiality();
                    $impacts[] = $instanceRisk->getInstance()->getConfidentiality();
                }
                if ($instanceRisk->getThreat()->getIntegrity()) {
                    $risks[] = $instanceRisk->getRiskIntegrity();
                    $impacts[] = $instanceRisk->getInstance()->getIntegrity();
                }
                if ($instanceRisk->getThreat()->getAvailability()) {
                    $risks[] = $instanceRisk->getRiskAvailability();
                    $impacts[] = $instanceRisk->getInstance()->getAvailability();
                }

                $instanceRisk->setCacheMaxRisk(\count($risks) ? max($risks) : -1);
                $instanceRisk->setCacheTargetedRisk(
                    $this->getTargetRisk(
                        $impacts,
                        $instanceRisk->getThreatRate(),
                        $oldVulRate,
                        $instanceRisk->getReductionAmount()
                    )
                );

                // Set reduction amount to 0
                $instanceRisk->setReductionAmount(0);

                // Change status to NOT_TREATED
                $instanceRisk->setKindOfMeasure(InstanceRisk::KIND_NOT_TREATED);

                $this->instanceRiskTable->saveEntity($instanceRisk);

                // Impact on brothers
                if ($recommendationRisk->hasGlobalObjectRelation()) {
                    $brothersInstances = $this->instanceTable->findByAnrAndObject(
                        $recommendationRisk->getAnr(),
                        $recommendationRisk->getGlobalObject()
                    );
                    foreach ($brothersInstances as $brotherInstance) {
                        $brothersInstancesRisks = $this->instanceRiskTable->findByInstanceAndInstanceRiskRelations(
                            $brotherInstance,
                            $instanceRisk,
                            false,
                            true
                        );

                        foreach ($brothersInstancesRisks as $brotherInstanceRisk) {
                            $brotherInstanceRisk->setComment($instanceRisk->getComment())
                                ->setCommentAfter($instanceRisk->getCommentAfter())
                                ->setVulnerabilityRate($instanceRisk->getVulnerabilityRate())
                                ->setRiskConfidentiality($instanceRisk->getRiskConfidentiality())
                                ->setRiskIntegrity($instanceRisk->getRiskIntegrity())
                                ->setRiskAvailability($instanceRisk->getRiskAvailability())
                                ->setCacheMaxRisk($instanceRisk->getCacheMaxRisk())
                                ->setCacheTargetedRisk($instanceRisk->getCacheTargetedRisk())
                                ->setReductionAmount($instanceRisk->getReductionAmount())
                                ->setKindOfMeasure($instanceRisk->getKindOfMeasure());

                            $this->instanceRiskTable->saveEntity($instanceRisk, false);
                        }
                        $this->instanceRiskTable->getDb()->flush();
                    }
                }
            }
        }

        $this->removeTheLink($recommendationRisk);
        $this->updateRecommendationData($recommendationRisk->getRecommandation());
    }

    /**
     * Creates an entry in the recommendation's history for information risks to keep a log of changes.
     *
     * @param array $data The history data (comment)
     * @param RecommandationRisk $recoRisk The recommendation risk to historize
     * @param bool $final Whether or not it's the final event
     */
    public function createRecoRiskHistoric(array $data, RecommandationRisk $recoRisk, bool $final)
    {
        $reco = $recoRisk->getRecommandation();
        $instanceRisk = $recoRisk->getInstanceRisk();
        $anr = $recoRisk->getAnr();
        $lang = $anr->getLanguage();

        $histo = [
            'final' => $final,
            'implComment' => $data['comment'],
            'recoCode' => $reco->getCode(),
            'recoDescription' => $reco->getDescription(),
            'recoImportance' => $reco->getImportance(),
            'recoComment' => $reco->getComment(),
            'recoDuedate' => $reco->getDueDate(),
            'recoResponsable' => $reco->getResponsable(),
            'riskInstance' => $instanceRisk->getInstance()->{'getName' . $lang}(),
            'riskInstanceContext' => $instanceRisk->getInstance()->getHierarchyString(),
            'riskAsset' => $instanceRisk->getAsset()->getLabel($lang),
            'riskThreat' => $instanceRisk->getThreat()->getLabel($lang),
            'riskThreatVal' => $instanceRisk->getThreatRate(),
            'riskVul' => $instanceRisk->getVulnerability()->getLabel($lang),
            'riskVulValBefore' => $instanceRisk->getVulnerabilityRate(),
            'riskVulValAfter' => $final
                ? max(0, $instanceRisk->getVulnerabilityRate() - $instanceRisk->getReductionAmount())
                : $instanceRisk->getVulnerabilityRate(),
            'riskKindOfMeasure' => $instanceRisk->getKindOfMeasure(),
            'riskCommentBefore' => $instanceRisk->getComment(),
            'riskCommentAfter' => $final ? $recoRisk->getCommentAfter() : $instanceRisk->getComment(),
            'riskMaxRiskBefore' => $instanceRisk->getCacheMaxRisk(),
            'riskMaxRiskAfter' => $final ? $instanceRisk->getCacheTargetedRisk() : $instanceRisk->getCacheMaxRisk(),
            'riskColorBefore' => $instanceRisk->getCacheMaxRisk() !== -1
                ? $this->anrService->getColor($anr, $instanceRisk->getCacheMaxRisk())
                : '',
            'cacheCommentAfter' => $recoRisk->getCommentAfter(),
            'riskColorAfter' => $final
                ? ($instanceRisk->getCacheTargetedRisk() !== -1
                    ? $this->anrService->getColor($anr, $instanceRisk->getCacheTargetedRisk())
                    : ''
                )
                : ($instanceRisk->getCacheMaxRisk() !== -1
                    ? $this->anrService->getColor($anr, $instanceRisk->getCacheMaxRisk())
                    : ''
                ),
        ];

        $recoHisto = new RecommandationHistoric();
        $recoHisto->setLanguage($this->getLanguage());
        $recoHisto->setDbAdapter($this->recommendationHistoricTable->getDb());
        $recoHisto->exchangeArray($histo);

        $recoHisto->setAnr($anr);
        $recoHisto->instanceRisk = $instanceRisk;

        $this->recommendationHistoricTable->save($recoHisto);
    }

    /**
     * Creates an entry in the recommendation's history for operational risks to keep a log of changes.
     *
     * @param array $data The history data (comment)
     * @param RecommandationRisk $recoRisk The recommendation risk to historize
     * @param bool $final Whether or not it's the final event
     */
    public function createRecoRiskOpHistoric(array $data, RecommandationRisk $recoRisk, bool $final)
    {
        $recommendation = $recoRisk->getRecommandation();
        $instanceRiskOp = $recoRisk->getInstanceRiskOp();
        $anr = $recoRisk->getAnr();
        $lang = $anr->getLanguage();

        $histo = [
            'final' => $final,
            'implComment' => $data['comment'],
            'recoCode' => $recommendation->getCode(),
            'recoDescription' => $recommendation->getDescription(),
            'recoImportance' => $recommendation->getImportance(),
            'recoComment' => $recommendation->getComment(),
            'recoDuedate' => $recommendation->getDueDate(),
            'recoResponsable' => $recommendation->getResponsable(),
            'riskInstance' => $instanceRiskOp->getInstance()->{'getName' . $lang}(),
            'riskInstanceContext' => $instanceRiskOp->getInstance()->getHierarchyString(),
            'riskAsset' => $instanceRiskOp->getObject()->getAsset()->getLabel($lang),
            'riskOpDescription' => $instanceRiskOp->getRiskCacheLabel($lang),
            'netProbBefore' => $instanceRiskOp->getNetProb(),
            'riskKindOfMeasure' => $instanceRiskOp->getKindOfMeasure(),
            'riskCommentBefore' => $instanceRiskOp->getComment(),
            'riskCommentAfter' => $final ? $recoRisk->getCommentAfter() : $instanceRiskOp->getComment(),
            'riskMaxRiskBefore' => $instanceRiskOp->getCacheNetRisk(),
            'riskMaxRiskAfter' => $final ? $instanceRiskOp->getCacheTargetedRisk() : $instanceRiskOp->getCacheNetRisk(),
            'riskColorBefore' => $instanceRiskOp->getCacheNetRisk() !== -1
                ? $this->anrService->getColorRiskOp($anr, $instanceRiskOp->getCacheNetRisk())
                : '',
            'cacheCommentAfter' => $recoRisk->getCommentAfter(),
            'riskColorAfter' => $final
                ? ($instanceRiskOp->getCacheTargetedRisk() !== -1
                    ? $this->anrService->getColorRiskOp($anr, $instanceRiskOp->getCacheTargetedRisk())
                    : ''
                )
                : ($instanceRiskOp->getCacheNetRisk() !== -1
                    ? $this->anrService->getColorRiskOp($anr, $instanceRiskOp->getCacheNetRisk())
                    : ''
                ),

        ];

        $recoHisto = new RecommandationHistoric();
        $recoHisto->setLanguage($this->getLanguage());
        $recoHisto->setDbAdapter($this->recommendationHistoricTable->getDb());
        $recoHisto->exchangeArray($histo);

        $recoHisto->setAnr($anr);
        $recoHisto->instanceRiskOp = $instanceRiskOp;

        $this->recommendationHistoricTable->save($recoHisto);
    }

    /**
     * Unlink the recommendation from related risk (remove the passed link between risks and recommendation).
     */
    private function removeTheLink(RecommandationRisk $recommendationRisk): void
    {
        /** @var Table\RecommandationRiskTable $recommendationRiskTable */
        $recommendationRiskTable = $this->get('table');

        if ($recommendationRisk->hasGlobalObjectRelation()) {
            $recommendationsRisksLinkedByRecommendationGlobalObjectAndAmv = $recommendationRiskTable
                ->findAllLinkedByRecommendationGlobalObjectAndAmv($recommendationRisk);

            foreach ($recommendationsRisksLinkedByRecommendationGlobalObjectAndAmv as $linkedRecommendationRisk) {
                $recommendationRiskTable->deleteEntity($linkedRecommendationRisk, false);
            }
            $recommendationRiskTable->getDb()->flush();
        } else {
            $recommendationRiskTable->deleteEntity($recommendationRisk);
        }
    }

    /**
     * @throws OptimisticLockException
     */
    private function updateRecommendationData(Recommandation $recommendation): void
    {
        $recommendation->incrementCounterTreated();

        if (!$recommendation->hasLinkedRecommendationRisks()) {
            $recommendation->setDueDate(null);
            $recommendation->setResponsable('');
            $recommendation->setComment('');

            $this->resetRecommendationsPositions(
                $recommendation->getAnr(),
                [$recommendation->getUuid() => $recommendation]
            );
        }

        $this->recommendationTable->saveEntity($recommendation);
    }
}
