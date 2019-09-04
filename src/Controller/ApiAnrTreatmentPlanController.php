<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\FrontOffice\Service\AnrRecommandationRiskService;
use Zend\View\Model\JsonModel;

/**
 * Api Anr Treatment Plan
 *
 * Class ApiAnrTreatmentPlanController
 * @package Monarc\FrontOffice\Controller
 */
class ApiAnrTreatmentPlanController extends ApiAnrAbstractController
{
    protected $name = 'recommandations-risks';

    /**
     * @inheritdoc
     */
    public function getList()
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Monarc\Core\Exception\Exception('Anr id missing', 412);
        }

        /** @var AnrRecommandationRiskService $service */
        $service = $this->getService();
        $entities = $service->getTreatmentPlan($anrId);
        if (count($this->dependencies)) {
            foreach ($entities as $key => $entity) {
                $this->formatDependencies($entities[$key], $this->dependencies);
            }
        }

        return new JsonModel([
            $this->name => $entities
        ]);
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Monarc\Core\Exception\Exception('Anr id missing', 412);
        }

        /** @var AnrRecommandationRiskService $service */
        $service = $this->getService();
        $entities = $service->getTreatmentPlan($anrId, $id);
        if (count($this->dependencies)) {
            foreach ($entities as $key => $entity) {
                $this->formatDependencies($entities[$key], $this->dependencies);
            }
        }

        if (count($entities)) {
            return new JsonModel($entities[0]);
        } else {
            throw new \Monarc\Core\Exception\Exception('ENtity not exist', 412);
        }


    }

    /**
     * @inheritdoc
     */
    public function deleteList($data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Monarc\Core\Exception\Exception('Anr id missing', 412);
        }

        /** @var AnrRecommandationRiskService $service */
        $service = $this->getService();
        $service->initPosition($anrId);

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
