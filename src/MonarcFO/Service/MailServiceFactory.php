<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Mais Service Factory
 *
 * Class MailServiceFactory
 * @package MonarcFO\Service
 */
class MailServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\MailService";

    protected $ressources = [];
}
