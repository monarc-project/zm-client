<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Exception;

use Monarc\Core\Exception\Exception;
use Throwable;

class InvalidConfigurationException extends Exception
{
    public function __construct(array $missedConfigurationOptions, $code = 0, Throwable $previous = null)
    {
        parent::__construct(sprintf(
            'The following configuration options are missed in the config: "%s"',
            implode(', ', $missedConfigurationOptions)
        ), $code, $previous);
    }
}
