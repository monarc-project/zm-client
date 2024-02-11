<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Model\Entity as CoreEntity;
use Monarc\Core\Service\Helper\ScalesCacheHelper;
use Monarc\FrontOffice\Model\Entity;
use Monarc\FrontOffice\Table;

/**
 * This class is the service that handles the ANR Cartography of real & targeted risks (as shown on the dashboard).
 */
class AnrCartoRiskService
{
    private array $listScales;
    private array $listOpRiskScales;
    private array $headers;

    public function __construct(
        private ScalesCacheHelper $scalesCacheHelper,
        private Table\InstanceRiskTable $instanceRiskTable,
        private Table\InstanceRiskOpTable $instanceRiskOpTable,
        private Table\OperationalRiskScaleTable $operationalRiskScaleTable
    ) {
    }

    /**
     * Computes and returns the cartography of real risks.
     *
     * @return array An associative array of Impact, MxV, counters and distrib to display as a table
     */
    public function getCartoReal(Entity\Anr $anr)
    {
        $this->buildListScalesAndHeaders($anr);
        $this->buildListScalesOpRisk($anr);

        [$counters, $distrib, $riskMaxSum, $byTreatment] = $this->getCountersRisks($anr);
        [$countersRiskOP, $distribRiskOp, $riskOpMaxSum, $byTreatmentRiskOp] = $this->getCountersOpRisks($anr);

        return [
            'Impact' => $this->listScales[CoreEntity\ScaleSuperClass::TYPE_IMPACT],
            'Probability' => $this->listScales[CoreEntity\ScaleSuperClass::TYPE_THREAT],
            'OpRiskImpact' => $this->listOpRiskScales[CoreEntity\OperationalRiskScaleSuperClass::TYPE_IMPACT],
            'Likelihood' => $this->listOpRiskScales[CoreEntity\OperationalRiskScaleSuperClass::TYPE_LIKELIHOOD],
            'MxV' => $this->headers,
            'riskInfo' => [
                'counters' => $counters,
                'distrib' => $distrib,
                'riskMaxSum' => $riskMaxSum,
                'byTreatment' => $byTreatment,
            ],
            'riskOp' => [
                'counters' => $countersRiskOP,
                'distrib' => $distribRiskOp,
                'riskOpMaxSum' => $riskOpMaxSum,
                'byTreatment' => $byTreatmentRiskOp,
            ],
        ];
    }

    /**
     * Computes and returns the cartography of targeted risks.
     *
     * @return array An associative array of Impact (rows), MxV (columns), counters and distrib to display as a table.
     */
    public function getCartoTargeted(Entity\Anr $anr): array
    {
        $this->buildListScalesAndHeaders($anr);
        $this->buildListScalesOpRisk($anr);

        [$counters, $distrib, $riskMaxSum, $byTreatment] = $this->getCountersRisks($anr, 'target');
        [$countersRiskOP, $distribRiskOp, $riskOpMaxSum, $byTreatmentRiskOp] = $this->getCountersOpRisks(
            $anr,
            'target'
        );

        return [
            'Impact' => $this->listScales[CoreEntity\ScaleSuperClass::TYPE_IMPACT],
            'Probability' => $this->listScales[CoreEntity\ScaleSuperClass::TYPE_THREAT],
            'OpRiskImpact' => $this->listOpRiskScales[CoreEntity\OperationalRiskScaleSuperClass::TYPE_IMPACT],
            'Likelihood' => $this->listOpRiskScales[CoreEntity\OperationalRiskScaleSuperClass::TYPE_LIKELIHOOD],
            'MxV' => $this->headers,
            'riskInfo' => [
                'counters' => $counters,
                'distrib' => $distrib,
                'riskMaxSum' => $riskMaxSum,
                'byTreatment' => $byTreatment,
            ],
            'riskOp' => [
                'counters' => $countersRiskOP,
                'distrib' => $distribRiskOp,
                'riskOpMaxSum' => $riskOpMaxSum,
                'byTreatment' => $byTreatmentRiskOp,
            ],
        ];
    }

