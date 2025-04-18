<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Stats\Service;

use DateTime;
use Exception;
use LogicException;
use Monarc\Core\Entity\ObjectSuperClass;
use Monarc\Core\Entity\ScaleSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Exception\AccessForbiddenException;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\InstanceRiskOp;
use Monarc\FrontOffice\Entity\SoaCategory;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Entity\UserRole;
use Monarc\FrontOffice\Import\Traits\EvaluationConverterTrait;
use Monarc\FrontOffice\Table;
use Monarc\FrontOffice\Stats\DataObject\StatsDataObject;
use Monarc\FrontOffice\Stats\Exception\StatsAlreadyCollectedException;
use Monarc\FrontOffice\Stats\Exception\StatsFetchingException;
use Monarc\FrontOffice\Stats\Exception\StatsSendingException;
use Monarc\FrontOffice\Stats\Exception\WrongResponseFormatException;
use Monarc\FrontOffice\Stats\Provider\StatsApiProvider;
use Throwable;
use function in_array;

class StatsAnrService
{
    use EvaluationConverterTrait;

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

    public const AVAILABLE_STATS_PROCESSORS = [
        'risk_process',
        'risk_averages',
        'risk_averages_on_date',
        'threat_process',
        'threat_average_on_date',
        'vulnerability_average_on_date',
    ];

    private ?User $connectedUser;

    /** @var string */
    private $apiKey;

    public function __construct(
        private Table\AnrTable $anrTable,
        private Table\ScaleTable $scaleTable,
        private Table\InstanceRiskTable $informationalRiskTable,
        private Table\InstanceRiskOpTable $operationalRiskTable,
        private Table\ReferentialTable $referentialTable,
        private Table\SoaTable $soaTable,
        private StatsApiProvider $statsApiProvider,
        private Table\SnapshotTable $snapshotTable,
        ConnectedUserService $connectedUserService,
        array $config
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
        $this->apiKey = $config['statsApi']['apiKey'] ?? '';
    }

    public function isStatsAvailable(): bool
    {
        if ($this->connectedUser === null || !$this->connectedUser->hasRole(UserRole::USER_ROLE_CEO)) {
            return false;
        }

        if ($this->apiKey === '') {
            return false;
        }

        if (!$this->connectedUser->hasRole(UserRole::USER_ROLE_CEO)) {
            $anrUuids = $this->getAvailableUserAnrsUuids();
            if (empty($anrUuids)) {
                return false;
            }
        }

        try {
            $client = $this->statsApiProvider->getClient();
        } catch (Throwable $e) {
            return false;
        }

        return $client['token'] === $this->apiKey;
    }

    /**
     * @param array $validatedParams Accepts the following params keys:
     *              - type Stats type (required);
     *              - dateFrom Stats period start date (optional);
     *              - dateTo Stats period end date (optional);
     *              - anrs List of Anr IDs to use for the result (optional);
     *              - aggregationPeriod One of the available options [day, week, month, quarter, year] (optional).
     *                                  Not used on StatsApi, will might be used to aggregate the received data here.
     *
     * @return array
     *
     * @throws AccessForbiddenException
     * @throws Exception
     * @throws StatsFetchingException
     */
    public function getStats(array $validatedParams): array
    {
        if (empty($validatedParams['type'])) {
            throw new LogicException("Filter parameter 'type' is mandatory to get the stats.");
        }
        $requestParams['type'] = $validatedParams['type'];

        if (empty($validatedParams['getLast'])) {
            $requestParams['date_from'] = $this->getPreparedDateFrom($validatedParams);
            $requestParams['date_to'] = $this->getPreparedDateTo($validatedParams);
            $requestParams['get_last'] = false;
        } else {
            $requestParams['get_last'] = true;
        }

        $anrUuids = $this->getFilteredAnrUuids($validatedParams);
        if (!empty($anrUuids)) {
            $requestParams['anrs'] = $anrUuids;
        }
        if (isset($validatedParams['offset'])) {
            $requestParams['offset'] = $validatedParams['offset'];
        }
        if (isset($validatedParams['limit'])) {
            $requestParams['limit'] = $validatedParams['limit'];
        }

        $statsData = $this->statsApiProvider->getStatsData($requestParams);

        if ($requestParams['type'] === StatsDataObject::TYPE_RISK) {
            $statsData = $this->formatRisksStatsData($statsData);
        } elseif (in_array(
            $requestParams['type'],
            [StatsDataObject::TYPE_THREAT, StatsDataObject::TYPE_VULNERABILITY],
            true
        )) {
            $statsData = $this->formatThreatsOrVulnerabilitiesStatsData($statsData);
        } elseif ($requestParams['type'] === StatsDataObject::TYPE_CARTOGRAPHY) {
            $statsData = $this->formatCartographyStatsData($statsData);
        } elseif ($requestParams['type'] === StatsDataObject::TYPE_COMPLIANCE) {
            $statsData = $this->formatComplianceStatsData($statsData);
        }

        return $statsData;
    }

