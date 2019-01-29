<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractController;
use Zend\View\Model\JsonModel;

/**
 * Api Anr Import Abstract Controller
 *
 * Class ApiAnrImportAbstractController
 * @package MonarcFO\Controller
 */
abstract class ApiAnrImportAbstractController extends AbstractController
{
    /**
     * @inheritdoc
     */
    public function create($data)
    {
        $service = $this->getService();

        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }

        $files = $this->params()->fromFiles('file');
        if (empty($files)) {
            throw new \MonarcCore\Exception\Exception('File missing', 412);
        }
        $data['file'] = $files;

        list($ids, $errors) = $service->importFromFile($anrId, $data);

        return new JsonModel([
            'status' => 'ok',
            'id' => $ids,
            'errors' => $errors,
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

