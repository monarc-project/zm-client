<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\InputFormatter\Threat\GetThreatsInputFormatter;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service\AnrThreatService;
use Monarc\FrontOffice\Validator\InputValidator\Threat\PostThreatDataInputValidator;

class ApiAnrThreatsController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private GetThreatsInputFormatter $getThreatsInputFormatter,
        private PostThreatDataInputValidator $postThreatDataInputValidator,
        private AnrThreatService $anrThreatService
    ) {
    }

    public function getList()
    {
        $formattedParams = $this->getFormattedInputParams($this->getThreatsInputFormatter);

        return $this->getPreparedJsonResponse([
            'count' => $this->anrThreatService->getCount($formattedParams),
            'threats' => $this->anrThreatService->getList($formattedParams),
        ]);
    }

    /**
     * @param string $id
     */
    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getPreparedJsonResponse($this->anrThreatService->getThreatData($anr, $id));
    }

    /**
     * @param array $data
     */
    public function create($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $isBatchData = $this->isBatchData($data);
        $this->validatePostParams(
            $this->postThreatDataInputValidator->setIncludeFilter(['anr' => $anr]),
            $data,
            $isBatchData
        );

        if ($isBatchData) {
            return $this->getSuccessfulJsonResponse([
                'id' => $this->anrThreatService->createList(
                    $anr,
                    $this->postThreatDataInputValidator->getValidDataSets()
                ),
            ]);
        }

        return $this->getSuccessfulJsonResponse([
            'id' => $this->anrThreatService->create(
                $anr,
                $this->postThreatDataInputValidator->getValidData()
            )->getUuid(),
        ]);
    }

    /**
     * @param string $id
     * @param array $data
     */
    public function update($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->validatePostParams(
            $this->postThreatDataInputValidator->setIncludeFilter(['anr' => $anr])->setExcludeFilter(['uuid' => $id]),
            $data
        );

        $this->anrThreatService->update($anr, $id, $this->postThreatDataInputValidator->getValidData());

        return $this->getSuccessfulJsonResponse();
    }

    /**
     * @param string $id
     * @param array $data
     */
    public function patch($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrThreatService->patch($anr, $id, $data);

        return $this->getSuccessfulJsonResponse();
    }

    /**
     * @param string $id
     */
    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrThreatService->delete($anr, $id);

        return $this->getSuccessfulJsonResponse();
    }

    /**
     * @param array $data
     */
    public function deleteList($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrThreatService->deleteList($anr, $data);

        return $this->getSuccessfulJsonResponse();
    }
}
