<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
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
     * @inheritdoc
     */
    public function create($data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }

        $id = (int)$this->params()->fromRoute('id');
        if (empty($id)) {
            throw new \MonarcCore\Exception\Exception('Snapshot id missing', 412);
        }

        /** @var SnapshotService $service */
        $service = $this->getService();
        $newId = $service->restore($anrId, $id);

        return new JsonModel([
            'status' => 'ok',
            'id' => $newId,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getList()
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

    /**
     * @inheritdoc
     */
    public function delete($id)
    {
        return $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function deleteList($data)
    {
        return $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        return $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        return $this->methodNotAllowed();
    }
}