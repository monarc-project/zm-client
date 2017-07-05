<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
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