    /**
     * Computes and builds the List Scales and headers for the table (Impact and MxV fields).
     */
    public function buildListScalesAndHeaders(Entity\Anr $anr)
    {
        // Only compute the listScales and headers fields if we didn't already
        // TODO: If we reuse the service to build the carto for 2 different ANRs in the same run,
        // this will cause issues!
        if ($this->listScales === null) {
            $this->listScales = [];
            foreach ($this->scalesCacheHelper->getCachedScales($anr) as $scale) {
                $this->listScales[$scale->getType()] = range($scale->getMin(), $scale->getMax());
            }
        }

        if ($this->headers === null) {
            $this->headers = [];
            foreach ($this->listScales[CoreEntity\ScaleSuperClass::TYPE_IMPACT] as $i) {
                foreach ($this->listScales[CoreEntity\ScaleSuperClass::TYPE_THREAT] as $m) {
                    foreach ($this->listScales[CoreEntity\ScaleSuperClass::TYPE_VULNERABILITY] as $v) {
                        $val = -1;
                        if ($i !== -1 && $m !== -1 && $v !== -1) {
                            $val = $m * $v;
                        }
                        if (!\in_array($val, $this->headers, true)) {
                            $this->headers[] = $val;
                        }
                    }
                }
            }
            sort($this->headers);
        }
    }

    /**
     * Computes and builds the List Scales for the operational risk table (Impact and likelihood fields).
     */
    public function buildListScalesOpRisk(Entity\Anr $anr)
    {
        // Only compute the listScales and headers fields if we didn't already
        if ($this->listOpRiskScales === null) {
            $likelihoodScale = current($this->operationalRiskScaleTable->findWithCommentsByAnrAndType(
                $anr,
                CoreEntity\OperationalRiskScaleSuperClass::TYPE_LIKELIHOOD
            ));
            $impactsScale = current($this->operationalRiskScaleTable->findWithCommentsByAnrAndType(
                $anr,
                CoreEntity\OperationalRiskScaleSuperClass::TYPE_IMPACT
            ));
            $impactScaleTypes = $impactsScale->getOperationalRiskScaleTypes();
            $impactScaleComments = $impactScaleTypes[0]->getOperationalRiskScaleComments();

            foreach ($impactScaleComments as $comment) {
                if (!$comment->isHidden()) {
                    $impactScaleValues[] = $comment->getScaleValue();
                }
            }

            usort($impactScaleValues, static function ($a, $b) {
                return $a <=> $b;
            });

            $this->listOpRiskScales[CoreEntity\OperationalRiskScaleSuperClass::TYPE_IMPACT] = $impactScaleValues;
            $this->listOpRiskScales[CoreEntity\OperationalRiskScaleSuperClass::TYPE_LIKELIHOOD] = range(
                $likelihoodScale->getMin(),
                $likelihoodScale->getMax()
            );
        }
    }

