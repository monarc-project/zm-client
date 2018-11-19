<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Referential Service Factory
 *
 * Class ReferentialServiceFactory
 * @package MonarcFO\Service
 */
class ReferentialServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\ReferentialTable',
        'entity' => 'MonarcFO\Model\Entity\Referential',
    ];
}
