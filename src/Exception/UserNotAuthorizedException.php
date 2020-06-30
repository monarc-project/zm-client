<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Exception;

use Monarc\Core\Exception\Exception;
use Throwable;

class UserNotAuthorizedException extends Exception
{
    public function __construct($message = 'User not authorized.', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
