<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;

/**
 * Proxy class to instantiate Monarc\Core's MailService, with Monarc\FrontOffice's services
 * @package Monarc\FrontOffice\Service
 */
class MailServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\Monarc\Core\\Service\\MailService";

    protected $ressources = [];
}