    /**
     * Calculates the number of risks for each impact/MxV combo.
     *
     * @param string $mode The mode to use, either 'raw' or 'target'
     *
     * @return array Associative array of values to show in the table
     */
    public function getCountersRisks(Entity\Anr $anr, string $mode = 'raw')
    {
        $temp = [];
        $riskMaxSum = $temp;
        $distrib = $temp;
        $counters = $temp;
        $byTreatment = [
            'treated' => [],
            'not_treated' => [],
            'reduction' => [],
            'denied' => [],
            'accepted' => [],
            'shared' => [],
            'all' => [
                'reduction' => [],
                'denied' => [],
                'accepted' => [],
                'shared' => [],
                'not_treated' => [],
            ],
        ];

        $riskValueField = $mode === 'raw' ? 'ir.cacheMaxRisk' : 'ir.cacheTargetedRisk';
        foreach ($this->instanceRiskTable->findRisksValuesForCartoStatsByAnr($anr, $riskValueField) as $r) {
            /** @var Entity\InstanceRisk $instanceRisk */
            $instanceRisk = $r['instanceRisk'];
            if (!isset($r['threat']) || !isset($r['vulnerability'])) {
                continue;
            }

            // on détermine le contexte de travail
            // A. Quel est l'impact MAX au regard du masque CID de la menace
            $c = $i = $d = 0;
            if ($r['mc']) {
                $c = $r['ic'];
            }
            if ($r['mi']) {
                $i = $r['ii'];
            }
            if ($r['ma']) {
                $d = $r['id'];
            }

            $imax = max($c, $i, $d);
            $max = $r['maximus'];
            $right = $imax > 0 ? round($max / $imax) : 0;

            $context = [
                'impact' => $imax,
                'right' => $right,
                'amv' => $r['asset'] . ';' . $r['threat'] . ';' . $r['vulnerability'],
                'max' => $max,
                'color' => $this->getColor($anr, $max),
                'treatment' => $instanceRisk->getTreatmentServiceName(),
                'isTreated' => $instanceRisk->isTreated(),
            ];

            // on est obligé de faire l'algo en deux passes pour pouvoir compter les objets globaux qu'une seule fois
            if ($r['scope'] === CoreEntity\ObjectSuperClass::SCOPE_GLOBAL) {
                if (!isset($temp[$r['object']][$context['amv']][0])) {
                    // dans ce cas pas grand chose à faire on doit stocker le context local
                    $temp[$r['object']][$context['amv']][0] = $context;
                } else {
                    // dans ce cas on doit comparer la valeur max qu'on a. Si c'est plus haut alors on remplace par le
                    // contexte courant
                    $cur = $temp[$r['object']][$context['amv']][0];

                    // Si on a un max plus grand, on le remplace, sinon on ne fait rien
                    if ($context['max'] > $cur['max']) {
                        unset($temp[$r['object']][$context['amv']][0]);
                        $temp[$r['object']][$context['amv']][0] = $context;
                    }
                }
            } else {
                // pour les locaux, l'amv peut exister plusieurs fois sur le même biblio, du coup pour bien les
                // compter plusieurs fois on rajoute
                $temp[$r['object']][$context['amv']][$instanceRisk->getId()] = $context;
            }
        }

        // le premier algo nous a permis d'isoler les maximus des globaux pour ne les compter qu'une seule fois
        // maintenant il faut compter de manière à pouvoir distribuer cela dans la matrice
        foreach ($temp as $risks) {
            foreach ($risks as $contexts) {
                foreach ($contexts as $context) {
                    if ($context['impact'] < 0) {
                        continue;
                    }

                    if (!isset($counters[$context['impact']][$context['right']])) {
                        $counters[$context['impact']][$context['right']] = 0;
                    }

                    if (!isset($distrib[$context['color']])) {
                        $distrib[$context['color']] = 0;
                    }

                    if (!isset($riskMaxSum[$context['color']])) {
                        $riskMaxSum[$context['color']] = 0;
                    }

                    $counters[$context['impact']][$context['right']] += 1;
                    ++$distrib[$context['color']];
                    $riskMaxSum[$context['color']] += $context['max'];

                    if ($context['isTreated'] !== 5) {
                        if (!isset($byTreatment['treated'][$context['color']]['count'])) {
                            $byTreatment['treated'][$context['color']]['count'] = 0;
                        }

                        if (!isset($byTreatment['treated'][$context['color']]['sum'])) {
                            $byTreatment['treated'][$context['color']]['sum'] = 0;
                        }

                        $byTreatment['treated'][$context['color']]['count'] += 1;
                        $byTreatment['treated'][$context['color']]['sum'] += $context['max'];
                    }

                    $kindOfMeasure = $context['treatment'];
                    if (!isset($byTreatment['all'][$kindOfMeasure]['count'])) {
                        $byTreatment['all'][$kindOfMeasure]['count'] = 0;
                    }

                    if (!isset($byTreatment['all'][$kindOfMeasure]['sum'])) {
                        $byTreatment['all'][$kindOfMeasure]['sum'] = 0;
                    }

                    if (!isset($byTreatment[$kindOfMeasure][$context['color']]['count'])) {
                        $byTreatment[$kindOfMeasure][$context['color']]['count'] = 0;
                    }

                    if (!isset($byTreatment[$kindOfMeasure][$context['color']]['sum'])) {
                        $byTreatment[$kindOfMeasure][$context['color']]['sum'] = 0;
                    }

                    $byTreatment[$kindOfMeasure][$context['color']]['count'] += 1;
                    $byTreatment[$kindOfMeasure][$context['color']]['sum'] += $context['max'];

                    $byTreatment['all'][$kindOfMeasure]['count'] += 1;
                    $byTreatment['all'][$kindOfMeasure]['sum'] += $context['max'];
                }
            }
        }

        return [$counters, $distrib, $riskMaxSum, $byTreatment];
    }

