<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Stats\Service;

use DateTime;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Measure;
use Monarc\FrontOffice\Model\Entity\MonarcObject;
use Monarc\FrontOffice\Model\Entity\Scale;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskOpTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskTable;
use Monarc\FrontOffice\Model\Table\ReferentialTable;
use Monarc\FrontOffice\Model\Table\ScaleTable;
use Monarc\FrontOffice\Stats\DataObject\StatsDataObject;
use Monarc\FrontOffice\Stats\Exception\StatsAlreadyCollectedException;
use Monarc\FrontOffice\Stats\Exception\StatsFetchingException;
use Monarc\FrontOffice\Stats\Exception\StatsSendingException;
use Monarc\FrontOffice\Stats\Provider\StatsApiProvider;

class StatsAnrService
{
    public const LOW_RISKS = 'Low risks';
    public const MEDIUM_RISKS = 'Medium risks';
    public const HIGH_RISKS = 'High risks';

    /** @var AnrTable */
    private $anrTable;

    /** @var ScaleTable */
    private $scaleTable;

    /** @var InstanceRiskTable */
    private $informationalRiskTable;

    /** @var InstanceRiskOpTable */
    private $operationalRiskTable;

    /** @var StatsApiProvider */
    private $statsApiProvider;

    /** @var ReferentialTable */
    private $referentialTable;

    public function __construct(
        AnrTable $anrTable,
        ScaleTable $scaleTable,
        InstanceRiskTable $informationalRiskTable,
        InstanceRiskOpTable $operationalRiskTable,
        ReferentialTable $referentialTable,
        StatsApiProvider $statsApiProvider
    ) {
        $this->anrTable = $anrTable;
        $this->scaleTable = $scaleTable;
        $this->informationalRiskTable = $informationalRiskTable;
        $this->operationalRiskTable = $operationalRiskTable;
        $this->referentialTable = $referentialTable;
        $this->statsApiProvider = $statsApiProvider;
    }

    /**
     * @param array $filterParams Accepts the following params keys:
     *              - dateStart Stats period start date;
     *              - endDate Stats period end date;
     *              - anrIds List of Anr IDs to use for the result;
     *              - aggregationPeriod One of the available options [per day, per week, per month]
     */
    public function getStats(array $filterParams): array
    {
        // TODO: Inject the Authenticated user (get connected user) and validate:
        //  - if the role is SEO we return allow all the analyses to the result, if not filtered by specific ones
        //  - if user role if userfo then we allow to use only accessible for him anrs
        // TODO: call stats-api to get the data.
    }

    /**
     * Collects the statistics for today.
     *
     * @param int[] $anrIds List of Anr IDs to use for the stats collection.
     * @param bool $forceUpdate Whether or not overwrite the data if already presented for today.
     *
     * @throws StatsAlreadyCollectedException
     * @throws StatsFetchingException
     * @throws StatsSendingException
     */
    public function collectStats(array $anrIds = [], bool $forceUpdate = false): void
    {
        $currentDate = new DateTime();
        $statsOfToday = $this->statsApiProvider->getStatsData(['fromDate' => $currentDate, 'toDate' => $currentDate]);
        if (!$forceUpdate && !empty($statsOfToday)) {
            throw new StatsAlreadyCollectedException();
        }

        $anrLists = !empty($anrIds)
            ? $this->anrTable->findByIds($anrIds)
            : $this->anrTable->findAll();

        $statsData = [];
        foreach ($anrLists as $anr) {
            $anrStatsForRtvc = $this->collectAnrStatsForRiskThreatVulnerabilityAndCartography($anr);
            $statsData[] = new StatsDataObject([
                'anr' => $anr->getUuid(),
                'type' => StatsDataObject::TYPE_RISK,
                'data' => $anrStatsForRtvc[StatsDataObject::TYPE_RISK],
            ]);
            $statsData[] = new StatsDataObject([
                'anr' => $anr->getUuid(),
                'type' => StatsDataObject::TYPE_THREAT,
                'data' => $anrStatsForRtvc[StatsDataObject::TYPE_THREAT],
            ]);
            $statsData[] = new StatsDataObject([
                'anr' => $anr->getUuid(),
                'type' => StatsDataObject::TYPE_VULNERABILITY,
                'data' => $anrStatsForRtvc[StatsDataObject::TYPE_VULNERABILITY],
            ]);
            $statsData[] = new StatsDataObject([
                'anr' => $anr->getUuid(),
                'type' => StatsDataObject::TYPE_CARTOGRAPHY,
                'data' => $anrStatsForRtvc[StatsDataObject::TYPE_CARTOGRAPHY],
            ]);

            $anrStatsForCompliance = $this->collectAnrStatsForCompliance($anr);
            $statsData[] = new StatsDataObject([
                'anr' => $anr->getUuid(),
                'type' => StatsDataObject::TYPE_COMPLIANCE,
                'data' => $anrStatsForCompliance,
            ]);
        }

        if (!empty($statsData)) {
            $this->statsApiProvider->sendStatsDataInBatch($statsData);
        }
    }

