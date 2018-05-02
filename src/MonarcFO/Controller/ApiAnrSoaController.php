<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Controller;

use MonarcFO\Model\Entity\Soa;
use MonarcFO\Model\Table\SoaTable;
use MonarcFO\Service\SoaService;
use Zend\View\Model\JsonModel;

/**
 * Api Anr Soa Controller
 *
 * Class ApiAnrSoaController
 * @package MonarcFO\Controller
 */
class ApiAnrSoaController extends  ApiAnrAbstractController
{
    protected $name = 'Soa-list';
  //  protected $dependencies = ['anr'];

  //  protected $dependencies = ['anr', 'asset', 'object', 'root', 'parent'];
  /**
   * @inheritdoc
   */
   public function getList()
   {
     //file_put_contents('php://stderr', print_r('controller', TRUE));
    //   $order = $this->params()->fromQuery('order');
    //   $filter = $this->params()->fromQuery('filter');
    //   $anrId = (int) $this->params()->fromRoute('anrId');

       /** @var SoaService $service */
       $service = $this->getService();
       $soa = $service->getList(0, 0, null,null, null); //['anr' => $anrId]

       return new JsonModel(array(
          'count' => count($soa),
           $this->name => $soa
       ));
   }



}