    /**
     * @throws LogicException|AccessForbiddenException|WrongResponseFormatException|StatsFetchingException
     */
    public function getProcessedStats(array $validatedParams): array
    {
        if (empty($validatedParams['processor']) || empty($validatedParams['type'])) {
            throw new LogicException("Filter parameters 'processor' and 'type' are mandatory to get the stats.");
        }
        $requestParams['processor'] = $validatedParams['processor'];
        $requestParams['processor_params'] = $validatedParams['processor_params'];
        $requestParams['date_from'] = $this->getPreparedDateFrom($validatedParams);
        $requestParams['date_to'] = $this->getPreparedDateTo($validatedParams);
        $requestParams['type'] = $validatedParams['type'];

        $anrUuids = $this->getFilteredAnrUuids(['anrs' => []]);
        if (!empty($anrUuids)) {
            $requestParams['anrs'] = $anrUuids;
        }

        /* Seems not used.
        if (!empty($validatedParams['nbdays'])) {
            $validatedParams['nbdays'];
        }*/

        return $this->formatProcessedStatsData($this->statsApiProvider->getProcessedStatsData($requestParams));
    }

    /**
     * Collects the statistics for today.
     *
     * @param int[] $anrIds List of Anr IDs to use for the stats collection.
     * @param bool $forceUpdate Whether overwrite the data if already presented for today.
     *
     * @return array
     *
     * @throws StatsAlreadyCollectedException
     * @throws StatsFetchingException
     * @throws StatsSendingException
     * @throws WrongResponseFormatException
     */
    public function collectStats(array $anrIds = [], bool $forceUpdate = false): array
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
            : $this->anrTable->findAllExcludeSnapshots();

        $statsData = [];
        $anrUuids = [];
        foreach ($anrLists as $anr) {
            if (!$anr->isStatsCollected()) {
                continue;
            }
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

            $anrUuids[] = $anr->getUuid();
        }

        if (!empty($statsData)) {
            $this->statsApiProvider->sendStatsDataInBatch($statsData);
        }

