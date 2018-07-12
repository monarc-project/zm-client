<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;
use MonarcFO\Model\Entity\Soa;
use MonarcFO\Model\Table\SoaTable;
use MonarcCore\Model\Entity\AbstractEntity;
use MonarcFO\Model\Entity\Amv;
use MonarcFO\Model\Table\AmvTable;
use MonarcFO\Model\Entity\InstanceRisk;
use MonarcFO\Model\Table\InstanceRiskTable;
use MonarcFO\Model\Entity\Measure;
use MonarcFO\Model\Table\MeasureTable;
use MonarcFO\Model\Entity\Category;
use MonarcFO\Model\Table\CategoryTable;

//use MonarcFO\Model\Entity\Measure;
//use MonarcCore\Service\MeasureService;


/**
 * @package MonarcFO\Service
 */

 class SoaService extends \MonarcCore\Service\AbstractService
 {

  protected $table;
  protected $entity;
  protected $dependencies = ['anr', 'measure'];


   







}
