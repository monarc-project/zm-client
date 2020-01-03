<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

/**
 * Factory class attached to AnrRiskOpService
 * @package Monarc\FrontOffice\Service
 */
class AnrRiskOpServiceFactory extends \Monarc\Core\Service\AbstractServiceFactory
{
    protected $ressources = [
        'table' => 'Monarc\FrontOffice\Model\Table\InstanceRiskOpTable',
        'entity' => 'Monarc\FrontOffice\Model\Entity\InstanceRiskOp',
        'instanceRiskOpService' => 'Monarc\FrontOffice\Service\AnrInstanceRiskOpService',
        'instanceTable' => 'Monarc\FrontOffice\Model\Table\InstanceTable',
        'rolfRiskTable' => 'Monarc\FrontOffice\Model\Table\RolfRiskTable',
        'rolfRiskService' => 'Monarc\FrontOffice\Service\AnrRolfRiskService',
        'MonarcObjectTable' => 'Monarc\FrontOffice\Model\Table\MonarcObjectTable',
        'anrTable' => 'Monarc\FrontOffice\Model\Table\AnrTable',
        'userAnrTable' => 'Monarc\FrontOffice\Model\Table\UserAnrTable',
        'translateService' => 'Monarc\Core\Service\TranslateService'
    ];
}
