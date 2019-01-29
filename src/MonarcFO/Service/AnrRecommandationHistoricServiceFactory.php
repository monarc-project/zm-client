<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Factory class attached to AnrRecommandationHistoricService
 * @package MonarcFO\Service
 */
class AnrRecommandationHistoricServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'MonarcFO\Model\Entity\RecommandationHistoric',
        'table' => 'MonarcFO\Model\Table\RecommandationHistoricTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
    ];
}
