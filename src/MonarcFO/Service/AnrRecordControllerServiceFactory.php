<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Record Controller Service Factory
 *
 * Class AnrRecordControllerServiceFactory
 * @package MonarcFO\Service
 */
class AnrRecordControllerServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\RecordControllerTable',
        'entity' => 'MonarcFO\Model\Entity\RecordController',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
    ];
}
