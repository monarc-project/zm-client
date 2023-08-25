<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Validator\InputValidator\Asset\PostAssetDataInputValidator;
use Monarc\FrontOffice\InputFormatter\Asset\GetAssetsInputFormatter;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Service\AnrAssetService;

class ApiAnrAssetsController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    private GetAssetsInputFormatter $getAssetsInputFormatter;

    private PostAssetDataInputValidator $postAssetDataInputValidator;

    private AnrAssetService $anrAssetService;

    public function __construct(
        GetAssetsInputFormatter $getAssetsInputFormatter,
        PostAssetDataInputValidator $postAssetDataInputValidator,
        AnrAssetService $anrAssetService
    ) {
        $this->getAssetsInputFormatter = $getAssetsInputFormatter;
        $this->postAssetDataInputValidator = $postAssetDataInputValidator;
        $this->anrAssetService = $anrAssetService;
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
        $this->validatePostParams($this->postAssetDataInputValidator->setAnr($anr), $data, $isBatchData);

        $assetsUuids = [];
        $validatedData = $isBatchData
            ? $this->postAssetDataInputValidator->getValidDataSets()
            : [$this->postAssetDataInputValidator->getValidData()];
        $setsNum = \count($validatedData) - 1;
        foreach ($validatedData as $setNum => $validatedDataRow) {
            $assetsUuids[] = $this->anrAssetService->create($anr, $validatedDataRow, $setNum === $setsNum)->getUuid();
        }

        return $this->getSuccessfulJsonResponse([
            'id' => \count($assetsUuids) === 1 && !$isBatchData ? current($assetsUuids) : $assetsUuids,
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
            $this->postAssetDataInputValidator->setExcludeFilter(['uuid' => $id])->setAnr($anr),
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
