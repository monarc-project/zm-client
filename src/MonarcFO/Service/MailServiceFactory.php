<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Mais Service Factory
 *
 * Class MailServiceFactory
 * @package MonarcFO\Service
 */
class MailServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\MailService";

    protected $ressources = array();
}
