<?php

namespace Monarc\FrontOffice\Stats\Exception;

use Monarc\Core\Exception\Exception;
use Throwable;

class WrongResponseFormatException extends Exception
{
    public function __construct(array $missingFields, $code = 0, Throwable $previous = null)
    {
        parent::__construct('The following fields are mandatory: ' . implode(', ', $missingFields), $code, $previous);
    }
}
