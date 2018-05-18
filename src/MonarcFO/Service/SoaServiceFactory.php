<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */


namespace MonarcFO\Service;
use MonarcCore\Service\AbstractServiceFactory;
use MonarcFO\Model\Entity\Soa;
use MonarcFO\Model\Table\SoaTable;

/**
 * Anr Object Service Factory
 *
 * Class AnrObjectServiceFactory
 * @package MonarcCore\Service
 */
class SoaServiceFactory extends AbstractServiceFactory
{     //file_put_contents('php://stderr', print_r('testservfact', TRUE));

    protected $ressources = [
      'entity' => 'MonarcFO\Model\Entity\Soa',
      'table' => 'MonarcFO\Model\Table\SoaTable',
      //'AmvTable' => 'MonarcFO\Model\Table\AmvTable',
      //'InstanceRiskTable' => 'MonarcFO\Model\Table\InstanceRiskTable',
      //'MeasureTable' => 'MonarcFO\Model\Table\MeasureTable',


      //  'measure' => 'MonarcFO\Model\Entity\Measure',
      //  'MeasureService' => 'MonarcCore\Service\MeasureService'
    ];
}
