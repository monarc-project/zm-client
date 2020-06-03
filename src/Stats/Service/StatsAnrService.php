<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Stats\Service;

use DateTime;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\MonarcObject;
use Monarc\FrontOffice\Model\Entity\Scale;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskOpTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskTable;
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

    /** @var array */
    private $informationalRisksData;

    /** @var array */
    private $operationalRisksData;

    public function __construct(
        AnrTable $anrTable,
        ScaleTable $scaleTable,
        InstanceRiskTable $informationalRiskTable,
        InstanceRiskOpTable $operationalRiskTable,
        StatsApiProvider $statsApiProvider
    ) {
        $this->anrTable = $anrTable;
        $this->scaleTable = $scaleTable;
        $this->informationalRiskTable = $informationalRiskTable;
        $this->operationalRiskTable = $operationalRiskTable;
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
            $anrStats = $this->collectAnrStats($anr);

            $statsData[] = new StatsDataObject([
                'anr' => $anr->getUuid(),
                'type' => StatsDataObject::TYPE_RISK,
                'data' => $this->transformStatsDataToRisksTypeFormat($anrStats),
            ]);
            $statsData[] = new StatsDataObject([
                'anr' => $anr->getUuid(),
                'type' => StatsDataObject::TYPE_THREAT,
                'data' => $this->collectAnrThreatStats($anr),
            ]);
            $statsData[] = new StatsDataObject([
                'anr' => $anr->getUuid(),
                'type' => StatsDataObject::TYPE_VULNERABILITY,
                'data' => $this->collectAnrVulnerabilityStats($anr),
            ]);
            $statsData[] = new StatsDataObject([
                'anr' => $anr->getUuid(),
                'type' => StatsDataObject::TYPE_CARTOGRAPHY,
                'data' => $anrStats,
            ]);
        }

        if (!empty($statsData)) {
            $this->statsApiProvider->sendStatsDataInBatch($statsData);
        }
    }

    private function collectAnrStats(Anr $anr): array
    {
        $scalesRanges = $this->prepareScalesRanges($anr);
        $likelihoodScales = $this->calculateScalesColumnValues($scalesRanges);

        $informationalRisksValues = $this->calculateInformationalRisks($anr);
        $operationalRisksValues = $this->calculateOperationalRisks($anr);

        return [
            'scales' => [
                'impact' => $scalesRanges[Scale::TYPE_IMPACT],
                'probability' => $scalesRanges[Scale::TYPE_THREAT],
                'likelihood' => $likelihoodScales,
            ],
            'risks' => [
                'current' => [
                    'informational' => $informationalRisksValues['current'],
                    'operational' => $operationalRisksValues['current'],
                ],
                'residual' => [
                    'informational' => $informationalRisksValues['residual'],
                    'operational' => $operationalRisksValues['residual'],
                ]
            ],
        ];
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
     * Calculates the number of risks for each impact/MxV combo.
     */
    public function calculateInformationalRisks(Anr $anr): array
    {
        $currentRisksValues = [];
        $targetRisksValues = [];
        $risksData = $this->informationalRiskTable->findRisksDataForStatsByAnr($anr);
        foreach ($risksData as $riskData) {
            $confidentiality = $riskData['threatConfidentiality'] ? $riskData['instanceConfidentiality'] : 0;
            $integrity = $riskData['threatIntegrity'] ? $riskData['instanceIntegrity'] : 0;
            $availability = $riskData['threatAvailability'] ? $riskData['instanceAvailability'] : 0;

            $maxImpact = max($confidentiality, $integrity, $availability);
            if ($maxImpact < 0) {
                continue;
            }
            $amvKey = $riskData['assetId'] . '_' . $riskData['threatId'] . '_' . $riskData['vulnerabilityId'];
            if ($riskData['cacheMaxRisk'] > -1) {
                $context = $this->getRiskContext($anr, $amvKey, $maxImpact, $riskData['cacheMaxRisk']);
                $this->setRiskValues($currentRisksValues, $context, $amvKey, $riskData);
            }
            if ($riskData['cacheTargetedRisk'] > -1) {
                $context = $this->getRiskContext($anr, $amvKey, $maxImpact, $riskData['cacheTargetedRisk']);
                $this->setRiskValues($targetRisksValues, $context, $amvKey, $riskData);
            }
        }

        return [
            'current' => $this->countInformationalRisksValues($currentRisksValues),
            'residual' => $this->countInformationalRisksValues($targetRisksValues),
        ];
    }

    private function getRiskContext(Anr $anr, string $amvKey, int $maxImpact, int $maxRiskValue): array
    {
        return [
            'impact' => $maxImpact,
            'likelihood' => $maxImpact > 0 ? round($maxRiskValue / $maxImpact) : 0,
            'amv' => $amvKey,
            'max' => $maxRiskValue,
            'level' => $this->getRiskLevel($anr, $maxRiskValue),
        ];
    }

    private function setRiskValues(&$risksValues, $riskContext, $amvKey, $riskData): void
    {
        $objectId = $riskData['objectId'];
        if ($riskData['scope'] === MonarcObject::SCOPE_GLOBAL) {
            if (empty($risksValues[$objectId][$amvKey])
                || $riskContext['max'] > $risksValues[$objectId][$amvKey][0]
            ) {
                $risksValues[$objectId][$amvKey][0] = $riskContext;
            }
        } else {
            $risksValues[$objectId][$amvKey][$riskData['id']] = $riskContext;
        }
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
            foreach ($risksValue as $contexts) {
                foreach ($contexts as $context) {
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

        return compact('counters', 'distributed');
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
        $targetRisksCounters = [];
        $targetRisksDistributed = [
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
            [$targetRisksCounters, $targetRisksDistributed] = $this->countOperationalRisks(
                $anr,
                $riskValue,
                $targetRisksCounters,
                $maxImpact,
                $prob,
                $targetRisksDistributed
            );
        }

        return [
            'current' => [
                'counters' => $currentRisksCounters,
                'distributed' => $currentRisksDistributed,
            ],
            'residual' => [
                'counters' => $targetRisksCounters,
                'distributed' => $targetRisksDistributed,
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

    private function transformStatsDataToRisksTypeFormat(array $statsData): array
    {
        return [
            'total' => [
                'currentRisks' => [
                    'informational' => $this->getTotalValue(
                        $statsData['risks']['current']['informational']['distributed']
                    ),
                    'operational' => $this->getTotalValue(
                        $statsData['risks']['current']['operational']['distributed']
                    ),
                ],
                'residualRisks' => [
                    'informational' => $this->getTotalValue(
                        $statsData['risks']['residual']['informational']['distributed']
                    ),
                    'operational' => $this->getTotalValue(
                        $statsData['risks']['residual']['operational']['distributed']
                    ),
                ],
            ],
            'risks' => [
                'current' => [
                    'informational' => $statsData['risks']['current']['informational']['distributed'],
                    'operational' => $statsData['risks']['current']['operational']['distributed'],
                ],
                'residual' => [
                    'informational' => $statsData['risks']['residual']['informational']['distributed'],
                    'operational' => $statsData['risks']['residual']['operational']['distributed'],
                ],
            ],
        ];
    }

    private function getTotalValue(array $values): int
    {
        return array_sum(array_column($values, 'value'));
    }
}
