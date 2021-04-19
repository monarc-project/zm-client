<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;

/**
 * Factory class attached to AnrAssetService
 * @package Monarc\FrontOffice\Service
 */
class AnrAssetServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'Monarc\FrontOffice\Model\Entity\Asset',
        'table' => 'Monarc\FrontOffice\Model\Table\AssetTable',
        'userAnrTable' => 'Monarc\FrontOffice\Model\Table\UserAnrTable',
    ];
}
