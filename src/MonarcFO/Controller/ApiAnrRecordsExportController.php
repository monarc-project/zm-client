<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api ANR Records Export Controller
 *
 * Class ApiAnrRecordsExportController
 * @package MonarcFO\Controller
 */
class ApiAnrRecordsExportController extends ApiAnrAbstractController
{
    /**
     * @inheritdoc
     */
    public function create($data)
    {
        if (empty($data['id'])) {
            throw new \MonarcCore\Exception\Exception('Record to export is required', 412);
        }
        $entity = $this->getService()->getEntity($data['id']);

        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }

        if ($entity['anr']->get('id') != $anrId) {
            throw new \MonarcCore\Exception\Exception('Anr ids differents', 412);
        }

        $output = $this->getService()->export($data);

        if (empty($data['password'])) {
          $contentType = 'application/json; charset=utf-8';
          $extension = '.json';
        } else {
          $contentType = 'text/plain; charset=utf-8';
          $extension = '.bin';
        }

        $this->getResponse()
             ->getHeaders()
             ->clearHeaders()
             ->addHeaderLine('Content-Type', $contentType)
             ->addHeaderLine('Content-Disposition', 'attachment; filename="' .
                              (empty($data['filename']) ? $data['id'] : $data['filename']) . $extension . '"');

        $this->getResponse()
             ->setContent($output);

        return $this->getResponse();
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
    public function getList()
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

    /**
     * @inheritdoc
     */
    public function delete($id)
    {
        return $this->methodNotAllowed();
    }
}
