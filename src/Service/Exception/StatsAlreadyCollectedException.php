<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Service\Exception;

use Monarc\Core\Exception\Exception;
use Throwable;

/**
 * TODO: add ValidationException or something to extend and configure the module to return the errors in the response.
 */
class StatsAlreadyCollectedException extends Exception
{
    public function __construct($message = '', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message ?? 'The stats is already collected for today.', $code, $previous);
    }
}
