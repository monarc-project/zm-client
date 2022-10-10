<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use \Monarc\Core\Model\Entity\Scale;
use \Monarc\Core\Model\Entity\OperationalRiskScale;
use \Monarc\Core\Model\Entity\MonarcObject;
use Monarc\FrontOffice\Model\Table\OperationalRiskScaleTable;
use Monarc\FrontOffice\Model\Table\ScaleTable;

/**
 * This class is the service that handles the ANR Cartography of real & targeted risks (as shown on the dashboard)
 * @package Monarc\FrontOffice\Service
 */
class AnrCartoRiskService extends \Monarc\Core\Service\AbstractService
{
    protected $anrTable;
    protected $userAnrTable;
    protected $instanceTable;
    protected $instanceRiskTable;
    protected $instanceRiskOpTable;
    protected $instanceConsequenceTable;
    protected $operationalRiskScaleTable;
    protected $threatTable;
    protected $filterColumns = [];
    protected $dependencies = [];
    private $anr = null;
    private $listScales = null;
    private $listOpRiskScales = null;
    private $headers = null;

    /**
     * Computes and returns the cartography of real risks
     * @param int $anrId The ANR ID
     * @return array An associative array of Impact, MxV, counters and distrib to display as a table
     */
    public function getCartoReal($anrId)
    {
        $this->buildListScalesAndHeaders($anrId);
        $this->buildListScalesOpRisk($anrId);

        list($counters, $distrib, $riskMaxSum) = $this->getCountersRisks('raw');
        list($countersRiskOP, $distribRiskOp, $riskOpMaxSum) = $this->getCountersOpRisks('raw');

        return [
            'Impact' => $this->listScales[Scale::TYPE_IMPACT],
            'Probability' => $this->listScales[Scale::TYPE_THREAT],
            'OpRiskImpact' => $this->listOpRiskScales[OperationalRiskScale::TYPE_IMPACT],
            'Likelihood' => $this->listOpRiskScales[OperationalRiskScale::TYPE_LIKELIHOOD],
            'MxV' => $this->headers,
            'riskInfo' => [
              'counters' => $counters,
              'distrib' => $distrib,
              'riskMaxSum' => $riskMaxSum,
            ],
            'riskOp' => [
              'counters' => $countersRiskOP,
              'distrib' => $distribRiskOp,
              'riskOpMaxSum' => $riskOpMaxSum,
            ],
        ];
    }

    /**
     * Computes and returns the cartography of targeted risks
     * @param int $anrId The ANR ID
     * @return array An associative array of Impact (rows), MxV (columns), counters and distrib to display as a table
     */
    public function getCartoTargeted($anrId)
    {
        $this->buildListScalesAndHeaders($anrId);
        $this->buildListScalesOpRisk($anrId);

        list($counters, $distrib, $riskMaxSum) = $this->getCountersRisks('target');
        list($countersRiskOP, $distribRiskOp, $riskOpMaxSum) = $this->getCountersOpRisks('target');

        return [
            'Impact' => $this->listScales[Scale::TYPE_IMPACT],
            'Probability' => $this->listScales[Scale::TYPE_THREAT],
            'OpRiskImpact' => $this->listOpRiskScales[OperationalRiskScale::TYPE_IMPACT],
            'Likelihood' => $this->listOpRiskScales[OperationalRiskScale::TYPE_LIKELIHOOD],
            'MxV' => $this->headers,
            'riskInfo' => [
              'counters' => $counters,
              'distrib' => $distrib,
              'riskMaxSum' => $riskMaxSum,
            ],
            'riskOp' => [
              'counters' => $countersRiskOP,
              'distrib' => $distribRiskOp,
              'riskOpMaxSum' => $riskOpMaxSum,
            ],
        ];
      }

