<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;

/**
 * Proxy class to instantiate Monarc\Core's AssetService, with Monarc\FrontOffice's services
 * @package Monarc\FrontOffice\Service
 */
class AssetServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\Monarc\Core\\Service\\AssetService";

    protected $ressources = [
        'table' => 'Monarc\FrontOffice\Model\Table\AssetTable',
        'entity' => 'Monarc\FrontOffice\Model\Entity\Asset',
        'anrTable' => 'Monarc\FrontOffice\Model\Table\AnrTable',
        'MonarcObjectTable' => 'Monarc\FrontOffice\Model\Table\MonarcObjectTable',
        'amvService' => 'Monarc\FrontOffice\Service\AmvService',
    ];
}
