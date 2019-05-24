<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * RecommandationSet Service Factory
 *
 * Class AnrRecommandationSetServiceFactory
 * @package MonarcFO\Service
 */
class AnrRecommandationSetServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\RecommandationSetTable',
        'entity' => 'MonarcFO\Model\Entity\RecommandationSet',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
    ];
}
