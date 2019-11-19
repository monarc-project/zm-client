<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;
use Zend\View\Model\JsonModel;

/**
 * Api ANR Scales Type Controller
 *
 * Class ApiAnrScalesTypesController
 * @package Monarc\FrontOffice\Controller
 */
class ApiAnrScalesTypesController extends ApiAnrAbstractController
{
    protected $name = 'types';
    protected $dependencies = ['scale'];

    public function create($data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Monarc\Core\Exception\Exception('Anr id missing', 412);
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
