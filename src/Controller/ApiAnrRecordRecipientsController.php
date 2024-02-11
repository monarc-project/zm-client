<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Laminas\View\Model\JsonModel;
use Monarc\FrontOffice\Service\AnrRecordRecipientService;

class ApiAnrRecordRecipientsController extends ApiAnrAbstractController
{
    protected $name = 'record-recipients';
    protected $dependencies = ['anr'];

    public function __construct(AnrRecordRecipientService $anrRecordRecipientService)
    {
        parent::__construct($anrRecordRecipientService);
    }

    public function create($data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Monarc\Core\Exception\Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;
        $id = $this->getService()->create($data);

        return new JsonModel([
            'status' => 'ok',
            'id' => $id,
        ]);
    }

    public function update($id, $data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Monarc\Core\Exception\Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        $service = $this->getService()->update($id, $data);

        return new JsonModel(['status' => 'ok']);
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
