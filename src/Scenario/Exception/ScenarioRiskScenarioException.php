<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Exception;

use RuntimeException;

/** A domain error that can be returned as a safe Scenario API response. */
final class ScenarioRiskScenarioException extends RuntimeException
{
    public function __construct(private string $reason, string $message)
    {
        parent::__construct($message);
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
