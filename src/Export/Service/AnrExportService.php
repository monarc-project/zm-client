<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Service;

use Monarc\FrontOffice\Model\Entity;
use Monarc\FrontOffice\Model\Table as DeprecatedTable;
use Monarc\FrontOffice\Table;

class AnrExportService
{
    public function __construct(
        private Table\AnrTable $anrTable,
        private Table\InstanceTable $instanceTable,
        private DeprecatedTable\SoaTable $soaTable,
        private Table\DeliveryTable $deliveryTable,
        private Table\AnrInstanceMetadataFieldTable $anrInstanceMetadataFieldTable,
        private AnrInstanceExportService $anrInstanceExportService
    ) {
    }

    public function export(Entity\Anr $anr, array $data): array
    {
        $withEval = isset($data['assessments']) && $data['assessments'];
        $withControls = isset($data['controls']) && $data['controls'];
        $withRecommendations = isset($data['recommendations']) && $data['recommendations'];
        $withMethodSteps = isset($data['methodSteps']) && $data['methodSteps'];
        $withInterviews = isset($data['interviews']) && $data['interviews'];
        $withSoas = isset($data['soas']) && $data['soas'];
        $withRecords = isset($data['records']) && $data['records'];

        $filename = preg_replace("/[^a-z0-9\._-]+/i", '', $anr->getLabel());

        // TODO: the variable at the end $exportedAnr is the result of the json_encode($return) below.
        // so we need to move it to a separate method and see how the output can be optimised.
        // therefore the goal to make the import faster.

        $return = [
            'type' => 'anr',
            'monarc_version' => $this->get('configService')->getAppVersion()['appVersion'],
            'export_datetime' => (new \DateTime())->format('Y-m-d H:i:s'),
            'instances' => [],
            'with_eval' => $withEval,
        ];

        // TODO: add the method with order prop...
        $instances = $this->instanceTable->findRootsByAnr($anr, ['position'=>'ASC']);
        $f = '';
        foreach ($instances as $instance) {
            // TODO: Added the InstanceExportService on FO and move there all the exp functionality...
            $return['instances'][$instance->getId()] = $this->anrInstanceExportService->generateExportArray(
                $instance->getId(),
                $f,
                $withEval,
                false,
                $withControls,
                $withRecommendations
            );
        }

        // TODO: add backward compatibility for [now]instanceMetadataFields === [before]anrMetadatasOnInstances
        $return['instanceMetadataFields'] = $this->generateExportArrayOfAnrInstanceMetadataFields($anr);

        if ($withEval) {
            // TODO: Soa functionality is related only to FrontOffice.
            if ($withSoas) {
                // soaScaleComment
                $soaScaleCommentExportService = $this->get('soaScaleCommentExportService');
                $return['soaScaleComment'] = $soaScaleCommentExportService->generateExportArray(
                    $anr
                );

                // referentials
                $return['referentials'] = [];
                $referentialTable = $this->get('referentialTable');
                $referentials = $referentialTable->getEntityByFields(['anr' => $anr->getId()]);
                $referentialsArray = [
                    'uuid' => 'uuid',
                    'label1' => 'label1',
                    'label2' => 'label2',
                    'label3' => 'label3',
                    'label4' => 'label4',
                ];
                foreach ($referentials as $r) {
                    $return['referentials'][$r->getUuid()] = $r->getJsonArray($referentialsArray);
                }

                // measures
                $return['measures'] = [];
                $measureTable = $this->get('measureTable');
                $measures = $measureTable->getEntityByFields(['anr' => $anr->getId()]);
                $measuresArray = [
                    'uuid' => 'uuid',
                    'referential' => 'referential',
                    'category' => 'category',
                    'code' => 'code',
                    'label1' => 'label1',
                    'label2' => 'label2',
                    'label3' => 'label3',
                    'label4' => 'label4',
                    'status' => 'status',
                ];
                foreach ($measures as $m) {
                    $newMeasure = $m->getJsonArray($measuresArray);
                    $newMeasure['referential'] = $m->getReferential()->getUuid();
                    $newMeasure['category'] = $m->getCategory() ?
                        $m->getCategory()->get('label' . $this->getLanguage())
                        : '';
                    $return['measures'][$m->getUuid()] = $newMeasure;
                }

                // measures-measures
                $return['measuresMeasures'] = [];
                $measureMeasureTable = $this->get('measureMeasureTable');
                $measuresMeasures = $measureMeasureTable->getEntityByFields(['anr' => $anr->getId()]);
                foreach ($measuresMeasures as $mm) {
                    $newMeasureMeasure = [];
                    $newMeasureMeasure['father'] = $mm->getFather();
                    $newMeasureMeasure['child'] = $mm->getChild();
                    $return['measuresMeasures'][] = $newMeasureMeasure;
                }

                // soacategories
                $return['soacategories'] = [];
                $soaCategoryTable = $this->get('soaCategoryTable');
                $soaCategories = $soaCategoryTable->getEntityByFields(['anr' => $anr->getId()]);
                $soaCategoriesArray = [
                    'referential' => 'referential',
                    'label1' => 'label1',
                    'label2' => 'label2',
                    'label3' => 'label3',
                    'label4' => 'label4',
                    'status' => 'status',
                ];
                foreach ($soaCategories as $c) {
                    $newSoaCategory = $c->getJsonArray($soaCategoriesArray);
                    $newSoaCategory['referential'] = $c->getReferential()->getUuid();
                    $return['soacategories'][] = $newSoaCategory;
                }

                // soas
                $return['soas'] = [];
                $soaTable = $this->get('soaTable');
                $soas = $soaTable->getEntityByFields(['anr' => $anr->getId()]);
                $soasArray = [
                    'remarks' => 'remarks',
                    'evidences' => 'evidences',
                    'actions' => 'actions',
                    'EX' => 'EX',
                    'LR' => 'LR',
                    'CO' => 'CO',
                    'BR' => 'BR',
                    'BP' => 'BP',
                    'RRA' => 'RRA',
                ];
                foreach ($soas as $s) {
                    $newSoas = $s->getJsonArray($soasArray);
                    if ($s->getSoaScaleComment() !== null) {
                        $newSoas['soaScaleComment'] = $s->getSoaScaleComment()->getId();
                    }
                    $newSoas['measure_id'] = $s->getMeasure()->getUuid();
                    $return['soas'][] = $newSoas;
                }
            }

            // operational risk scales
            /** @var OperationalRiskScalesExportService $operationalRiskScalesExportService */
            $operationalRiskScalesExportService = $this->get('operationalRiskScalesExportService');
            $return['operationalRiskScales'] = $operationalRiskScalesExportService->generateExportArray($anr);

            // scales
            $return['scales'] = [];
            /** @var ScaleTable $scaleTable */
            $scaleTable = $this->get('scaleTable');
            $scales = $scaleTable->findByAnr($anr);
            foreach ($scales as $scale) {
                $return['scales'][$scale->getType()] = [
                    'id' => $scale->getId(),
                    'min' => $scale->getMin(),
                    'max' => $scale->getMax(),
                    'type' => $scale->getType(),
                ];
            }

            /** @var ScaleCommentTable $scaleCommentTable */
            $scaleCommentTable = $this->get('scaleCommentTable');
            $scaleComments = $scaleCommentTable->findByAnr($anr);
            foreach ($scaleComments as $scaleComment) {
                $scaleCommentId = $scaleComment->getId();
                $return['scalesComments'][$scaleCommentId] = [
                    'id' => $scaleCommentId,
                    'scaleIndex' => $scaleComment->getScaleIndex(),
                    'scaleValue' => $scaleComment->getScaleValue(),
                    'comment1' => $scaleComment->getComment(1),
                    'comment2' => $scaleComment->getComment(2),
                    'comment3' => $scaleComment->getComment(3),
                    'comment4' => $scaleComment->getComment(4),
                    'scale' => [
                        'id' => $scaleComment->getScale()->getId(),
                        'type' => $scaleComment->getScale()->getType(),
                    ],
                ];
                if ($scaleComment->getScaleImpactType() !== null) {
                    $return['scalesComments'][$scaleCommentId]['scaleImpactType'] = [
                        'id' => $scaleComment->getScaleImpactType()->getId(),
                        'type' => $scaleComment->getScaleImpactType()->getType(),
                        'position' => $scaleComment->getScaleImpactType()->getPosition(),
                        'labels' => [
                            'label1' => $scaleComment->getScaleImpactType()->getLabel(1),
                            'label2' => $scaleComment->getScaleImpactType()->getLabel(2),
                            'label3' => $scaleComment->getScaleImpactType()->getLabel(3),
                            'label4' => $scaleComment->getScaleImpactType()->getLabel(4),
                        ],
                        'isSys' => $scaleComment->getScaleImpactType()->isSys(),
                        'isHidden' => $scaleComment->getScaleImpactType()->isHidden(),
                    ];
                }
            }

            if ($withMethodSteps) {
                //Risks analysis method data
                $return['method']['steps'] = [
                    'initAnrContext' => $anr->getInitAnrContext(),
                    'initEvalContext' => $anr->getInitEvalContext(),
                    'initRiskContext' => $anr->getInitRiskContext(),
                    'initDefContext' => $anr->getInitDefContext(),
                    'modelImpacts' => $anr->getModelImpacts(),
                    'modelSummary' => $anr->getModelSummary(),
                    'evalRisks' => $anr->getEvalRisks(),
                    'evalPlanRisks' => $anr->getEvalPlanRisks(),
                    'manageRisks' => $anr->getManageRisks(),
                ];

                $return['method']['data'] = [
                    'contextAnaRisk' => $anr->getContextAnaRisk(),
                    'contextGestRisk' => $anr->getContextGestRisk(),
                    'synthThreat' => $anr->getSynthThreat(),
                    'synthAct' => $anr->getSynthAct(),
                ];



                $deliveryTable = $this->get('deliveryTable');
                for ($i = 0; $i <= 5; $i++) {
                    $deliveries = $deliveryTable->getEntityByFields(
                        ['anr' => $anr->getId(),
                         'typedoc' => $i ],
                        ['id'=>'ASC']
                    );
                    $deliveryArray = [
                        'id' => 'id',
                        'typedoc' => 'typedoc',
                        'name' => 'name',
                        'status' => 'status',
                        'version' => 'version',
                        'classification' => 'classification',
                        'respCustomer' => 'respCustomer',
                        'respSmile' => 'respSmile',
                        'summaryEvalRisk' => 'summaryEvalRisk',
                    ];
                    foreach ($deliveries as $d) {
                        $return['method']['deliveries'][$d->typedoc] = $d->getJsonArray($deliveryArray);
                    }
                }
                $questionTable = $this->get('questionTable');
                $questions = $questionTable->getEntityByFields(['anr' => $anr->getId()], ['position'=>'ASC']);
                $questionArray = [
                    'id' => 'id',
                    'mode' => 'mode',
                    'multichoice' => 'multichoice',
                    'label1' => 'label1',
                    'label2' => 'label2',
                    'label3' => 'label3',
                    'label4' => 'label4',
                    'response' => 'response',
                    'type' => 'type',
                    'position' => 'position',

                ];

                foreach ($questions as $q) {
                    $return['method']['questions'][$q->position] = $q->getJsonArray($questionArray);
                }

                $questionChoiceTable = $this->get('questionChoiceTable');
                $questionsChoices = $questionChoiceTable->getEntityByFields(['anr' => $anr->getId()]);
                $questionChoiceArray = [
                    'question' => 'question',
                    'position' => 'position',
                    'label1' => 'label1',
                    'label2' => 'label2',
                    'label3' => 'label3',
                    'label4' => 'label4',
                ];
                foreach ($questionsChoices as $qc) {
                    $return['method']['questionChoice'][$qc->id] = $qc->getJsonArray($questionChoiceArray);
                    $return['method']['questionChoice'][$qc->id]['question'] = $qc->question->id;
                }
            }
            //import thresholds
            $return['method']['thresholds'] = [
                'seuil1' => $anr->getSeuil1(),
                'seuil2' => $anr->getSeuil2(),
                'seuilRolf1' => $anr->getSeuilRolf1(),
                'seuilRolf2' => $anr->getSeuilRolf2(),
            ];
            // manage the interviews
            if ($withInterviews) {
                $interviewTable = $this->get('interviewTable');
                $interviews = $interviewTable->getEntityByFields(['anr' => $anr->getId()], ['id'=>'ASC']);
                $interviewArray = [
                    'id' => 'id',
                    'date' => 'date',
                    'service' => 'service',
                    'content' => 'content',
                ];

                foreach ($interviews as $i) {
                    $return['method']['interviews'][$i->id] = $i->getJsonArray($interviewArray);
                }
            }

            // TODO: This is only used on FO side.
            /** @var ThreatTable $threatTable */
            $threatTable = $this->get('threatTable');
            /** @var ThreatSuperClass[] $threats */
            $threats = $threatTable->findByAnr($anr);
            $threatArray = [
                'uuid' => 'uuid',
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
                'trend' => 'trend',
                'comment' => 'comment',
                'qualification' => 'qualification',
            ];


            foreach ($threats as $threat) {
                $threatUuid = $threat->getUuid();
                // TODO: ....
                $return['method']['threats'][$threatUuid] = $threat->getJsonArray($threatArray);
                if ($threat->getTheme() !== null) {
                    $return['method']['threats'][$threatUuid]['theme']['id'] = $threat->getTheme()->getId();
                    $return['method']['threats'][$threatUuid]['theme']['label1'] = $threat->getTheme()->getLabel(1);
                    $return['method']['threats'][$threatUuid]['theme']['label2'] = $threat->getTheme()->getLabel(2);
                    $return['method']['threats'][$threatUuid]['theme']['label3'] = $threat->getTheme()->getLabel(3);
                    $return['method']['threats'][$threatUuid]['theme']['label4'] = $threat->getTheme()->getLabel(4);
                }
            }

            // manage the GDPR records
            if ($withRecords) {
                $recordService = $this->get('recordService');
                $table = $this->get('recordTable');
                $records = $table->getEntityByFields(['anr' => $anr->getId()], ['id'=>'ASC']);
                $f = '';
                foreach ($records as $r) {
                    $return['records'][$r->id] = $recordService->generateExportArray($r->id, $f);
                }
            }
        }

        if (!empty(json_encode($data['password'])) {
            $exportedAnr = $this->encrypt($exportedAnr, $data['password']);
        }

        return $exportedAnr;
    }

    private function generateExportArrayOfAnrInstanceMetadataFields(Entity\Anr $anr): array
    {
        $result = [];
        /** @var Entity\AnrInstanceMetadataField $anrInstanceMetadata */
        foreach ($this->anrInstanceMetadataFieldTable->findByAnr($anr) as $anrInstanceMetadata) {
            $id = $anrInstanceMetadata->getId();
            $result[$id] = ['id' => $id, 'label' => $anrInstanceMetadata->getLabel()];
        }

        return $result;
    }
}
