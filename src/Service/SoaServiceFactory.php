<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */


namespace Monarc\FrontOffice\Service;
use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\FrontOffice\Model\Entity\Soa;
use Monarc\FrontOffice\Model\Table\SoaTable;

/**
 * Anr Object Service Factory
 *
 * Class AnrObjectServiceFactory
 * @package Monarc\Core\Service
 */
class SoaServiceFactory extends AbstractServiceFactory
{

    protected $ressources = [
      'entity' => 'Monarc\FrontOffice\Model\Entity\Soa',
      'table' => 'Monarc\FrontOffice\Model\Table\SoaTable',
      'anrTable' => 'Monarc\FrontOffice\Model\Table\AnrTable',
      'userAnrTable' => 'Monarc\FrontOffice\Model\Table\UserAnrTable',
      'measureService' => 'Monarc\FrontOffice\Service\AnrMeasureService',
      'riskService' => 'Monarc\FrontOffice\Service\AnrRiskService',
      'riskOpService' => 'Monarc\FrontOffice\Service\AnrRiskOpService',
    ];
}
