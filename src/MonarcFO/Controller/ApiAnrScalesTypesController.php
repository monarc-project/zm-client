<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Controller;
use Zend\View\Model\JsonModel;

/**
 * Api ANR Scales Type Controller
 *
 * Class ApiAnrScalesTypesController
 * @package MonarcFO\Controller
 */
class ApiAnrScalesTypesController extends ApiAnrAbstractController
{
    protected $name = 'types';
    protected $dependencies = ['scale'];
    
    public function create($data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;
        $rightCommLanguage ="label".$data['langue'];
			$data[$rightCommLanguage] = $data['Label'];
        
			if(isset($data['langue'])) 
			{
				unset($data['Label']);
				unset($data['langue']);
			}       

        $id = $this->getService()->create($data);

        return new JsonModel([
            'status' => 'ok',
            'id' => $id,
        ]);
    }
}