<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Laminas\Diactoros\Response;
use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service\DeliverableGenerationService;
use function strlen;

class ApiAnrDeliverableController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(private DeliverableGenerationService $deliverableGenerationService)
    {
    }

    public function create($data)
    {
        $typeDoc = (int)$data['typedoc'];
        if (empty($typeDoc)) {
            throw new Exception('Document type missing', 412);
        }

        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $filePath = $this->deliverableGenerationService->generateDeliverableWithValues($anr, $typeDoc, $data);
        if (!file_exists($filePath)) {
            throw new Exception('Generated file is not found: ' . $filePath);
        }

        $reportContent = file_get_contents($filePath);
        $stream = fopen('php://memory', 'rb+');
        fwrite($stream, $reportContent);
        rewind($stream);
        unlink($filePath);

        return new Response($stream, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Length' => strlen($reportContent),
            'Content-Disposition' => 'attachment; filename="deliverable.docx"',
        ]);
    }

    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getPreparedJsonResponse([
            'delivery' => $this->deliverableGenerationService->getLastDelivery($anr, (int)$id),
        ]);
    }
}
