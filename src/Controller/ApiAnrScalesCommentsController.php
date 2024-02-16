<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\InputFormatter\ScaleComment\GetScaleCommentsInputFormatter;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Service\AnrScaleCommentService;

class ApiAnrScalesCommentsController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private AnrScaleCommentService $anrScaleCommentService,
        private GetScaleCommentsInputFormatter $getScaleCommentsInputFormatter
    ) {
    }

    public function getList()
    {
        $formattedParams = $this->getFormattedInputParams($this->getScaleCommentsInputFormatter);
        $formattedParams->setFilterValueFor('scale', (int)$this->params()->fromRoute('scaleId'));

        $comments = $this->anrScaleCommentService->getList($formattedParams);

        return $this->getPreparedJsonResponse([
            'count' => \count($comments),
            'comments' => $comments,
        ]);
    }

    /**
     * @param array $data
     */
    public function create($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $scaleComment = $this->anrScaleCommentService->create($anr, $data);

        return $this->getSuccessfulJsonResponse(['id' => $scaleComment->getId()]);
    }

    /**
     * @param array $data
     */
    public function update($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $scaleComment = $this->anrScaleCommentService->update($anr, (int)$id, $data);

        return $this->getSuccessfulJsonResponse(['id' => $scaleComment->getId()]);
    }
}