    private function collectAnrStatsForRiskThreatVulnerabilityAndCartography(Anr $anr): array
    {
        $scalesRanges = $this->prepareScalesRanges($anr);
        $likelihoodScales = $this->calculateScalesColumnValues($scalesRanges);

        $informationalRisksValues = $this->calculateInformationalRisks($anr);
        $operationalRisksValues = $this->calculateOperationalRisks($anr);

        return [
            StatsDataObject::TYPE_RISK => [
                'total' => [
                    'current' => [
                        'informational' => $this->getTotalValue(
                            $informationalRisksValues[StatsDataObject::TYPE_RISK]['current']
                        ),
                        'operational' => $this->getTotalValue(
                            $operationalRisksValues[StatsDataObject::TYPE_RISK]['current']
                        ),
                    ],
                    'residual' => [
                        'informational' => $this->getTotalValue(
                            $informationalRisksValues[StatsDataObject::TYPE_RISK]['residual']
                        ),
                        'operational' => $this->getTotalValue(
                            $operationalRisksValues[StatsDataObject::TYPE_RISK]['residual']
                        ),
                    ],
                ],
                'risks' => [
                    'current' => [
                        'informational' => $informationalRisksValues[StatsDataObject::TYPE_RISK]['current'],
                        'operational' => $operationalRisksValues[StatsDataObject::TYPE_RISK]['current'],
                    ],
                    'residual' => [
                        'informational' => $informationalRisksValues[StatsDataObject::TYPE_RISK]['residual'],
                        'operational' => $operationalRisksValues[StatsDataObject::TYPE_RISK]['residual'],
                    ],
                ],
            ],
            StatsDataObject::TYPE_THREAT => $informationalRisksValues[StatsDataObject::TYPE_THREAT],
            StatsDataObject::TYPE_VULNERABILITY => $informationalRisksValues[StatsDataObject::TYPE_VULNERABILITY],
            StatsDataObject::TYPE_CARTOGRAPHY => [
                'scales' => [
                    'impact' => $scalesRanges[Scale::TYPE_IMPACT],
                    'probability' => $scalesRanges[Scale::TYPE_THREAT],
                    'likelihood' => $likelihoodScales,
                ],
                'risks' => [
                    'current' => [
                        'informational' => $informationalRisksValues[StatsDataObject::TYPE_CARTOGRAPHY]['current'],
                        'operational' => $operationalRisksValues[StatsDataObject::TYPE_CARTOGRAPHY]['current'],
                    ],
                    'residual' => [
                        'informational' =>  $informationalRisksValues[StatsDataObject::TYPE_CARTOGRAPHY]['residual'],
                        'operational' => $operationalRisksValues[StatsDataObject::TYPE_CARTOGRAPHY]['residual'],
                    ]
                ],
            ],
        ];
    }

