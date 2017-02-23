<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api Anr Assets Import common Controller
 *
 * Class ApiAnrAssetsImportCommonController
 * @package MonarcFO\Controller
 */
class ApiAnrAssetsImportCommonController extends ApiAnrImportAbstractController
{
    protected $name = "assets";

    /**
     * @inheritdoc
     */
    public function getList()
    {
        $service = $this->getService();

        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }

        $entities = $service->getListAssets($anrId);

        return new JsonModel([
            'count' => count($entities),
            $this->name => $entities
        ]);
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        $service = $this->getService();

        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }

        $entitie = $service->getAsset($anrId, $id);

        return new JsonModel($entitie);
    }

    /**
     * @inheritdoc
     */
    public function create($data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }
        if (empty($data['asset'])) {
            throw new \MonarcCore\Exception\Exception('Asset id missing', 412);
        }
        $id = $this->getService()->importAsset($anrId, $data['asset']);

        return new JsonModel([
            'status' => 'ok',
            'id' => $id,
        ]);
    }
}