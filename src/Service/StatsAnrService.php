<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Service;

use DateTime;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\StatsAnrTable;
use Monarc\FrontOffice\Model\Table\ThreatTable;
use Monarc\FrontOffice\Model\Table\VulnerabilityTable;
use Monarc\FrontOffice\Service\Exception\StatsAlreadyCollectedException;

class StatsAnrService
{
    /** @var StatsAnrTable */
    private $statsAnrTable;

    /** @var AnrTable */
    private $anrTable;

    /** @var ThreatTable */
    private $threatTable;

    /** @var VulnerabilityTable */
    private $vulnerabilityTable;

    public function __construct(
        StatsAnrTable $statsAnrTable,
        AnrTable $anrTable,
        ThreatTable $threatTable,
        VulnerabilityTable $vulnerabilityTable
    ) {
        $this->statsAnrTable = $statsAnrTable;
        $this->anrTable = $anrTable;
        $this->threatTable = $threatTable;
        $this->vulnerabilityTable = $vulnerabilityTable;
    }

    /**
     * TODO: - return list of generated object or void as it is,
     *       - think if we need a param to return the generated stats and not to save it.
     *
     * Collects the statistics for today.
     *
     * @param array $anrIds List of Anr IDs to use for the stats collection.
     * @param bool $forceUpdate Whether or not overwrite the data if already presented for today.
     */
    public function collectStats(array $anrIds = [], bool $forceUpdate = false): void
    {
        $currentDate = new DateTime();
        $statsAnrsFromToday = $this->statsAnrTable->findByDateOfCreatedAt($currentDate);
        if (!empty($statsAnrsFromToday) && !$forceUpdate) {
            throw new StatsAlreadyCollectedException();
        }


        // TODO:
        //  - go thorough the Dashboard generation logic and implement it here.
        //  - save the generated data.
        //  - return or not the objects list ?
    }

    /**
     *
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

        // TODO: use the table class to fetch and the format the output data with use https://github.com/doctrine/doctrine-laminas-hydrator
    }
}
