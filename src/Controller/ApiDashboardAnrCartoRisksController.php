<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Laminas\View\Model\JsonModel;

/**
 * Api Dashboard ANR Cartography Risks Real & Targeted Controller
 *
 * Class ApiDashboardAnrCartoRisksController
 * @package Monarc\FrontOffice\Controller
 */
class ApiDashboardAnrCartoRisksController extends ApiAnrAbstractController
{
    protected $name = 'carto';
    protected $dependencies = [];

    /**
     * @inheritdoc
     */
    public function getList()
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Monarc\Core\Exception\Exception('Anr id missing', 412);
        }
        $type = $this->params()->fromRoute('type', 'all'); // real / targeted / all
        switch ($type) {
            case 'real':
                return new JsonModel([
                    'status' => 'ok',
                    $this->name => [
                        'real' => $this->getService()->getCartoReal($anrId),
                    ]
                ]);
                break;
            case 'targeted':
                return new JsonModel([
                    'status' => 'ok',
                    $this->name => [
                        'targeted' => $this->getService()->getCartoTargeted($anrId),
                    ]
                ]);
                break;
            default:
            case 'all':
                return new JsonModel([
                    'status' => 'ok',
                    $this->name => [
                        'real' => $this->getService()->getCartoReal($anrId),
                        'targeted' => $this->getService()->getCartoTargeted($anrId),
                    ]
                ]);
                break;
        }
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function create($data)
    {
        $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function delete($id)
    {
        $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function deleteList($data)
    {
        $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        $this->methodNotAllowed();
    }
}
