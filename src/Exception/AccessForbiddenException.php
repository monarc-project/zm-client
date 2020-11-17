<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Exception;

use Monarc\Core\Exception\Exception;
use Throwable;

class AccessForbiddenException extends Exception
{
    public function __construct(
        $message = 'User does not have an access to the action.',
        $code = 403,
        Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