    private function collectAnrStatsForCompliance(Anr $anr): array
    {
        $complianceStatsValues = [];
        $references = $this->referentialTable->findByAnr($anr);
        foreach ($references as $reference) {
            if (!$reference->hasMeasures()) {
                continue;
            }

            $currentCategoriesValues = [];
            $targetCategoriesValues = [];
            foreach ($reference->getCategories() as $soaCategory) {
                $soaCategoryId = $soaCategory->getId();
                $soaCategoryData = [
                    'label1' => $soaCategory->getlabel1(),
                    'label2' => $soaCategory->getlabel2(),
                    'label3' => $soaCategory->getlabel3(),
                    'label4' => $soaCategory->getlabel4(),
                ];
                /** @var Measure[] $measures */
                $measures = $soaCategory->getMeasures();
                $targetSoaCount = 0;
                $currentSoaTotalCompliance = '0';
                foreach ($measures as $measure) {
                    $soa = $measure->getSoa();
                    if (!$soa) {
                        continue;
                    }

                    $currentControlData = [
                        'code' => $measure->getCode(),
                        'measure' => $measure->getUuid(),
                        'value' => $soa->getEx() === 1 ? '0.00' : bcmul((string)$soa->getCompliance(), '0.2', 2),
                    ];
                    $targetControlData = [
                        'code' => $measure->getCode(),
                        'measure' => $measure->getUuid(),
                        'value' => $soa->getEx() === 1 ? '0' : '1',
                    ];
                    $currentCategoriesValues[$soaCategoryId] = $soaCategoryData;
                    $currentCategoriesValues[$soaCategoryId]['current'][] = $currentControlData;
                    $currentCategoriesValues[$soaCategoryId]['target'][] = $targetControlData;

                    $targetCategoriesValues[$soaCategoryId] = $soaCategoryData;
                    $targetCategoriesValues[$soaCategoryId]['current'][] = $currentControlData;
                    $targetCategoriesValues[$soaCategoryId]['target'][] = $targetControlData;

                    $currentSoaTotalCompliance = bcadd(
                        $currentSoaTotalCompliance,
                        $soa->getEx() === 1 ? '0' : (string)$soa->getCompliance(),
                        2
                    );
                    if ($soa->getEx() !== 1) {
                        $targetSoaCount++;
                    }
                }

                $currentCategoriesValues[$soaCategoryId]['value'] = bcmul(
                    bcdiv($currentSoaTotalCompliance, \count($currentCategoriesValues[$soaCategoryId]), 2),
                    '0.2',
                    2
                );
                $targetCategoriesValues[$soaCategoryId] = bcdiv(
                    (string)$targetSoaCount,
                    $targetSoaCount,
                    2
                );
            }

            $complianceStatsValues[] = [
                'referential' => $reference->getUuid(),
                'current' => $currentCategoriesValues,
                'target' => $targetCategoriesValues,
            ];
        }

        return $complianceStatsValues;
    }

    private function prepareScalesRanges(Anr $anr): array
    {
        $scalesRanges = [
            Scale::TYPE_IMPACT => [],
            Scale::TYPE_THREAT => [],
            Scale::TYPE_VULNERABILITY => [],
        ];
        $scales = $this->scaleTable->findByAnr($anr);
        foreach ($scales as $scale) {
            $scalesRanges[$scale->getType()] = range($scale->getMin(), $scale->getMax());
        }

        return $scalesRanges;
    }

    private function calculateScalesColumnValues(array $scalesRanges): array
    {
        $headersResult = [];
        foreach ($scalesRanges[Scale::TYPE_IMPACT] as $i) {
            foreach ($scalesRanges[Scale::TYPE_THREAT] as $t) {
                foreach ($scalesRanges[Scale::TYPE_VULNERABILITY] as $v) {
                    $value = -1;
                    if ($i !== -1 && $t !== -1 && $v !== -1) {
                        $value = $t * $v;
                    }
                    if (!\in_array($value, $headersResult, true)) {
                        $headersResult[] = $value;
                    }
                }
            }
        }

        sort($headersResult);

        return $headersResult;
    }

