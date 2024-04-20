<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Validator\InputValidator\Asset\PostAssetDataInputValidator;
use Monarc\FrontOffice\InputFormatter\Asset\GetAssetsInputFormatter;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service\AnrAssetService;

class ApiAnrAssetsController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private GetAssetsInputFormatter $getAssetsInputFormatter,
        private PostAssetDataInputValidator $postAssetDataInputValidator,
        private AnrAssetService $anrAssetService
    ) {
    }

    public function getList()
    {
        $formattedParams = $this->getFormattedInputParams($this->getAssetsInputFormatter);

        return $this->getPreparedJsonResponse([
            'count' => $this->anrAssetService->getCount($formattedParams),
            'assets' => $this->anrAssetService->getList($formattedParams),
        ]);
    }

    /**
     * @param string $id
     */
    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getPreparedJsonResponse($this->anrAssetService->getAssetData($anr, $id));
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
            $this->postAssetDataInputValidator->setIncludeFilter(['anr' => $anr]),
            $data,
            $isBatchData
        );

        if ($isBatchData) {
            return $this->getSuccessfulJsonResponse([
                'id' => $this->anrAssetService->createList(
                    $anr,
                    $this->postAssetDataInputValidator->getValidDataSets()
                ),
            ]);
        }

        return $this->getSuccessfulJsonResponse([
            'id' => $this->anrAssetService->create(
                $anr,
                $this->postAssetDataInputValidator->getValidData()
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
            $this->postAssetDataInputValidator->setIncludeFilter(['anr' => $anr])->setExcludeFilter(['uuid' => $id]),
            $data
        );

        $this->anrAssetService->update($anr, $id, $this->postAssetDataInputValidator->getValidData());

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

        $this->anrAssetService->patch($anr, $id, $data);

        return $this->getSuccessfulJsonResponse();
    }

    /**
     * @param string $id
     */
    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrAssetService->delete($anr, $id);

        return $this->getSuccessfulJsonResponse();
    }

    /**
     * @param array $data
     */
    public function deleteList($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrAssetService->deleteList($anr, $data);

        return $this->getSuccessfulJsonResponse();
    }
}
