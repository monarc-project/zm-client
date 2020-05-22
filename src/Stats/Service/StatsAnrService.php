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
    public const LOW_RISK = 0;
    public const MEDIUM_RISK = 1;
    public const HIGH_RISK = 2;

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
            $statsData[] = new StatsDataObject([
                'anr' => $anr->getUuid(),
                'data' => $this->collectAnrStats($anr),
                'type' => StatsDataObject::TYPE_CARTOGRAPHY,
            ]);
        }

        if (!empty($statsData)) {
            $this->statsApiProvider->sendStatsDataInBatch($statsData);
        }
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

    private function collectAnrStats(Anr $anr): array
    {
        $scalesRanges = $this->prepareScalesRanges($anr);
        $scalesColumnsValues = $this->calculateScalesColumnValues($scalesRanges);

        $informationalRisksValues = $this->calculateInformationalRisks($anr);
        $operationalRisksValues = $this->calculateOperationalRisks($anr);

        return [
            'real' => [
                'impact' => $scalesRanges[Scale::TYPE_IMPACT],
                'probability' => $scalesRanges[Scale::TYPE_THREAT],
                'scales' => $scalesColumnsValues,
                'informationalRisks' => $informationalRisksValues['current'],
                'operationalRisks' => $operationalRisksValues['current'],
            ],
            'targeted' => [
                'impact' => $scalesRanges[Scale::TYPE_IMPACT],
                'probability' => $scalesRanges[Scale::TYPE_THREAT],
                'scales' => $scalesColumnsValues,
                'informationalRisks' => $informationalRisksValues['targeted'],
                'operationalRisks' => $operationalRisksValues['targeted'],
            ]
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
            foreach ($scalesRanges[Scale::TYPE_THREAT] as $m) {
                foreach ($scalesRanges[Scale::TYPE_VULNERABILITY] as $v) {
                    $val = -1;
                    if ($i !== -1 && $m !== -1 && $v !== -1) {
                        $val = $m * $v;
                    }
                    if (!\in_array($val, $headersResult, true)) {
                        $headersResult[] = $val;
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
        $risksData = $this->informationalRiskTable->findRisksDataForStatsByAnr($anr);
        $currentRisksValues = [];
        $targetRisksValues = [];
        foreach ($risksData as $riskData) {
            if (!isset($riskData['threatId'], $riskData['vulnerabilityId'])) {
                continue;
            }

            $confidentiality = $riskData['threatConfidentiality'] ? $riskData['instanceConfidentiality'] : 0;
            $integrity = $riskData['threatIntegrity'] ? $riskData['instanceIntegrity'] : 0;
            $availability = $riskData['threatAvailability'] ? $riskData['instanceAvailability'] : 0;

            $maxImpact = max($confidentiality, $integrity, $availability);
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
            'targeted' => $this->countInformationalRisksValues($targetRisksValues),
        ];
    }

    private function getRiskContext(Anr $anr, string $amvKey, int $maxImpact, int $maxRiskValue): array
    {
        return [
            'impact' => $maxImpact,
            'right' => $maxImpact > 0 ? round($maxRiskValue / $maxImpact) : 0,
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
        $distributed = [];
        foreach ($risksValues as $risksValue) {
            foreach ($risksValue as $contexts) {
                foreach ($contexts as $context) {
                    if ($context['impact'] < 0) {
                        continue;
                    }

                    if (!isset($counters[$context['impact']][$context['right']])) {
                        $counters[$context['impact']][$context['right']] = 0;
                    }
                    $counters[$context['impact']][$context['right']]++;

                    if (!isset($distributed[$context['level']])) {
                        $distributed[$context['level']] = 0;
                    }
                    $distributed[$context['level']]++;
                }
            }
        }

        return compact('counters', 'distributed');
    }

    /**
     * Calculates the number of operational risks for each impact/probability combo.
     */
    public function calculateOperationalRisks(Anr $anr): array
    {
        $currentRisksCounters = [];
        $currentRisksDistributed = [];
        $targetRisksCounters = [];
        $targetRisksDistributed = [];
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
            'targeted' => [
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

        if (!isset($risksDistributed[$level])) {
            $risksDistributed[$level] = 0;
        }
        $risksDistributed[$level]++;

        return [$risksCounters, $risksDistributed];
    }

    /**
     * @return int|string 0, 1, 2 corresponding to low/med/hi risk levels, or an empty string in case of invalid value.
     */
    private function getRiskLevel(Anr $anr, int $riskValue, string $riskType = 'informational')
    {
        if ($riskType === 'informational') {
            if ($riskValue <= $anr->getSeuil1()) {
                return self::LOW_RISK;
            }
            if ($riskValue <= $anr->getSeuil2()) {
                return self::MEDIUM_RISK;
            }
        } else {
            if ($riskValue <= $anr->getSeuilRolf1()) {
                return self::LOW_RISK;
            }
            if ($riskValue <= $anr->getSeuilRolf2()) {
                return self::MEDIUM_RISK;
            }
        }

        return self::HIGH_RISK;
    }
}