    /**
     * Calculates the number of risks per each level, its distribution, threats and vulnerabilities rates per uuid key.
     */
    private function calculateInformationalRisks(Anr $anr): array
    {
        $currentRisksValues = [];
        $residualRisksValues = [];
        $threatsValues = [];
        $vulnerabilitiesValues = [];
        $risksData = $this->informationalRiskTable->findRisksDataForStatsByAnr($anr);
        foreach ($risksData as $riskData) {
            $confidentiality = $riskData['threatConfidentiality'] ? $riskData['instanceConfidentiality'] : 0;
            $integrity = $riskData['threatIntegrity'] ? $riskData['instanceIntegrity'] : 0;
            $availability = $riskData['threatAvailability'] ? $riskData['instanceAvailability'] : 0;

            $maxImpact = max($confidentiality, $integrity, $availability);
            if ($maxImpact < 0) {
                continue;
            }
            $amvKey = md5($riskData['assetId'] . $riskData['threatId'] . $riskData['vulnerabilityId']);
            if ($riskData['cacheMaxRisk'] > -1) {
                $riskContext = $this->getRiskContext($anr, $maxImpact, $riskData['cacheMaxRisk']);
                $currentRisksValues = $this->getRiskValues($currentRisksValues, $riskContext, $amvKey, $riskData);

                if ($riskData['cacheMaxRisk'] > 0) {
                    $otvKey = md5($riskData['objectId'] . $riskData['threatId'] . $riskData['vulnerabilityId']);
                    $threatsValues = $this->getThreatsValues($threatsValues, $otvKey, $riskData);
                    $vulnerabilitiesValues = $this->getVulnerabilitiesValues(
                        $vulnerabilitiesValues,
                        $otvKey,
                        $riskData
                    );
                }
            }
            if ($riskData['cacheTargetedRisk'] > -1) {
                $riskContext = $this->getRiskContext($anr, $maxImpact, $riskData['cacheTargetedRisk']);
                $residualRisksValues = $this->getRiskValues($residualRisksValues, $riskContext, $amvKey, $riskData);
            }
        }

        $currentInformationalRisks = $this->countInformationalRisksValues($currentRisksValues);
        $residualInformationalRisks = $this->countInformationalRisksValues($residualRisksValues);

        return [
            StatsDataObject::TYPE_RISK => [
                'current' => $currentInformationalRisks['distributed'],
                'residual' => $residualInformationalRisks['distributed'],
            ],
            StatsDataObject::TYPE_THREAT => $this->calculateAverageRatesAndCountPerKey($threatsValues),
            StatsDataObject::TYPE_VULNERABILITY => $this->calculateAverageRatesAndCountPerKey($vulnerabilitiesValues),
            StatsDataObject::TYPE_CARTOGRAPHY => [
                'current' => $currentInformationalRisks['counters'],
                'residual' => $residualInformationalRisks['counters'],
            ],
        ];
    }

    private function getRiskContext(Anr $anr, int $maxImpact, int $maxRiskValue): array
    {
        return [
            'impact' => $maxImpact,
            'likelihood' => $maxImpact > 0 ? round($maxRiskValue / $maxImpact) : 0,
            'max' => $maxRiskValue,
            'level' => $this->getRiskLevel($anr, $maxRiskValue),
        ];
    }

    private function getRiskValues(array $risksValues, array $riskContext, string $amvKey, array $riskData): array
    {
        $objectId = (string)$riskData['objectId'];
        if ($riskData['scope'] !== MonarcObject::SCOPE_GLOBAL
            || empty($risksValues[$objectId][$amvKey])
            || $riskContext['max'] > current($risksValues[$objectId][$amvKey])['max']
        ) {
            $risksValues[$objectId][$amvKey][$riskData['id']] = $riskContext;
        }

        return $risksValues;
    }

