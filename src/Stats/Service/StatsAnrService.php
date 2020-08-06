<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Stats\Service;

use DateTime;
use Exception;
use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Exception\AccessForbiddenException;
use Monarc\FrontOffice\Exception\UserNotAuthorizedException;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\MonarcObject;
use Monarc\FrontOffice\Model\Entity\Scale;
use Monarc\FrontOffice\Model\Entity\SoaCategory;
use Monarc\FrontOffice\Model\Entity\User;
use Monarc\FrontOffice\Model\Entity\UserRole;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskOpTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskTable;
use Monarc\FrontOffice\Model\Table\ReferentialTable;
use Monarc\FrontOffice\Model\Table\ScaleTable;
use Monarc\FrontOffice\Model\Table\SoaTable;
use Monarc\FrontOffice\Stats\DataObject\StatsDataObject;
use Monarc\FrontOffice\Stats\Exception\StatsAlreadyCollectedException;
use Monarc\FrontOffice\Stats\Exception\StatsFetchingException;
use Monarc\FrontOffice\Stats\Exception\StatsSendingException;
use Monarc\FrontOffice\Stats\Exception\WrongResponseFormatException;
use Monarc\FrontOffice\Stats\Provider\StatsApiProvider;

class StatsAnrService
{
    public const LOW_RISKS = 'Low risks';
    public const MEDIUM_RISKS = 'Medium risks';
    public const HIGH_RISKS = 'High risks';

    public const DEFAULT_STATS_DATES_RANGE = '3 months';

    public const AVAILABLE_AGGREGATION_FIELDS = [
        'day',
        'week',
        'month',
        'quarter',
        'year',
    ];

    /** @var AnrTable */
    private $anrTable;

    /** @var ScaleTable */
    private $scaleTable;

    /** @var InstanceRiskTable */
    private $informationalRiskTable;

    /** @var InstanceRiskOpTable */
    private $operationalRiskTable;

    /** @var ReferentialTable */
    private $referentialTable;

    /** @var SoaTable */
    private $soaTable;

    /** @var StatsApiProvider */
    private $statsApiProvider;

    /** @var ConnectedUserService */
    private $connectedUserService;

    public function __construct(
        AnrTable $anrTable,
        ScaleTable $scaleTable,
        InstanceRiskTable $informationalRiskTable,
        InstanceRiskOpTable $operationalRiskTable,
        ReferentialTable $referentialTable,
        SoaTable $soaTable,
        StatsApiProvider $statsApiProvider,
        ConnectedUserService $connectedUserService
    ) {
        $this->anrTable = $anrTable;
        $this->scaleTable = $scaleTable;
        $this->informationalRiskTable = $informationalRiskTable;
        $this->operationalRiskTable = $operationalRiskTable;
        $this->referentialTable = $referentialTable;
        $this->soaTable = $soaTable;
        $this->statsApiProvider = $statsApiProvider;
        $this->connectedUserService = $connectedUserService;
    }

