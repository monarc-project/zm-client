<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Controller;

use MonarcFO\Service\SnapshotService;
use Zend\View\Model\JsonModel;

/**
 * Api Snapshot Restore Controller
 *
 * Class ApiSnapshotRestoreController
 * @package MonarcFO\Controller
 */
class ApiSnapshotRestoreController extends ApiAnrAbstractController
{
    protected $name = 'snapshots';

    /**
     * Create
     *
     * @param mixed $data
     * @return JsonModel
     * @throws \Exception
     */
    public function create($data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Exception('Anr id missing', 412);
        }

        $id = (int)$this->params()->fromRoute('id');
        if (empty($id)) {
            throw new \Exception('Snapshot id missing', 412);
        }

        /** @var SnapshotService $service */
        $service = $this->getService();
        $newId = $service->restore($anrId, $id);

        return new JsonModel([
            'status' => 'ok',
            'id' => $newId,
        ]);
    }

    public function getList()
    {
        return $this->methodNotAllowed();
    }

    public function get($id)
    {
        return $this->methodNotAllowed();
    }

    public function delete($id)
    {
        return $this->methodNotAllowed();
    }

    public function deleteList($data)
    {
        return $this->methodNotAllowed();
    }

    public function update($id, $data)
    {
        return $this->methodNotAllowed();
    }

    public function patch($id, $data)
    {
        return $this->methodNotAllowed();
    }
}