    private function getThreatsValues(array $threatsValues, string $key, array $riskData): array
    {
        if ($riskData['scope'] !== MonarcObject::SCOPE_GLOBAL
            || empty($threatsValues[$key])
            || $riskData['cacheMaxRisk'] > current($threatsValues[$key])['maxRisk']
        ) {
            $threatsValues[$key][(string)$riskData['threatId']] = [
                'uuid' => (string)$riskData['threatId'],
                'label1' => $riskData['threatLabel1'],
                'label2' => $riskData['threatLabel2'],
                'label3' => $riskData['threatLabel3'],
                'label4' => $riskData['threatLabel4'],
                'maxRisk' => $riskData['cacheMaxRisk'],
                'count' => 1,
                'averageRate' => (string)$riskData['threatRate'],
            ];
        }

        return $threatsValues;
    }

    private function getVulnerabilitiesValues(array $vulnerabilitiesValues, string $key, array $riskData): array
    {
        if ($riskData['scope'] !== MonarcObject::SCOPE_GLOBAL
            || empty($vulnerabilitiesValues[$key])
            || $riskData['cacheMaxRisk'] > current($vulnerabilitiesValues[$key])['maxRisk']
        ) {
            $vulnerabilitiesValues[$key][(string)$riskData['vulnerabilityId']] = [
                'uuid' => (string)$riskData['vulnerabilityId'],
                'label1' => $riskData['vulnerabilityLabel1'],
                'label2' => $riskData['vulnerabilityLabel2'],
                'label3' => $riskData['vulnerabilityLabel3'],
                'label4' => $riskData['vulnerabilityLabel4'],
                'count' => 1,
                'maxRisk' => $riskData['cacheMaxRisk'],
                'averageRate' => (string)$riskData['vulnerabilityRate'],
            ];
        }

        return $vulnerabilitiesValues;
    }

    private function countInformationalRisksValues(array $risksValues): array
    {
        $counters = [];
        $distributed = [
            [
                'level' => self::LOW_RISKS,
                'value' => 0,
            ],
            [
                'level' => self::MEDIUM_RISKS,
                'value' => 0,
            ],
            [
                'level' => self::HIGH_RISKS,
                'value' => 0,
            ],
        ];

        foreach ($risksValues as $risksValue) {
            foreach ($risksValue as $values) {
                foreach ($values as $context) {
                    if (!isset($counters[$context['impact']][$context['likelihood']])) {
                        $counters[$context['impact']][$context['likelihood']] = 0;
                    }
                    $counters[$context['impact']][$context['likelihood']]++;

                    $levelElementIndex = array_search($context['level'], array_column($distributed, 'level'), true);
                    if ($levelElementIndex !== false) {
                        $distributed[(int)$levelElementIndex]['value']++;
                    }
                }
            }
        }

        ksort($counters);
        foreach ($counters as &$counter) {
            ksort($counter);
        }

        return compact('counters', 'distributed');
    }

    private function calculateAverageRatesAndCountPerKey(array $values): array
    {
        $averageRatesPerKey = [];
        foreach ($values as $value) {
            foreach ($value as $context) {
                $key = $context['uuid'];
                if (isset($averageRatesPerKey[$key])) {
                    $averageRatesPerKey[$key]['averageRate'] =
                        bcdiv(
                            bcadd(
                                bcmul(
                                    $averageRatesPerKey[$key]['averageRate'],
                                    (string)$averageRatesPerKey[$key]['count'],
                                    2
                                ),
                                $context['averageRate'],
                                2
                            ),
                            (string)++$averageRatesPerKey[$key]['count'],
                            2
                        );
                    if ($context['maxRisk'] > $averageRatesPerKey[$key]['maxRisk']) {
                        $averageRatesPerKey[$key]['maxRisk'] = $context['maxRisk'];
                    }
                } else {
                    $averageRatesPerKey[$key] = $context;
                }
            }
        }

        return array_values($averageRatesPerKey);
    }

