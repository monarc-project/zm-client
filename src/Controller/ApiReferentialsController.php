<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api Referentials Controller
 *
 * Class ApiReferentialsController
 * @package Monarc\FrontOffice\Controller
 */
class ApiReferentialsController extends ApiAnrImportAbstractController
{
    protected $name = 'referentials';

    /**
     * @inheritdoc
     */
    public function getList()
    {
        $filter = $this->params()->fromQuery('filter');
        $order = $this->params()->fromQuery('order');
        $referentials = $this->getService()->getCommonReferentials($filter,$order);
        return new JsonModel([
            'count' => count($referentials),
            $this->name => $referentials,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        return $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        return $this->methodNotAllowed();
    }
}
