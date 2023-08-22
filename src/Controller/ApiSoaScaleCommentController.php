<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Laminas\View\Model\JsonModel;
use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Service\SoaScaleCommentService;

class ApiSoaScaleCommentController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    private SoaScaleCommentService $soaScaleCommentService;

    public function __construct(SoaScaleCommentService $soaScaleCommentService)
    {
        $this->soaScaleCommentService = $soaScaleCommentService;
    }

    public function getList()
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return new JsonModel([
            'data' => $this->soaScaleCommentService->getSoaScaleCommentsData($anr),
        ]);
    }

    /**
     * @param array $data
     */
    public function update($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->soaScaleCommentService->update($anr, (int)$id, $data);

        return $this->getSuccessfulJsonResponse();
    }

    public function patchList($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->soaScaleCommentService->createOrHideSoaScaleComments($anr, $data);

        return new JsonModel([
            'data' => $this->soaScaleCommentService->getSoaScaleCommentsData($anr),
        ]);
    }
}