    /**
     * Calculates the number of operational risks for each impact/probability combo.
     */
    private function calculateOperationalRisks(Anr $anr): array
    {
        $currentRisksCounters = [];
        $currentRisksDistributed = [
            [
                'level' => self::LOW_RISKS,
                'value' => 0,
            ],
            [
                'level' => self::MEDIUM_RISKS,
                'value' => 0,
            ],
            [
                'level' => self::HIGH_RISKS,
                'value' => 0,
            ],
        ];
        $residualRisksCounters = [];
        $residualRisksDistributed = [
            [
                'level' => self::LOW_RISKS,
                'value' => 0,
            ],
            [
                'level' => self::MEDIUM_RISKS,
                'value' => 0,
            ],
            [
                'level' => self::HIGH_RISKS,
                'value' => 0,
            ],
        ];
        $risksData = $this->operationalRiskTable->findRisksDataForStatsByAnr($anr);
        foreach ($risksData as $riskData) {
            $maxImpact = max(
                $riskData['netR'],
                $riskData['netO'],
                $riskData['netL'],
                $riskData['netF'],
                $riskData['netP']
            );
            $riskValue = $riskData['cacheNetRisk'];
            $prob = $riskData['netProb'];
            [$currentRisksCounters, $currentRisksDistributed] = $this->countOperationalRisks(
                $anr,
                $riskValue,
                $currentRisksCounters,
                $maxImpact,
                $prob,
                $currentRisksDistributed
            );
            if ($riskData['cacheTargetedRisk'] > -1) {
                $maxImpact = max(
                    $riskData['targetedR'],
                    $riskData['targetedO'],
                    $riskData['targetedL'],
                    $riskData['targetedF'],
                    $riskData['targetedP']
                );
                $riskValue = $riskData['cacheTargetedRisk'];
                $prob = $riskData['targetedProb'];
            }
            [$residualRisksCounters, $residualRisksDistributed] = $this->countOperationalRisks(
                $anr,
                $riskValue,
                $residualRisksCounters,
                $maxImpact,
                $prob,
                $residualRisksDistributed
            );
        }

        return [
            StatsDataObject::TYPE_RISK => [
                'current' => $currentRisksDistributed,
                'residual' => $residualRisksDistributed,
            ],
            StatsDataObject::TYPE_CARTOGRAPHY => [
                'current' => $currentRisksCounters,
                'residual' => $residualRisksCounters,
            ],
        ];
    }

    private function countOperationalRisks(
        Anr $anr,
        int $riskValue,
        array $risksCounters,
        int $maxImpact,
        int $prob,
        array $risksDistributed
    ): array {
        $level = $this->getRiskLevel($anr, $riskValue, 'operational');

        if (!isset($risksCounters[$maxImpact][$prob])) {
            $risksCounters[$maxImpact][$prob] = 0;
        }
        $risksCounters[$maxImpact][$prob]++;

        $levelElementIndex = array_search($level, array_column($risksDistributed, 'level'), true);
        if ($levelElementIndex !== false) {
            $risksDistributed[(int)$levelElementIndex]['value']++;
        }

        return [$risksCounters, $risksDistributed];
    }

    private function getRiskLevel(Anr $anr, int $riskValue, string $riskType = 'informational'): string
    {
        $firstRiskLevel = $riskType === 'informational' ? $anr->getSeuil1() : $anr->getSeuilRolf1();
        $secondRiskLevel = $riskType === 'informational' ? $anr->getSeuil2() : $anr->getSeuilRolf1();

        if ($riskValue <= $firstRiskLevel) {
            return self::LOW_RISKS;
        }

        if ($riskValue <= $secondRiskLevel) {
            return self::MEDIUM_RISKS;
        }

        return self::HIGH_RISKS;
    }

    private function getTotalValue(array $values): int
    {
        return array_sum(array_column($values, 'value'));
    }
}
