<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\FrontOffice\Service\AnrRecommandationRiskService;
use Zend\View\Model\JsonModel;

/**
 * Api Anr Recommandations Risks Validate
 *
 * Class ApiAnrRecommandationsRisksValidateController
 * @package Monarc\FrontOffice\Controller
 */
class ApiAnrRecommandationsRisksValidateController extends ApiAnrAbstractController
{
    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Monarc\Core\Exception\Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        /** @var AnrRecommandationRiskService $service */
        $service = $this->getService();
        $service->validateFor($id, $data);

        return new JsonModel(['status' => 'ok']);
    }

    /**
     * @inheritdoc
     */
    public function create($data)
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
    public function update($id, $data)
    {
        return $this->methodNotAllowed();
    }
}
