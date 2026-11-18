<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Laminas\Diactoros\Response;
use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\InputFormatter\DeliveryModel\GetDeliveryModelsInputFormatter;
use Monarc\Core\Service\DeliveriesModelsService;
use Monarc\Core\Validator\InputValidator\DeliveryModel\DeliveryModelDataInputValidator;

class ApiDeliveriesModelsController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private GetDeliveryModelsInputFormatter $getDeliveryModelsInputFormatter,
        private DeliveryModelDataInputValidator $deliveryModelDataInputValidator,
        private DeliveriesModelsService $deliveriesModelsService
    ) {
    }

    public function getList()
    {
        $formattedParams = $this->getFormattedInputParams($this->getDeliveryModelsInputFormatter);

        return $this->getPreparedJsonResponse([
            'count' => $this->deliveriesModelsService->getCount($formattedParams),
            'deliveriesmodels' => $this->deliveriesModelsService->getList($formattedParams),
        ]);
    }

    public function get($id)
    {
        $download = $this->deliveriesModelsService->getDownload((int)$id, (int)$this->params()->fromQuery('lang', 1));
        $stream = fopen('php://memory', 'rb+');
        fwrite($stream, $download['contents']);
        rewind($stream);

        return new Response($stream, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Length' => strlen($download['contents']),
            'Content-Disposition' => 'attachment; filename="' . $download['filename'] . '"',
        ]);
    }

    public function create($data)
    {
        $this->validatePostParams(
            $this->deliveryModelDataInputValidator,
            array_replace($data, ['file' => $this->getRequest()->getFiles()->toArray()['file'] ?? []])
        );

        $validData = $this->deliveryModelDataInputValidator->getValidData();

        return $this->getSuccessfulJsonResponse([
            'id' => $this->deliveriesModelsService->create($validData)->getId(),
        ]);
    }

    public function update($id, $data)
    {
        $this->validatePostParams(
            $this->deliveryModelDataInputValidator,
            array_replace($data, ['file' => $this->getRequest()->getFiles()->toArray()['file'] ?? []]),
            partial: true
        );
        $this->deliveriesModelsService->update((int)$id, $this->deliveryModelDataInputValidator->getValidData());

        return $this->getSuccessfulJsonResponse();
    }

    public function patch($id, $data)
    {
        $this->validatePostParams(
            $this->deliveryModelDataInputValidator,
            array_replace($data, ['file' => $this->getRequest()->getFiles()->toArray()['file'] ?? []]),
            partial: true
        );
        $this->deliveriesModelsService->update((int)$id, $this->deliveryModelDataInputValidator->getValidData());

        return $this->getSuccessfulJsonResponse();
    }

    public function delete($id)
    {
        $this->deliveriesModelsService->delete((int)$id);

        return $this->getSuccessfulJsonResponse();
    }
}
