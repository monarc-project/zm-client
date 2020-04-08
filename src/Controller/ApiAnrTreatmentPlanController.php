<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Service\AnrRecommandationRiskService;
use Laminas\View\Model\JsonModel;

/**
 * Api Anr Treatment Plan
 *
 * Class ApiAnrTreatmentPlanController
 * @package Monarc\FrontOffice\Controller
 */
class ApiAnrTreatmentPlanController extends ApiAnrAbstractController
{
    /**
     * @inheritdoc
     */
    public function getList()
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }

        /** @var AnrRecommandationRiskService $service */
        $service = $this->getService();
        $entities = $service->getTreatmentPlan($anrId);

        return new JsonModel([
            'recommandations-risks' => $entities
        ]);
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }

        /** @var AnrRecommandationRiskService $service */
        $service = $this->getService();
        $entities = $service->getTreatmentPlan($anrId, $id);
        if (empty($entities)) {
            throw new Exception('Entity does not exist', 412);
        }

        return new JsonModel($entities[0]);
    }

    /**
     * @inheritdoc
     */
    public function deleteList($data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }

        /** @var AnrRecommandationRiskService $service */
        $service = $this->getService();
        $service->resetRecommendationsPositionsToDefault($anrId);

        return new JsonModel(['status' => 'ok']);
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
    public function patch($token, $data)
    {
        return $this->methodNotAllowed();
    }
}
