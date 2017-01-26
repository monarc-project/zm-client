<?php
namespace MonarcFO\Controller;

use MonarcFO\Service\AnrRecommandationRiskService;
use Zend\View\Model\JsonModel;

/**
 * Api Anr Recommandations Risks Validate
 *
 * Class ApiAnrRecommandationsRisksValidateController
 * @package MonarcFO\Controller
 */
class ApiAnrRecommandationsRisksValidateController extends ApiAnrAbstractController
{
    /**
     * Patch
     *
     * @param mixed $id
     * @param mixed $data
     * @return JsonModel
     * @throws \Exception
     */
    public function patch($id, $data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        /** @var AnrRecommandationRiskService $service */
        $service = $this->getService();
        $service->validateFor($id, $data);

        return new JsonModel(['status' => 'ok']);
    }

    public function create($data)
    {
        $this->methodNotAllowed();
    }

    public function getList()
    {
        $this->methodNotAllowed();
    }

    public function update($id, $data)
    {
        return $this->methodNotAllowed();
    }
}