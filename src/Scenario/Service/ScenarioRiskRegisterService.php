<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Service;

use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Scenario\Table\ScenarioRiskRegisterTable;

/** Provides the authorised read-only risk-register projection. */
final class ScenarioRiskRegisterService
{
    public function __construct(private ScenarioRiskRegisterTable $register)
    {
    }

    /** @param array<string, int|string|bool|null> $query @return array<string, mixed> */
    public function list(Anr $anr, array $query): array
    {
        return $this->register->list((int) $anr->getId(), $query);
    }
}