    /**
     * @param array $filterParams Accepts the following params keys:
     *              - type Stats type (required);
     *              - dateFrom Stats period start date (optional);
     *              - dateTo Stats period end date (optional);
     *              - anrs List of Anr IDs to use for the result (optional);
     *              - aggregationPeriod One of the available options [day, week, month, quarter, year] (optional).
     *
     * @return array
     *
     * @throws UserNotAuthorizedException
     * @throws AccessForbiddenException
     * @throws Exception
     * @throws StatsFetchingException
     */
    public function getStats(array $filterParams): array
    {
        /** @var User $loggedInUser */
        $loggedInUser = $this->connectedUserService->getConnectedUser();
        if ($loggedInUser === null) {
            throw new UserNotAuthorizedException();
        }

        if (empty($filterParams['type'])) {
            throw new \LogicException("Filter parameter 'type' is mandatory to get the stats.");
        }

        $hasFullAccess = $loggedInUser->hasRole(UserRole::USER_ROLE_CEO);

        $requestParams['type'] = $filterParams['type'];
        if (empty($filterParams['getLast'])) {
            $requestParams['date_from'] = $this->getPreparedDateFrom($filterParams);
            $requestParams['date_to'] = $this->getPreparedDateTo($filterParams);
            $requestParams['get_last'] = false;
        } else {
            $requestParams['get_last'] = true;
        }

        $anrUuids = $this->getFilteredAnrUuids($filterParams, $hasFullAccess, $loggedInUser);
        if (!empty($anrUuids)) {
            $requestParams['anrs'] = $anrUuids;
        }
        if (!empty($filterParams['aggregationPeriod'])) {
            $requestParams['aggregation_period'] = $filterParams['aggregationPeriod'];
        }

        $statsData = $this->statsApiProvider->getStatsData($requestParams);

        // TODO: make a response data formatter.
        if ($requestParams['type'] === StatsDataObject::TYPE_RISK) {
            $statsData = $this->formatRisksStatsData($statsData);
        } elseif (
        \in_array($requestParams['type'], [StatsDataObject::TYPE_THREAT, StatsDataObject::TYPE_VULNERABILITY], true)
        ) {
            $statsData = $this->formatThreatsOrVulnerabilitiesStatsData($statsData);
        } elseif ($requestParams['type'] === StatsDataObject::TYPE_CARTOGRAPHY) {
            $statsData = $this->formatCartographyStatsData($statsData);
        } elseif ($requestParams['type'] === StatsDataObject::TYPE_COMPLIANCE) {
            $statsData = $this->formatComplianceStatsData($statsData);
        }

        return $statsData;
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
     * @throws WrongResponseFormatException
     */
    public function collectStats(array $anrIds = [], bool $forceUpdate = false): void
    {
        $currentDate = (new DateTime())->format('Y-m-d');
        $statsOfToday = $this->statsApiProvider->getStatsData([
            'type' => StatsDataObject::TYPE_RISK,
            'date_from' => $currentDate,
            'date_to' => $currentDate,
        ]);
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
                'date' => $currentDate,
            ]);
            $statsData[] = new StatsDataObject([
                'anr' => $anr->getUuid(),
                'type' => StatsDataObject::TYPE_THREAT,
                'data' => $anrStatsForRtvc[StatsDataObject::TYPE_THREAT],
                'date' => $currentDate,
            ]);
            $statsData[] = new StatsDataObject([
                'anr' => $anr->getUuid(),
                'type' => StatsDataObject::TYPE_VULNERABILITY,
                'data' => $anrStatsForRtvc[StatsDataObject::TYPE_VULNERABILITY],
                'date' => $currentDate,
            ]);
            $statsData[] = new StatsDataObject([
                'anr' => $anr->getUuid(),
                'type' => StatsDataObject::TYPE_CARTOGRAPHY,
                'data' => $anrStatsForRtvc[StatsDataObject::TYPE_CARTOGRAPHY],
                'date' => $currentDate,
            ]);

