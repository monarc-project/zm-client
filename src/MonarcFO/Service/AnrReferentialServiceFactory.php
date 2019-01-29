<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Referential Service Factory
 *
 * Class AnrReferentialServiceFactory
 * @package MonarcFO\Service
 */
class AnrReferentialServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\ReferentialTable',
        'entity' => 'MonarcFO\Model\Entity\Referential',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'selfCoreService' => 'MonarcCore\Service\ReferentialService',
    ];
}
