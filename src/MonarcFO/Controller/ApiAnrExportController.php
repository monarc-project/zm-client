<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Controller;

/**
 * Api Anr Export Controller
 *
 * Class ApiAnrExportController
 * @package MonarcFO\Controller
 */
class ApiAnrExportController extends ApiAnrAbstractController
{
    /**
     * @inheritdoc
     */
    public function create($data)
    {
        if (empty($data['id'])) {
            throw new \MonarcCore\Exception\Exception('Anr to export is required', 412);
        }

        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }

        $output = $this->getService()->exportAnr($data);

        $response = $this->getResponse();
        $response->setContent($output);

        $headers = $response->getHeaders();
        $headers->clearHeaders()
            ->addHeaderLine('Content-Type', 'text/plain; charset=utf-8')
            ->addHeaderLine('Content-Disposition', 'attachment; filename="' . (empty($data['filename']) ? $data['id'] : $data['filename']) . '.bin"');

        return $this->response;
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
    public function getList()
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