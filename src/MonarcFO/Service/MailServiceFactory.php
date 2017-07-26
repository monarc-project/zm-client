<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Proxy class to instantiate MonarcCore's MailService, with MonarcFO's services
 * @package MonarcFO\Service
 */
class MailServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\MailService";

    protected $ressources = [];
}
