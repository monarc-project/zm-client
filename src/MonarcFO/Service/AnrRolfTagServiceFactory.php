<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Factory class attached to AnrRolfTagService
 * @package MonarcFO\Service
 */
class AnrRolfTagServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'MonarcFO\Model\Entity\RolfTag',
        'table' => 'MonarcFO\Model\Table\RolfTagTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
    ];
}
