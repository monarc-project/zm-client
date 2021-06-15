<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Monarc\FrontOffice\Service\OperationalRiskScaleCommentService;

class ApiOperationalRisksScalesCommentsController extends AbstractRestfulController
{
    private OperationalRiskScaleCommentService $operationalRiskScaleCommentService;

    public function __construct(OperationalRiskScaleCommentService $operationalRiskScaleCommentService)
    {
        $this->operationalRiskScaleCommentService = $operationalRiskScaleCommentService;
    }


    public function update($id, $data)
    {
      file_put_contents('php://stderr', print_r('$data', TRUE).PHP_EOL);
      file_put_contents('php://stderr', print_r($data, TRUE).PHP_EOL);
      file_put_contents('php://stderr', print_r('$id', TRUE).PHP_EOL);
      file_put_contents('php://stderr', print_r($id, TRUE).PHP_EOL);


        return new JsonModel(['status' => 'ok']);
    }

}
