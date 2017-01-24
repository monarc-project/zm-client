<?php

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api ANR Objects Export Controller
 *
 * Class ApiAnrObjectsExportController
 * @package MonarcFO\Controller
 */
class ApiAnrObjectsExportController extends ApiAnrAbstractController
{
    /**
     * Create
     *
     * @param mixed $data
     * @return JsonModel
     * @throws \Exception
     */
    public function create($data)
    {
        if (empty($data['id'])) {
            throw new \Exception('Object to export is required', 412);
        }
        $entity = $this->getService()->getEntity($data['id']);

        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Exception('Anr id missing', 412);
        }

        if ($entity['anr']->get('id') != $anrId) {
            throw new \Exception('Anr ids differents', 412);
        }

        $output = $this->getService()->export($data);

        $response = $this->getResponse();
        $response->setContent($output);

        $headers = $response->getHeaders();
        $headers->clearHeaders()
            ->addHeaderLine('Content-Type', 'text/plain; charset=utf-8')
            ->addHeaderLine('Content-Disposition', 'attachment; filename="' . (empty($data['filename']) ? $data['id'] : $data['filename']) . '.bin"');

        return $this->response;
    }

    public function get($id)
    {
        return $this->methodNotAllowed();
    }

    public function getList()
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

    public function delete($id)
    {
        return $this->methodNotAllowed();
    }
}