    /**
     * Computes and builds the List Scales and headers for the table (Impact and MxV fields)
     * @param int $anrId The ANR ID
     */
    public function buildListScalesAndHeaders($anrId)
    {
        // Only load the ANR if we don't have the ANR already loaded, or a different one.
        if (!$this->anr || $this->anr->get('id') != $anrId) {
            $this->anr = $this->get('anrTable')->getEntity($anrId);
        }

        // Only compute the listScales and headers fields if we didn't already
        // TODO: If we reuse the service to build the carto for 2 different ANRs in the same run, this will cause issues!
        if ($this->listScales === null) {
            /** @var ScaleTable $scaleTable */
            $scaleTable = $this->get('table');
            $scales = $scaleTable->findByAnr($this->anr);

            $this->listScales = [
                Scale::TYPE_IMPACT => [],
                Scale::TYPE_THREAT => [],
                Scale::TYPE_VULNERABILITY => [],
            ];
            foreach ($scales as $scale) {
                $this->listScales[$scale->getType()] = range($scale->getMin(), $scale->getMax());
            }
        }

        if ($this->headers === null) {
            $this->headers = [];
            foreach ($this->listScales[Scale::TYPE_IMPACT] as $i) {
                foreach ($this->listScales[Scale::TYPE_THREAT] as $m) {
                    foreach ($this->listScales[Scale::TYPE_VULNERABILITY] as $v) {
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
     * Computes and builds the List Scales for the operational risk table (Impact and likelihood fields)
     * @param int $anrId The ANR ID
     */
    public function buildListScalesOpRisk($anrId)
    {
        // Only load the ANR if we don't have the ANR already loaded, or a different one.
        if (!$this->anr || $this->anr->get('id') != $anrId) {
            $this->anr = $this->get('anrTable')->getEntity($anrId);
        }

        // Only compute the listScales and headers fields if we didn't already
        if ($this->listOpRiskScales === null) {
            /** @var OperationalRiskScaleTable $operationalRiskScaleTable */
            $operationalRiskScaleTable = $this->get('operationalRiskScaleTable');
            $likelihoodScale = current($operationalRiskScaleTable->findWithCommentsByAnrAndType($this->anr, OperationalRiskScale::TYPE_LIKELIHOOD));
            $impactsScale = current($operationalRiskScaleTable->findWithCommentsByAnrAndType($this->anr, OperationalRiskScale::TYPE_IMPACT));
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

            $this->listOpRiskScales[OperationalRiskScale::TYPE_IMPACT] = $impactScaleValues;
            $this->listOpRiskScales[OperationalRiskScale::TYPE_LIKELIHOOD] = range($likelihoodScale->getMin(),$likelihoodScale->getMax());
        }
    }

    /**
     * Calculates the number of risks for each impact/MxV combo
     * @param string $mode The mode to use, either 'raw' or 'target'
     * @return array Associative array of values to show in the table
     */
    public function getCountersRisks($mode = 'raw')
    {
        // On croise avec les données des risques
        $changeField = $mode == 'raw' ? 'ir.cacheMaxRisk' : 'ir.cacheTargetedRisk';
        $query = $this->get('instanceRiskTable')->getRepository()->createQueryBuilder('ir');
        $result = $query->select([
            'ir.id as myid', 'IDENTITY(ir.amv) as amv', 'IDENTITY(ir.asset) as asset', 'IDENTITY(ir.threat) as threat', 'IDENTITY(ir.vulnerability) as vulnerability', $changeField . ' as maximus',
            'i.c as ic', 'i.i as ii', 'i.d as id', 'IDENTITY(i.object) as object',
            'm.c as mc', 'm.i as mi', 'm.a as ma',
            'o.scope',
        ])->where('ir.anr = :anrid')
            ->setParameter(':anrid', $this->anr->get('id'))
            ->andWhere($changeField . " != -1")
            ->innerJoin('ir.instance', 'i')
            ->innerJoin('ir.threat', 'm')
            ->innerJoin('i.object', 'o')->getQuery()->getResult();

        $counters = $distrib = $riskMaxSum = $temp = [];
        foreach ($result as $r) {
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
                'color' => $this->getColor($max,'riskInfo'),
                'uuid' => $r['amv']
            ];

            // on est obligé de faire l'algo en deux passes pour pouvoir compter les objets globaux qu'une seule fois
            if ($r['scope'] == MonarcObject::SCOPE_GLOBAL) {
                if (!isset($temp[$r['object']][$context['amv']][0])) {
                    // dans ce cas pas grand chose à faire on doit stocker le context local
                    $temp[$r['object']][$context['amv']][0] = $context;
                } else {
                    // dans ce cas on doit comparer la valeur max qu'on a. Si c'est plus haut alors on remplace par le contexte courant
                    $cur = $temp[$r['object']][$context['amv']][0];

                    // Si on a un max plus grand, on le remplace, sinon on ne fait rien
                    if ($context['max'] > $cur['max']) {
                        unset($temp[$r['object']][$context['amv']][0]);
                        $temp[$r['object']][$context['amv']][0] = $context;
                    }
                }
            } else {
                // pour les locaux, l'amv peut exister plusieurs fois sur le même biblio, du coup pour bien les compter plusieurs fois on rajoute
                $temp[$r['object']][$context['amv']][$r['myid']] = $context;
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
                        $counters[$context['impact']][$context['right']] = [];
                    }

                    if (!isset($distrib[$context['color']])) {
                        $distrib[$context['color']] = [];
                    }

                    if (!isset($riskMaxAverage[$context['color']])) {
                        $riskMaxAverage[$context['color']] = 0;
                    }

                    array_push($counters[$context['impact']][$context['right']],$context['uuid']);
                    array_push($distrib[$context['color']],$context['uuid']);
                    $riskMaxSum[$context['color']] += $context['max'];
                }
            }
        }

        return [$counters, $distrib, $riskMaxSum];
    }

    /**
     * Calculates the number of operational risks for each impact/probabiliy combo
     * @param string $mode The mode to use, either 'raw' or 'target'
     * @return array Associative array of values to show in the table
     */
    public function getCountersOpRisks($mode = 'raw')
    {
        $valuesField = ['IDENTITY(iro.rolfRisk) as id', 'iro.netProb as netProb','iro.targetedProb as targetedProb'];
        $query = $this->get('instanceRiskOpTable')->getRepository()->createQueryBuilder('iro');
        $result = $query->select([
            'iro as instanceRiskOp', 'iro.cacheNetRisk as netRisk', 'iro.cacheTargetedRisk as targetedRisk',
            implode(',', $valuesField)
        ])->where('iro.anr = :anrid')
            ->setParameter(':anrid', $this->anr->get('id'))
            ->andWhere("iro.cacheNetRisk != -1")
            ->getQuery()->getResult();


        $countersRiskOP = $distribRiskOp = $riskOpMaxSum = $temp = [];
        foreach ($result as $r) {
            foreach ($r['instanceRiskOp']->getOperationalInstanceRiskScales() as $operationalInstanceRiskScale) {
                $operationalRiskScaleType = $operationalInstanceRiskScale->getOperationalRiskScaleType();
                $scalesData[$operationalRiskScaleType->getId()] = [
                    'netValue' => $operationalInstanceRiskScale->getNetValue(),
                    'targetedValue' => $operationalInstanceRiskScale->getTargetedValue(),
                ];
            }
            if ($mode == 'raw' || $r['targetedRisk'] == -1) {
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

            $id = $r['id'];
            $color = $this->getColor($max, 'riskOp');

            if (!isset($countersRiskOP[$imax][$prob])) {
                $countersRiskOP[$imax][$prob] = [];
            }

            if (!isset($distribRiskOp[$color])) {
                $distribRiskOp[$color] = [];
            }

            if (!isset($riskOpMaxSum[$color])) {
                $riskOpMaxSum[$color] = 0;
            }

            array_push($countersRiskOP[$imax][$prob],$r['id']);
            array_push($distribRiskOp[$color],$r['id']);
            $riskOpMaxSum[$color] += $max;
        }

        return [$countersRiskOP, $distribRiskOp, $riskOpMaxSum];

    }

    /**
     * Returns the cell color to display for the provided risk value
     * @param int $val The risk value
     * @return int|string 0, 1, 2 corresponding to low/med/hi risk color, or an empty string in case of invalid value
     */
    private function getColor($val,$kindOfRisk = 'riskInfo')
    {
        // Provient de l'ancienne version, on ne remonte que les valeurs '' / 0 / 1 / 2, les couleurs seront traitées par le FE
        if ($val == -1 || is_null($val)) {
            return '';
        }
        if ($kindOfRisk == 'riskInfo') {
          if ($val <= $this->anr->get('seuil1')) {
              return 0;
          }
          if ($val <= $this->anr->get('seuil2')) {
              return 1;
          }
        } else {
          if ($val <= $this->anr->get('seuilRolf1')) {
              return 0;
          }
          if ($val <= $this->anr->get('seuilRolf2')) {
              return 1;
          }
        }
        return 2;
    }
}
