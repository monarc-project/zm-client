<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api ANR Objects Import Controller
 *
 * Class ApiAnrObjectsImportController
 * @package MonarcFO\Controller
 */
class ApiAnrObjectsImportController extends ApiAnrImportAbstractController
{
    protected $name = 'objects';

    /**
     * Get List
     *
     * @return JsonModel
     * @throws \Exception
     */
    public function getList()
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Exception('Anr id missing', 412);
        }
        $objects = $this->getService()->getCommonObjects($anrId);
        return new JsonModel([
            'count' => count($objects),
            $this->name => $objects,
        ]);
    }

    /**
     * Patch
     *
     * @param mixed $id
     * @param mixed $data
     * @return JsonModel
     * @throws \Exception
     */
    public function patch($id, $data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;
        $newid = $this->getService()->importFromCommon($id, $data);

        return new JsonModel([
            'status' => 'ok',
            'id' => $newid
        ]);
    }

    /**
     * Get
     *
     * @param mixed $id
     * @return JsonModel
     * @throws \Exception
     */
    public function get($id)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Exception('Anr id missing', 412);
        }

        $object = $this->getService()->getCommonEntity($anrId, $id);

        $this->formatDependencies($object, ['asset', 'category', 'rolfTag']);
        unset($object['anrs']);

        return new JsonModel($object);
    }
}