<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Exception;

/** A safe, client-actionable failure for the legacy Scenario handoff. */
final class ScenarioHandoffException extends \RuntimeException
{
    public function __construct(
        private string $errorCode,
        private int $status,
        string $message
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function status(): int
    {
        return $this->status;
    }
}