            $anrStatsForCompliance = $this->collectAnrStatsForCompliance($anr);
            $statsData[] = new StatsDataObject([
                'anr' => $anr->getUuid(),
                'type' => StatsDataObject::TYPE_COMPLIANCE,
                'data' => $anrStatsForCompliance,
                'date' => $currentDate,
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
            /** @var SoaCategory $soaCategory */
            foreach ($reference->getCategories() as $soaCategory) {
                $statementOfApplicabilityList = $this->soaTable->findByAnrAndSoaCategory(
                    $anr,
                    $soaCategory,
                    ['m.code' => 'ASC']
                );
                if (empty($statementOfApplicabilityList)) {
                    continue;
                }

                $currentCategoryValues = [
                    'label1' => $soaCategory->getlabel1(),
                    'label2' => $soaCategory->getlabel2(),
                    'label3' => $soaCategory->getlabel3(),
                    'label4' => $soaCategory->getlabel4(),
                    'controls' => [],
                ];
                $targetCategoryValues = [
                    'label1' => $soaCategory->getlabel1(),
                    'label2' => $soaCategory->getlabel2(),
                    'label3' => $soaCategory->getlabel3(),
                    'label4' => $soaCategory->getlabel4(),
                    'controls' => [],
                ];
                $targetSoaCount = 0;
                $currentSoaTotalCompliance = '0';
                foreach ($statementOfApplicabilityList as $soa) {
                    $measure = $soa->getMeasure();
                    $currentCategoryValues['controls'][] = [
                        'code' => $measure->getCode(),
                        'measure' => (string)$measure->getUuid(),
                        'value' => $soa->getEx() === 1 ? '0.00' : bcmul((string)$soa->getCompliance(), '0.20', 2),
                    ];
                    $targetCategoryValues['controls'][] = [
                        'code' => $measure->getCode(),
                        'measure' => (string)$measure->getUuid(),
                        'value' => $soa->getEx() === 1 ? '0' : '1',
                    ];

                    $currentSoaTotalCompliance = bcadd(
                        $currentSoaTotalCompliance,
                        $soa->getEx() === 1 ? '0' : (string)$soa->getCompliance(),
                        2
                    );
                    if ($soa->getEx() !== 1) {
                        $targetSoaCount++;
                    }
                }

                $currentControlsNumber = \count($currentCategoryValues['controls']);
                if ($currentControlsNumber > 0) {
                    $currentCategoryValues['value'] = bcmul(bcdiv(
                        $currentSoaTotalCompliance,
                        (string)$currentControlsNumber,
                        2
                    ), '0.2', 2);
                    $targetCategoryValues['value'] = bcdiv((string)$targetSoaCount, (string)$currentControlsNumber, 2);

                    if (!isset($complianceStatsValues[(string)$reference->getUuid()])) {
                        $complianceStatsValues[(string)$reference->getUuid()] = [
                            'referential' => (string)$reference->getUuid(),
                            'label1' => $reference->getLabel1(),
                            'label2' => $reference->getLabel2(),
                            'label3' => $reference->getLabel3(),
                            'label4' => $reference->getLabel4(),
                            'current' => [],
                            'target' => [],
                        ];
                    }
                    $complianceStatsValues[(string)$reference->getUuid()]['current'][] = $currentCategoryValues;
                    $complianceStatsValues[(string)$reference->getUuid()]['target'][] = $targetCategoryValues;
                }
            }
        }

        return array_values($complianceStatsValues);
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
            'maxRisk' => $maxRiskValue,
            'level' => $this->getRiskLevel($anr, $maxRiskValue),
        ];
    }

    private function getRiskValues(array $risksValues, array $riskContext, string $amvKey, array $riskData): array
    {
        $objectId = (string)$riskData['objectId'];
        $isScopeGlobal = $riskData['scope'] === MonarcObject::SCOPE_GLOBAL;
        $riskKey = $isScopeGlobal ? 0 : $riskData['id'];
        if (!$isScopeGlobal
            || empty($risksValues[$objectId][$amvKey][$riskKey])
            || $riskContext['maxRisk'] > $risksValues[$objectId][$amvKey][$riskKey]['maxRisk']
        ) {
            $risksValues[$objectId][$amvKey][$riskKey] = $riskContext;
        }

        return $risksValues;
    }

    private function getThreatsValues(array $threatsValues, string $key, array $riskData): array
    {
        $isScopeGlobal = $riskData['scope'] === MonarcObject::SCOPE_GLOBAL;
        $threatKey = $isScopeGlobal ? 0 : (string)$riskData['threatId'];
        if (!$isScopeGlobal
            || empty($threatsValues[$key][$threatKey])
            || $riskData['cacheMaxRisk'] > $threatsValues[$key][$threatKey]['maxRisk']
        ) {
            $threatsValues[$key][$threatKey] = [
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
        $isScopeGlobal = $riskData['scope'] === MonarcObject::SCOPE_GLOBAL;
        $vulnerabilityKey = $isScopeGlobal ? 0 : (string)$riskData['vulnerabilityId'];
        if (!$isScopeGlobal
            || empty($vulnerabilitiesValues[$key][$vulnerabilityKey])
            || $riskData['cacheMaxRisk'] > $vulnerabilitiesValues[$key][$vulnerabilityKey]['maxRisk']
        ) {
            $vulnerabilitiesValues[$key][$vulnerabilityKey] = [
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

    /**
     * @throws AccessForbiddenException
     */
    private function getFilteredAnrUuids(array $filterParams, bool $hasFullAccess, UserSuperClass $loggedInUser): array
    {
        $anrUuids = [];
        if (!$hasFullAccess) {
            foreach ($loggedInUser->getUserAnrs() as $userAnr) {
                /** @var Anr $anr */
                $anr = $userAnr->getAnr();
                if (!empty($filterParams['anrs']) && !\in_array($anr->getId(), $filterParams['anrs'], true)) {
                    continue;
                }

                $anrUuids[] = $anr->getUuid();
            }
            if (empty($anrUuids)) {
                throw new AccessForbiddenException();
            }
        } elseif (!empty($filterParams['anrs'])) {
            foreach ($this->anrTable->findByIds($filterParams['anrs']) as $anr) {
                $anrUuids[] = $anr->getUuid();
            }
        }

        return $anrUuids;
    }

    /**
     * @throws Exception
     */
    private function getPreparedDateFrom(array $filterParams): string
    {
        if (!empty($filterParams['dateFrom'])) {
            return $filterParams['dateFrom'];
        }

        $dateTo = new DateTime();
        if (!empty($filterParams['dateTo'])) {
            $dateTo = new DateTime($filterParams['dateTo']);
        }

        return $dateTo->modify('- ' . self::DEFAULT_STATS_DATES_RANGE)->format('Y-m-d');
    }

    /**
     * @throws Exception
     */
    private function getPreparedDateTo(array $filterParams): string
    {
        if (!empty($filterParams['dateTo'])) {
            return $filterParams['dateTo'];
        }

        return (new DateTime())->format('Y-m-d');
    }

    /**
     * @param StatsDataObject[] $statsData
     *
     * @return array
     */
    private function formatRisksStatsData(array $statsData): array
    {
        if (empty($statsData)) {
            return [];
        }

        $formattedResult = [];
        $anrUuids = [];
        foreach ($statsData as $data) {
            $anrUuids[] = $data->getAnr();
            $formattedResult[$data->getAnr()] = [
                'current' => [
                    'category' => '',
                    'series' => $this->getSeriesForType('current', $data->getData()),
                ],
                'residual' => [
                    'category' => '',
                    'series' => $this->getSeriesForType('residual', $data->getData()),
                ],
            ];
        }

        $anrs = $this->anrTable->findByUuids($anrUuids);
        foreach ($anrs as $anr) {
            $formattedResult[$anr->getUuid()]['current']['category'] = $anr->getLabel($anr->getLanguage());
            $formattedResult[$anr->getUuid()]['residual']['category'] = $anr->getLabel($anr->getLanguage());
        }

        if (!empty($formattedResult)) {
            $formattedResult = [
                'current' => array_column($formattedResult, 'current'),
                'residual' => array_column($formattedResult, 'residual'),
            ];
            foreach ($formattedResult as $key => &$value) {
              usort($value, function ($a,$b){
                return $a['category'] <=> $b['category'];
              });
            }
        }

        return $formattedResult;
    }

    private function getSeriesForType(string $riskType, array $data): array
    {
        $series = [];
        foreach ($data['risks'][$riskType]['informational'] as $currentInformational) {
            $series[] = [
                'label' => $currentInformational['level'],
                'value' => $currentInformational['value'],
            ];
        }

        return $series;
    }

    /**
     * @param StatsDataObject[] $statsData
     *
     * @return array
     */
    private function formatThreatsOrVulnerabilitiesStatsData(array $statsData): array
    {
        if (empty($statsData)) {
            return [];
        }

        $userLanguageNumber = $this->connectedUserService->getConnectedUser()->getLanguage();
        $formattedResult = [];
        $anrUuids = [];
        foreach ($statsData as $data) {
            $anrUuid = $data->getAnr();
            $anr = current($this->anrTable->findByUuids([$anrUuid]));
            $anrLanguage = $anr->getLanguage();

            $dataSets = $data->getData();
            if (!\in_array($anrUuid, $anrUuids, true)) {
                $anrUuids[] = $anrUuid;
                $formattedResult[$anrUuid] = [
                    'category' => $anr->getLabel($anr->getLanguage()),
                    'series' => [],
                ];
            }

            foreach ($dataSets as $dataSet) {
                $dataSetUuid = $dataSet['uuid'];
                if (!isset($formattedResult[$anrUuid]['series'][$dataSetUuid])) {
                    $formattedResult[$anrUuid]['series'][$dataSetUuid] = [
                        'category' => (!empty($dataSet['label' . $userLanguageNumber]) ?
                                        $dataSet['label' . $userLanguageNumber] :
                                        $dataSet['label' . $anrLanguage]),
                        'uuid' => $dataSetUuid,
                        'series' => [
                            [
                                'label' => $data->getDate(),
                                'count' => $dataSet['count'],
                                'maxRisk' => $dataSet['maxRisk'],
                                'averageRate' => $dataSet['averageRate'],
                            ]
                        ],
                    ];
                }else {
                  $addSerie = [
                      'label' => $data->getDate(),
                      'count' => $dataSet['count'],
                      'maxRisk' => $dataSet['maxRisk'],
                      'averageRate' => $dataSet['averageRate']
                  ];

                  array_push($formattedResult[$anrUuid]['series'][$dataSetUuid]['series'],$addSerie);
                }

                usort($formattedResult[$anrUuid]['series'][$dataSetUuid]['series'], function ($a,$b){
                  return $a['label'] <=> $b['label'];
                });
            }
        }

        $formattedResult = array_values($formattedResult);
        foreach ($formattedResult as &$resultByAnr) {
            $resultByAnr['series'] = array_values($resultByAnr['series']);
        }

          usort($formattedResult, function ($a,$b){
            return $a['category'] <=> $b['category'];
          });

        return $formattedResult;
    }

    /**
     * @param StatsDataObject[] $statsData
     *
     * @return array
     */
    private function formatCartographyStatsData(array $statsData): array
    {
        if (empty($statsData)) {
            return [];
        }

        $userLanguageNumber = $this->connectedUserService->getConnectedUser()->getLanguage();
        $formattedResult = [];
        $anrUuids = [];
        foreach ($statsData as $data) {
            $anrUuids[] = $data->getAnr();
            $data = $data->getData();
            // TODO: build a matrix with values.
            //       iterate for ($i = 0 till max scale x and y)
            $data['risks']['current']['informational'];
            $data['risks']['current']['operational'];
            $formattedResult[$data->getAnr()] = [
                'saceles' => $data['scales'],
                'series' => [],
            ];
        }
    }

    /**
     * @param StatsDataObject[] $statsData
     *
     * @return array
     */
    private function formatComplianceStatsData(array $statsData): array
    {
        if (empty($statsData)) {
            return [];
        }

        $userLanguageNumber = $this->connectedUserService->getConnectedUser()->getLanguage();
        $formattedResult = [];
        $anrUuids = [];
        foreach ($statsData as $data) {

        }
    }
}
