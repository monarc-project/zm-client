<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Laminas\View\Model\JsonModel;

/**
 * Api Instance Consequences Controller
 *
 * Class ApiAnrInstancesConsequencesController
 * @package Monarc\FrontOffice\Controller
 */
class ApiAnrInstancesConsequencesController extends ApiAnrAbstractController
{
    protected $name = 'instances-consequences';

    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        $data['anr'] = (int)$this->params()->fromRoute('anrid');

        $this->getService()->patchConsequence($id, $data);

        return new JsonModel(['status' => 'ok']);
    }
}
