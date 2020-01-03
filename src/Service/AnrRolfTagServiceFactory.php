<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;

/**
 * Factory class attached to AnrRolfTagService
 * @package Monarc\FrontOffice\Service
 */
class AnrRolfTagServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'Monarc\FrontOffice\Model\Entity\RolfTag',
        'table' => 'Monarc\FrontOffice\Model\Table\RolfTagTable',
        'anrTable' => 'Monarc\FrontOffice\Model\Table\AnrTable',
        'userAnrTable' => 'Monarc\FrontOffice\Model\Table\UserAnrTable',
    ];
}