    /**
     * Calculates the number of operational risks for each impact/probability combo.
     *
     * @param string $mode The mode to use, either 'raw' or 'target'
     *
     * @return array Associative array of values to show in the table
     */
    public function getCountersOpRisks(Entity\Anr $anr, string $mode = 'raw')
    {
        $riskOpMaxSum = [];
        $distribRiskOp = $riskOpMaxSum;
        $countersRiskOP = $riskOpMaxSum;
        $byTreatment = [
            'treated' => [],
            'not_treated' => [],
            'reduction' => [],
            'denied' => [],
            'accepted' => [],
            'shared' => [],
            'all' => [
                'reduction' => [],
                'denied' => [],
                'accepted' => [],
                'shared' => [],
                'not_treated' => [],
            ],
        ];

        foreach ($this->instanceRiskOpTable->findRisksValuesForCartoStatsByAnr($anr) as $r) {
            /** @var Entity\InstanceRiskOp $operationalInstanceRisk */
            $operationalInstanceRisk = $r['instanceRiskOp'];
            foreach ($operationalInstanceRisk->getOperationalInstanceRiskScales() as $operationalInstanceRiskScale) {
                $operationalRiskScaleType = $operationalInstanceRiskScale->getOperationalRiskScaleType();
                $scalesData[$operationalRiskScaleType->getId()] = [
                    'netValue' => $operationalInstanceRiskScale->getNetValue(),
                    'targetedValue' => $operationalInstanceRiskScale->getTargetedValue(),
                ];
            }
            if ($mode === 'raw' || $r['targetedRisk'] === -1) {
                $imax = array_reduce($scalesData, function ($a, $b) {
                    return $a ? ($a['netValue'] > $b['netValue'] ? $a : $b) : $b;
                });
                $imax = $imax['netValue'];
                $max = $r['netRisk'];
                $prob = $r['netProb'];
            } else {
                $imax = array_reduce($scalesData, function ($a, $b) {
                    return $a ? ($a['targetedValue'] > $b['targetedValue'] ? $a : $b) : $b;
                });
                $imax = $imax['targetedValue'];
                $max = $r['targetedRisk'];
                $prob = $r['targetedProb'];
            }
            $color = $this->getColor($anr, $max, 'riskOp');

            if (!isset($countersRiskOP[$imax][$prob])) {
                $countersRiskOP[$imax][$prob] = 0;
            }

            if (!isset($distribRiskOp[$color])) {
                $distribRiskOp[$color] = 0;
            }

            if (!isset($riskOpMaxSum[$color])) {
                $riskOpMaxSum[$color] = 0;
            }

            $countersRiskOP[$imax][$prob] += 1;
            ++$distribRiskOp[$color];
            $riskOpMaxSum[$color] += $max;

            if ($operationalInstanceRisk->isTreated()) {
                if (!isset($byTreatment['treated'][$color]['count'])) {
                    $byTreatment['treated'][$color]['count'] = 0;
                }

                if (!isset($byTreatment['treated'][$color]['sum'])) {
                    $byTreatment['treated'][$color]['sum'] = 0;
                }

                $byTreatment['treated'][$color]['count'] += 1;
                $byTreatment['treated'][$color]['sum'] += $max;
            }

            $kindOfMeasure = $operationalInstanceRisk->getTreatmentServiceName();

            if (!isset($byTreatment['all'][$kindOfMeasure]['count'])) {
                $byTreatment['all'][$kindOfMeasure]['count'] = 0;
            }

            if (!isset($byTreatment['all'][$kindOfMeasure]['sum'])) {
                $byTreatment['all'][$kindOfMeasure]['sum'] = 0;
            }

            if (!isset($byTreatment[$kindOfMeasure][$color]['count'])) {
                $byTreatment[$kindOfMeasure][$color]['count'] = 0;
            }

            if (!isset($byTreatment[$kindOfMeasure][$color]['sum'])) {
                $byTreatment[$kindOfMeasure][$color]['sum'] = 0;
            }

            $byTreatment[$kindOfMeasure][$color]['count'] += 1;
            $byTreatment[$kindOfMeasure][$color]['sum'] += $max;

            $byTreatment['all'][$kindOfMeasure]['count'] += 1;
            $byTreatment['all'][$kindOfMeasure]['sum'] += $max;
        }

        return [$countersRiskOP, $distribRiskOp, $riskOpMaxSum, $byTreatment];
    }

    /**
     * Returns the cell color to display for the provided risk value
     *
     * @param int|null $val The risk value
     *
     * @return int|string 0, 1, 2 corresponding to low/med/hi risk color, or an empty string in case of invalid value
     */
    private function getColor(Entity\Anr $anr, ?int $val, string $riskType = 'riskInfo')
    {
        // Provient de l'ancienne version, on ne remonte que les valeurs '' / 0 / 1 / 2, les couleurs seront traitées
        // par le FE
        if ($val === null || $val === -1) {
            return '';
        }

        if ($riskType === 'riskInfo') {
            if ($val <= $anr->getSeuil1()) {
                return 0;
            }
            if ($val <= $anr->getSeuil2()) {
                return 1;
            }
        } elseif ($riskType === 'riskOp') {
            if ($val <= $anr->getSeuilRolf1()) {
                return 0;
            }
            if ($val <= $anr->getSeuilRolf2()) {
                return 1;
            }
        }

        return 2;
    }
}