        return $anrUuids;
    }

    public function deleteStatsForAnr(string $anrUuid): void
    {
        $this->statsApiProvider->deleteStatsForAnr($anrUuid);
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
                    'impact' => $scalesRanges[ScaleSuperClass::TYPE_IMPACT],
                    'probability' => $scalesRanges[ScaleSuperClass::TYPE_THREAT],
                    'likelihood' => $likelihoodScales,
                ],
                'risks' => [
                    'current' => [
                        'informational' => $informationalRisksValues[StatsDataObject::TYPE_CARTOGRAPHY]['current'],
                        'operational' => $operationalRisksValues[StatsDataObject::TYPE_CARTOGRAPHY]['current'],
                    ],
                    'residual' => [
                        'informational' => $informationalRisksValues[StatsDataObject::TYPE_CARTOGRAPHY]['residual'],
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
                    'label1' => $soaCategory->getlabel(1),
                    'label2' => $soaCategory->getlabel(2),
                    'label3' => $soaCategory->getlabel(3),
                    'label4' => $soaCategory->getlabel(4),
                    'controls' => [],
                ];
                $targetCategoryValues = [
                    'label1' => $soaCategory->getlabel(1),
                    'label2' => $soaCategory->getlabel(2),
                    'label3' => $soaCategory->getlabel(3),
                    'label4' => $soaCategory->getlabel(4),
                    'controls' => [],
                ];
                $targetSoaCount = 0;
                $currentSoaTotalCompliance = '0';
                foreach ($statementOfApplicabilityList as $soa) {
                    $measure = $soa->getMeasure();
                    if ($measure === null) {
                        continue;
                    }
                    $currentCategoryValues['controls'][] = [
                        'code' => $measure->getCode(),
                        'measure' => $measure->getUuid(),
                        'value' => $soa->getEx() === 1 ? '0.00' : bcmul((string)$soa->getCompliance(), '0.20', 2),
                    ];
                    $targetCategoryValues['controls'][] = [
                        'code' => $measure->getCode(),
                        'measure' => $measure->getUuid(),
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

                    if (!isset($complianceStatsValues[$reference->getUuid()])) {
                        $complianceStatsValues[$reference->getUuid()] = [
                            'referential' => $reference->getUuid(),
                            'label1' => $reference->getLabel(1),
                            'label2' => $reference->getLabel(2),
                            'label3' => $reference->getLabel(3),
                            'label4' => $reference->getLabel(4),
                            'current' => [],
                            'target' => [],
                        ];
                    }
                    $complianceStatsValues[$reference->getUuid()]['current'][] = $currentCategoryValues;
                    $complianceStatsValues[$reference->getUuid()]['target'][] = $targetCategoryValues;
                }
            }
        }

        return array_values($complianceStatsValues);
    }

    private function prepareScalesRanges(Anr $anr): array
    {
        $scalesRanges = [
            ScaleSuperClass::TYPE_IMPACT => [],
            ScaleSuperClass::TYPE_THREAT => [],
            ScaleSuperClass::TYPE_VULNERABILITY => [],
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
        foreach ($scalesRanges[ScaleSuperClass::TYPE_IMPACT] as $i) {
            foreach ($scalesRanges[ScaleSuperClass::TYPE_THREAT] as $t) {
                foreach ($scalesRanges[ScaleSuperClass::TYPE_VULNERABILITY] as $v) {
                    $value = -1;
                    if ($i !== -1 && $t !== -1 && $v !== -1) {
                        $value = $t * $v;
                    }
                    if (!in_array($value, $headersResult, true)) {
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

                $otvKey = md5($riskData['objectId'] . $riskData['threatId'] . $riskData['vulnerabilityId']);
                $threatsValues = $this->getThreatsValues($threatsValues, $otvKey, $riskData);
                $vulnerabilitiesValues = $this->getVulnerabilitiesValues(
                    $vulnerabilitiesValues,
                    $otvKey,
                    $riskData
                );
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
        $isScopeGlobal = $riskData['scope'] === ObjectSuperClass::SCOPE_GLOBAL;
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
        $isScopeGlobal = $riskData['scope'] === ObjectSuperClass::SCOPE_GLOBAL;
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
        $isScopeGlobal = $riskData['scope'] === ObjectSuperClass::SCOPE_GLOBAL;
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

        $instanceRiskOps = $this->operationalRiskTable->findRisksDataForStatsByAnr($anr);
        foreach ($instanceRiskOps as $instanceRiskOp) {
            $maxImpact = $this->getOperationalRiskMaxImpact($instanceRiskOp);
            $riskValue = $instanceRiskOp->getCacheNetRisk();
            $prob = $instanceRiskOp->getNetProb();
            [$currentRisksCounters, $currentRisksDistributed] = $this->countOperationalRisks(
                $anr,
                $riskValue,
                $currentRisksCounters,
                $maxImpact,
                $prob,
                $currentRisksDistributed
            );
            if ($instanceRiskOp->getCacheTargetedRisk() > -1) {
                $maxImpact = $this->getOperationalRiskMaxImpact($instanceRiskOp, 'Targeted');
                $riskValue = $instanceRiskOp->getCacheTargetedRisk();
                $prob = $instanceRiskOp->getTargetedProb();
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

    private function getOperationalRiskMaxImpact(InstanceRiskOp $instanceRiskOp, string $impactType = 'Net'): int
    {
        $maxImpact = 0;
        foreach ($instanceRiskOp->getOperationalInstanceRiskScales() as $instanceRiskScale) {
            if (!$instanceRiskScale->getOperationalRiskScaleType()->isHidden()) {
                $impactValue = $instanceRiskScale->{'get' . $impactType . 'Value'}();
                if ($maxImpact < $impactValue) {
                    $maxImpact = $impactValue;
                }
            }
        }

        return $maxImpact;
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
        $secondRiskLevel = $riskType === 'informational' ? $anr->getSeuil2() : $anr->getSeuilRolf2();

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
    private function getFilteredAnrUuids(array $validatedParams): array
    {
        $anrUuids = [];
        if (!$this->connectedUser?->hasRole(UserRole::USER_ROLE_CEO)) {
            $anrUuids = $this->getAvailableUserAnrsUuids($validatedParams['anrs']);
            if (empty($anrUuids)) {
                throw new AccessForbiddenException();
            }
        } elseif (!empty($validatedParams['anrs'])) {
            foreach ($this->anrTable->findByIds($validatedParams['anrs']) as $anr) {
                $anrUuids[] = $anr->getUuid();
            }
        } else {
            foreach ($this->anrTable->findVisibleOnDashboard() as $anr) {
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
            $risksData = $data->getData();
            if (empty($risksData['risks']['current']) || empty($risksData['risks']['residual'])) {
                continue;
            }
            $anrUuid = $data->getAnr();
            $anrUuids[] = $anrUuid;
            $formattedResult[$anrUuid] = [
                'current' => [
                    'category' => '',
                    'uuid' => $anrUuid,
                    'series' => $this->getSeriesForType('current', $risksData),
                ],
                'residual' => [
                    'category' => '',
                    'uuid' => $anrUuid,
                    'series' => $this->getSeriesForType('residual', $risksData),
                ],
            ];
        }

        if (!empty($anrUuids)) {
            $anrs = $this->anrTable->findByUuids($anrUuids);
            foreach ($anrs as $anr) {
                $formattedResult[$anr->getUuid()]['current']['category'] = $anr->getLabel();
                $formattedResult[$anr->getUuid()]['residual']['category'] = $anr->getLabel();
            }

            if (!empty($formattedResult)) {
                $formattedResult = [
                    'current' => array_column($formattedResult, 'current'),
                    'residual' => array_column($formattedResult, 'residual'),
                ];
                foreach ($formattedResult as $key => &$value) {
                    usort($value, static function ($a, $b) {
                        return $a['category'] <=> $b['category'];
                    });
                }
            }
        }

        return $formattedResult;
    }

    private function getSeriesForType(string $riskType, array $data): array
    {
        $series = [];
        $risks = $data['risks'][$riskType]['informational'];

        foreach ($risks as $index => $risk) {
            $series[] = [
                'label' => $risk['level'],
                'riskInfo' => $risk['value'],
                'riskOp' => $data['risks'][$riskType]['operational'][$index]['value']
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

        $userLanguageNumber = $this->connectedUser !== null ? $this->connectedUser->getLanguage() : 1;
        $formattedResult = [];
        foreach ($statsData as $data) {
            $anrUuid = $data->getAnr();
            $anr = $this->anrTable->findOneByUuid($anrUuid);
            if ($anr === null) {
                continue;
            }
            $anrLanguage = $anr->getLanguage();

            $dataSets = $data->getData();
            if (!isset($formattedResult[$anrUuid])) {
                $formattedResult[$anrUuid] = [
                    'category' => $anr->getLabel(),
                    'uuid' => $anrUuid,
                    'series' => []
                ];
            }

            foreach ($dataSets as $dataSet) {
                $dataSetUuid = $dataSet['uuid'];
                if (isset($formattedResult[$anrUuid]['series'][$dataSetUuid])) {
                    $formattedResult[$anrUuid]['series'][$dataSetUuid]['series'][] = [
                        'date' => $data->getDate(),
                        'count' => $dataSet['count'],
                        'maxRisk' => $dataSet['maxRisk'],
                        'averageRate' => $dataSet['averageRate']
                    ];
                } else {
                    $formattedResult[$anrUuid]['series'][$dataSetUuid] = [
                        'category' => !empty($dataSet['label' . $userLanguageNumber]) ?
                            $dataSet['label' . $userLanguageNumber] :
                            $dataSet['label' . $anrLanguage],
                        'uuid' => $dataSetUuid,
                        'series' => [
                            [
                                'date' => $data->getDate(),
                                'count' => $dataSet['count'],
                                'maxRisk' => $dataSet['maxRisk'],
                                'averageRate' => $dataSet['averageRate'],
                            ]
                        ],
                    ];
                }

                usort($formattedResult[$anrUuid]['series'][$dataSetUuid]['series'], function ($a, $b) {
                    return $a['date'] <=> $b['date'];
                });
            }
        }

        $formattedResult = array_values($formattedResult);
        foreach ($formattedResult as &$resultByAnr) {
            $resultByAnr['series'] = array_values($resultByAnr['series']);
        }

        usort($formattedResult, static function ($a, $b) {
            return $a['category'] <=> $b['category'];
        });

        return $formattedResult;
    }

    /**
     * The formatted result is currently performed only for a single day (assumed that anr is unique per set of data).
     * The result contains only informational risks matrix with data.
     *
     * @param StatsDataObject[] $statsData
     *
     * @return array
     */
    private function formatCartographyStatsData(array $statsData): array
    {
        if (empty($statsData)) {
            return [];
        }

        $formattedResult = [];
        foreach ($statsData as $dataSet) {
            $anrUuid = $dataSet->getAnr();
            $anr = $this->anrTable->findOneByUuid($anrUuid);
            if ($anr === null) {
                continue;
            }
            if (!isset($formattedResult[$anrUuid])) {
                $formattedResult[$anrUuid] = [
                    'currentInfo' => [
                        'category' => $anr->getLabel(),
                        'uuid' => $anrUuid,
                        'series' => [],
                    ],
                    'residualInfo' => [
                        'category' => $anr->getLabel(),
                        'uuid' => $anrUuid,
                        'series' => [],
                    ],
                    'currentOp' => [
                        'category' => $anr->getLabel(),
                        'uuid' => $anrUuid,
                        'series' => [],
                    ],
                    'residualOp' => [
                        'category' => $anr->getLabel(),
                        'uuid' => $anrUuid,
                        'series' => [],
                    ],
                ];
            }

            $data = $dataSet->getData();
            $scalesImpact = !empty($data['scales']['impact']) ? $data['scales']['impact'] : [];
            $maxImpact = empty($scalesImpact) ? 0 : max($data['scales']['impact']);
            $minImpact = empty($scalesImpact) ? 0 : min($data['scales']['impact']);
            $maxLikelihood = empty($data['scales']['likelihood']) ? 0 : max($data['scales']['likelihood']);
            $minLikelihood = empty($data['scales']['likelihood']) ? 0 : min($data['scales']['likelihood']);
            $maxProbability = empty($data['scales']['probability']) ? 0 : max($data['scales']['probability']);
            $minProbability = empty($data['scales']['probability']) ? 0 : min($data['scales']['probability']);

            foreach ($scalesImpact as $impactValue) {
                $y = $this->convertValueWithinNewScalesRange($impactValue, $minImpact, $maxImpact, 0, 4);
                foreach ($data['scales']['likelihood'] as $likelihoodValue) {
                    $x = $this
                        ->convertValueWithinNewScalesRange($likelihoodValue, $minLikelihood, $maxLikelihood, 0, 20);
                    $seriesKey = $y . $x;
                    if (!isset($formattedResult[$anrUuid]['informational']['currentInfo']['series'][$seriesKey])) {
                        $formattedResult[$anrUuid]['informational']['currentInfo']['series'][$seriesKey] = [
                            'y' => $y,
                            'x' => $x,
                            'value' => $data['risks']['current']['informational'][$impactValue][$likelihoodValue]
                                ?? null,
                        ];
                    } elseif (isset($data['risks']['current']['informational'][$impactValue][$likelihoodValue])) {
                        $formattedResult[$anrUuid]['informational']['currentInfo']['series'][$seriesKey]['value'] +=
                            $data['risks']['current']['informational'][$impactValue][$likelihoodValue];
                    }

                    if (!isset($formattedResult[$anrUuid]['informational']['residualInfo']['series'][$seriesKey])) {
                        $formattedResult[$anrUuid]['informational']['residualInfo']['series'][$seriesKey] = [
                            'y' => $y,
                            'x' => $x,
                            'value' => $data['risks']['residual']['informational'][$impactValue][$likelihoodValue]
                                ?? null,
                        ];
                    } elseif (isset($data['risks']['residual']['informational'][$impactValue][$likelihoodValue])) {
                        $formattedResult[$anrUuid]['informational']['residualInfo']['series'][$seriesKey]['value'] +=
                            $data['risks']['residual']['informational'][$impactValue][$likelihoodValue];
                    }
                }

                foreach ($data['scales']['probability'] as $probabilityValue) {
                    $x = $this
                        ->convertValueWithinNewScalesRange($probabilityValue, $minProbability, $maxProbability, 0, 4);
                    $seriesKey = $y . $x;
                    if (!isset($formattedResult[$anrUuid]['operational']['currentOp']['series'][$seriesKey])) {
                        $formattedResult[$anrUuid]['operational']['currentOp']['series'][$seriesKey] = [
                            'y' => $y,
                            'x' => $x,
                            'value' => $data['risks']['current']['operational'][$impactValue][$probabilityValue]
                                ?? null,
                        ];
                    } elseif (isset($data['risks']['current']['operational'][$impactValue][$probabilityValue])) {
                        $formattedResult[$anrUuid]['operational']['currentOp']['series'][$seriesKey]['value']
                            += $data['risks']['current']['operational'][$impactValue][$probabilityValue];
                    }

                    if (!isset($formattedResult[$anrUuid]['operational']['residualOp']['series'][$seriesKey])) {
                        $formattedResult[$anrUuid]['operational']['residualOp']['series'][$seriesKey] = [
                            'y' => $y,
                            'x' => $x,
                            'value' => $data['risks']['residual']['operational'][$impactValue][$probabilityValue]
                                ?? null,
                        ];
                    } elseif (isset($data['risks']['residual']['operational'][$impactValue][$probabilityValue])) {
                        $formattedResult[$anrUuid]['operational']['residualOp']['series'][$seriesKey]['value'] +=
                            $data['risks']['residual']['operational'][$impactValue][$probabilityValue];
                    }
                }
            }

            $formattedResult[$anrUuid]['currentInfo']['series'] = \is_array(
                $formattedResult[$anrUuid]['informational']['currentInfo']['series']
            ) ? array_values($formattedResult[$anrUuid]['informational']['currentInfo']['series']) : [];
            $formattedResult[$anrUuid]['residualInfo']['series'] = \is_array(
                $formattedResult[$anrUuid]['informational']['residualInfo']['series']
            ) ? array_values($formattedResult[$anrUuid]['informational']['residualInfo']['series']): [];
            $formattedResult[$anrUuid]['currentOp']['series'] = \is_array(
                $formattedResult[$anrUuid]['operational']['currentOp']['series']
            ) ? array_values($formattedResult[$anrUuid]['operational']['currentOp']['series']): [];
            $formattedResult[$anrUuid]['residualOp']['series'] = \is_array(
                $formattedResult[$anrUuid]['operational']['residualOp']['series']
            )? array_values($formattedResult[$anrUuid]['operational']['residualOp']['series']): [];
        }

        if (!empty($formattedResult)) {
            $formattedResult = [
                'informational' => [
                    'current' => array_column($formattedResult, 'currentInfo'),
                    'residual' => array_column($formattedResult, 'residualInfo'),
                ],
                'operational' => [
                    'current' => array_column($formattedResult, 'currentOp'),
                    'residual' => array_column($formattedResult, 'residualOp'),
                ]
            ];

            foreach ($formattedResult['informational'] as $key => &$value) {
                usort($value, static function ($a, $b) {
                    return $a['category'] <=> $b['category'];
                });
            }
            unset($value);
            foreach ($formattedResult['operational'] as &$value) {
                usort($value, static function ($a, $b) {
                    return $a['category'] <=> $b['category'];
                });
            }
        }

        return $formattedResult;
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

        $formattedResult = [];
        foreach ($statsData as $data) {
            $anrUuid = $data->getAnr();
            $anr = $this->anrTable->findOneByUuid($anrUuid);
            if ($anr === null) {
                continue;
            }
            $anrLanguage = $anr->getLanguage();
            $dataSets = $data->getData();
            if (!isset($formattedResult[$anrUuid])) {
                $formattedResult[$anrUuid] = [
                    'category' => $anr->getLabel(),
                    'series' => [],
                ];
            }

            foreach ($dataSets as $dataSet) {
                $referentialLabel = $dataSet['label' . $anrLanguage];
                $formattedResult[$anrUuid]['series'][$referentialLabel] = [
                    'category' => $referentialLabel,
                    'series' => [],
                ];

                foreach ($dataSet['target'] as $targetData) {
                    $formattedResult[$anrUuid]['series'][$referentialLabel]['series']['target'][] =
                        $this->getMeasuresData($targetData, $anrLanguage);
                }
                foreach ($dataSet['current'] as $currentData) {
                    $formattedResult[$anrUuid]['series'][$referentialLabel]['series']['current'][] =
                        $this->getMeasuresData($currentData, $anrLanguage);
                }
            }
        }

        if (!empty($formattedResult)) {
            $formattedResult = array_values($formattedResult);
            foreach ($formattedResult as &$resultByAnr) {
                $resultByAnr['series'] = array_values($resultByAnr['series']);
            }

            usort($formattedResult, static function ($a, $b) {
                return $a['category'] <=> $b['category'];
            });
        }

        return $formattedResult;
    }

    private function getMeasuresData(array $data, int $anrLanguage): array
    {
        $measuresData = [
            'label' => $data['label' . $anrLanguage],
            'value' => $data['value'],
            'data' => [],
        ];
        if (!empty($data['controls'])) {
            foreach ($data['controls'] as $control) {
                $measuresData['data'][] = [
                    'label' => $control['code'],
                    'value' => $control['value'],
                ];
            }
        }

        return $measuresData;
    }

    private function formatProcessedStatsData(array $data): array
    {
        $userLanguageNumber = $this->connectedUser !== null ? $this->connectedUser->getLanguage() : 1;
        $formattedResponse = [];
        foreach ($data as $processedStats) {
            $dataRow = $processedStats;
            if (!empty($dataRow['labels'])) {
                if (!empty($dataRow['labels']['label' . $userLanguageNumber])) {
                    $dataRow['label'] = $dataRow['labels']['label' . $userLanguageNumber];
                } else {
                    foreach ($dataRow['labels'] as $label) {
                        if (!empty($label)) {
                            $dataRow['label'] = $label;
                        }
                    }
                }
                unset($dataRow['labels']);
            }

            if (!empty($dataRow['values'])) {
                usort($dataRow['values'], static function ($a, $b) {
                    return $a['date'] <=> $b['date'];
                });
            }

            $formattedResponse[] = $dataRow;
        }

        return $formattedResponse;
    }

    private function getAvailableUserAnrsUuids(array $anrIds = []): array
    {
        $anrUuids = [];
        $userAnrs = $this->connectedUser !== null ? $this->connectedUser->getUserAnrs() : [];
        $snapshotAnrsIds = [];
        if (!$userAnrs->isEmpty()) {
            $snapshots = $this->snapshotTable->findAll();
            foreach ($snapshots as $snapshot) {
                $snapshotAnrsIds[] = $snapshot->getAnr()->getId();
            }
        }
        foreach ($userAnrs as $userAnr) {
            /** @var Anr $anr */
            $anr = $userAnr->getAnr();
            if ((!empty($anrIds) && !in_array($anr->getId(), $anrIds, true))
                || in_array($anr->getId(), $snapshotAnrsIds, true)
            ) {
                continue;
            }

            $anrUuids[] = $anr->getUuid();
        }

        return $anrUuids;
    }
}
