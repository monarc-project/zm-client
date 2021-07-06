<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\InstanceService;
use Monarc\FrontOffice\Model\Table\InstanceTable;
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

    protected function getExportedRecommendations(
        bool $withEval,
        bool $withRecommendations,
        bool $withUnlinkedRecommendations,
        array $riskIds
    ): array {
        if ($withEval && $withRecommendations) {
            $recsSetsObj = [
                'uuid' => 'uuid',
                'label1' => 'label1',
                'label2' => 'label2',
                'label3' => 'label3',
                'label4' => 'label4',
            ];

            $return['recSets'] = [];
            $recommandationsSets = $this->get('recommandationSetTable')->getEntityByFields(['anr' => $instance->get('anr')->get('id')]);
            foreach ($recommandationsSets as $recommandationSet) {
                $return['recSets'][$recommandationSet->getUuid()] = $recommandationSet->getJsonArray($recsSetsObj);
            }
        }

        $recoIds = [];
        if ($withEval && $withRecommendations && !empty($riskIds) && $this->get('recommandationRiskTable')) {
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
            $return['recos'] = [];
            if (!$withUnlinkedRecommendations) {
                $return['recs'] = [];
            }
            $recoRisk = $this->get('recommandationRiskTable')->getEntityByFields(['anr' => $instance->get('anr')->get('id'), 'instanceRisk' => $riskIds], ['id' => 'ASC']);
            foreach ($recoRisk as $rr) {
                if (!empty($rr)) {
                    $recommendation = $rr->getRecommandation();
                    $recommendationUuid = $recommendation->getUuid();
                    $return['recos'][$rr->get('instanceRisk')->get('id')][$recommendationUuid] = $rr->get('recommandation')->getJsonArray($recosObj);
                    $return['recos'][$rr->get('instanceRisk')->get('id')][$recommendationUuid]['recommandationSet'] = $rr->getRecommandation()->getRecommandationSet()->getUuid();
                    $return['recos'][$rr->get('instanceRisk')->get('id')][$recommendationUuid]['commentAfter'] = $rr->get('commentAfter');
                    if (!$withUnlinkedRecommendations && !isset($recoIds[$recommendationUuid])) {
                        $return['recs'][$recommendationUuid] = $recommendation->getJsonArray($recosObj);
                        $return['recs'][$recommendationUuid]['recommandationSet'] = $recommendation->getRecommandationSet()->getUuid();
                    }
                    $recoIds[$recommendationUuid] = $recommendationUuid;
                }
            }
        }
    }
}
