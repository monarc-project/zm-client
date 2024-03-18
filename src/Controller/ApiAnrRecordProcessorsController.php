<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Service\AnrRecordProcessorService;

class ApiAnrRecordProcessorsController extends ApiAnrAbstractController
{
    protected $name = 'record-processors';
    protected $dependencies = ['anr', 'representative', 'dpo'];

    public function __construct(AnrRecordProcessorService $anrRecordProcessorService)
    {
        parent::__construct($anrRecordProcessorService);
    }

    public function get($id)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        $entity = $this->getService()->getEntity(['anr' => $anrId, 'id' => $id]);

        $this->formatDependencies($entity, $this->dependencies);

        return $this->getPreparedJsonResponse($entity);
    }

    public function update($id, $data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;
        $this->getService()->updateProcessor($id, $data);

        return $this->getSuccessfulJsonResponse();
    }

    public function create($data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        $id = $this->getService()->create($data);

        return $this->getPreparedJsonResponse([
            'id' => $id,
        ]);
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
