<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Model\Entity\InstanceSuperClass;
use Monarc\Core\Service\InstanceService;
use Monarc\FrontOffice\Model\Entity\RecommandationRisk;
use Monarc\FrontOffice\Model\Table\InstanceTable;
use Monarc\FrontOffice\Model\Table\RecommandationRiskTable;
use Monarc\FrontOffice\Model\Table\RecommandationSetTable;
use Monarc\FrontOffice\Model\Table\RecommandationTable;
use Monarc\FrontOffice\Model\Table\UserAnrTable;
use Monarc\FrontOffice\Service\Traits\RecommendationsPositionsUpdateTrait;

/**
 * This class is the service that handles instances in use within an ANR. Inherits most of the behavior from its
 * Monarc\Core parent class.
 * @package Monarc\FrontOffice\Service
 */
class AnrInstanceService extends InstanceService
{
    use RecommendationsPositionsUpdateTrait;

    /** @var UserAnrTable */
    protected $userAnrTable;
    protected $themeTable;
    protected $instanceRiskTable;
    protected $instanceRiskOpTable;
    protected $recommandationRiskTable;
    protected $recommandationTable;
    protected $recommandationSetTable;

    public function delete($id)
    {
        /** @var InstanceTable $instanceTable */
        $instanceTable = $this->get('table');
        $anr = $instanceTable->findById($id)->getAnr();

        parent::delete($id);

        // Reset related recommendations positions to 0.
        /** @var RecommandationTable $recommendationTable */
        $recommendationTable = $this->get('recommandationTable');
        $unlinkedRecommendations = $recommendationTable->findUnlinkedWithNotEmptyPositionByAnr($anr);
        $recommendationsToResetPositions = [];
        foreach ($unlinkedRecommendations as $unlinkedRecommendation) {
            $recommendationsToResetPositions[$unlinkedRecommendation->getUuid()] = $unlinkedRecommendation;
        }
        if (!empty($recommendationsToResetPositions)) {
            $this->resetRecommendationsPositions($anr, $recommendationsToResetPositions);
        }
    }

    /**
     * The code is extracted to be able to refactor the recommendations export,
     * They are duplicated between instances and the code requires improvements itself.
     */
    protected function getExportedRecommendationsData(
        InstanceSuperClass $instance,
        bool $withEval,
        bool $withRecommendations,
        bool $withUnlinkedRecommendations,
        array $riskIds,
        array $riskOpIds
    ): array {
        $result = [];

        if ($withEval && $withRecommendations) {
            /** @var RecommandationSetTable $recommendationSetTable */
            $recommendationSetTable = $this->get('recommandationSetTable');

            $result['recSets'] = [];

            $recommendationsSets = $recommendationSetTable->findByAnr($instance->getAnr());
            foreach ($recommendationsSets as $recommendationSet) {
                $result['recSets'][$recommendationSet->getUuid()] = [
                    'uuid' => $recommendationSet->getUuid(),
                    'label1' => $recommendationSet->getLabel(1),
                    'label2' => $recommendationSet->getLabel(2),
                    'label3' => $recommendationSet->getLabel(3),
                    'label4' => $recommendationSet->getLabel(4),
                ];
            }
        }

        /** @var RecommandationRiskTable $recommendationRiskTable */
        $recommendationRiskTable = $this->get('recommandationRiskTable');
        $recoIds = [];
        if ($withEval && $withRecommendations && !empty($riskIds)) {
            $recosObj = [
                'uuid' => 'uuid',
                'recommandationSet' => 'recommandationSet',
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
            /** @var RecommandationRisk[] $recoRisk */
            $recoRisk = $recommendationRiskTable->getEntityByFields(
                ['anr' => $instance->getAnr()->getId(), 'instanceRisk' => $riskIds],
                ['id' => 'ASC']
            );
            foreach ($recoRisk as $rr) {
                $recommendation = $rr->getRecommandation();
                if ($recommendation !== null) {
                    $recommendationUuid = $recommendation->getUuid();
                    $instanceRiskId = $rr->getInstanceRisk()->getId();
                    $result['recos'][$instanceRiskId][$recommendationUuid] = $recommendation->getJsonArray($recosObj);
                    $result['recos'][$instanceRiskId][$recommendationUuid]['recommandationSet'] = $recommendation->getRecommandationSet()->getUuid();
                    $result['recos'][$instanceRiskId][$recommendationUuid]['commentAfter'] = $rr->getCommentAfter();
                    if (!$withUnlinkedRecommendations && !isset($recoIds[$recommendationUuid])) {
                        $result['recs'][$recommendationUuid] = $recommendation->getJsonArray($recosObj);
                        $result['recs'][$recommendationUuid]['recommandationSet'] = $recommendation->getRecommandationSet()->getUuid();
                    }
                    $recoIds[$recommendationUuid] = $recommendationUuid;
                }
            }
        }

        if ($withEval && $withRecommendations && !empty($riskOpIds)) {
            $recosObj = [
                'uuid' => 'uuid',
                'recommandationSet' => 'recommandationSet',
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
            $recoRisk = $recommendationRiskTable->getEntityByFields(
                ['anr' => $instance->getAnr()->getId(), 'instanceRiskOp' => $riskOpIds],
                ['id' => 'ASC']
            );
            foreach ($recoRisk as $rr) {
                $recommendation = $rr->getRecommandation();
                if ($recommendation !== null) {
                    $instanceRiskOpId = $rr->getInstanceRiskOp()->getId();
                    $recommendationUuid = $recommendation->getUuid();
                    $result['recosop'][$instanceRiskOpId][$recommendationUuid] = $recommendation->getJsonArray($recosObj);
                    $result['recosop'][$instanceRiskOpId][$recommendationUuid]['recommandationSet'] = $recommendation->getRecommandationSet()->getUuid();
                    $result['recosop'][$instanceRiskOpId][$recommendationUuid]['commentAfter'] = $rr->getCommentAfter();
                    if (!$withUnlinkedRecommendations && !isset($recoIds[$recommendationUuid])) {
                        $result['recs'][$recommendationUuid] = $recommendation->getJsonArray($recosObj);
                        $result['recs'][$recommendationUuid]['recommandationSet'] = $recommendation->getRecommandationSet()->getUuid();
                    }
                    $recoIds[$recommendationUuid] = $recommendationUuid;
                }
            }
        }

        // Recommendation unlinked to recommandations-risks
        if ($withUnlinkedRecommendations && $withEval && $withRecommendations) {
            /** @var RecommandationTable $recommendationTable */
            $recommendationTable = $this->get('recommandationTable');
            $recosObj = [
                'uuid' => 'uuid',
                'recommandationSet' => 'recommandationSet',
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
            $recommendations = $recommendationTable->findByAnr($instance->getAnr());
            foreach ($recommendations as $recommendation) {
                if (!isset($recoIds[$recommendation->getUuid()])) {
                    $result['recs'][$recommendation->getUuid()] = $recommendation->getJsonArray($recosObj);
                    $result['recs'][$recommendation->getUuid()]['recommandationSet'] = $recommendation->getRecommandationSet()->getUuid();
                    $recoIds[$recommendation->getUuid()] = $recommendation->getUuid();
                }
            }
        }

        return $result;
    }
}
