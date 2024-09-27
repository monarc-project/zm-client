<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\InputFormatter\RolfTag\GetRolfTagsInputFormatter;
use Monarc\Core\Validator\InputValidator\RolfTag\PostRolfTagDataInputValidator;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service\AnrRolfTagService;

class ApiAnrRolfTagsController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private AnrRolfTagService $anrRolfTagService,
        private GetRolfTagsInputFormatter $getRolfTagsInputFormatter,
        private PostRolfTagDataInputValidator $postRolfTagDataInputValidator
    ) {
    }

    public function getList()
    {
        $formattedParams = $this->getFormattedInputParams($this->getRolfTagsInputFormatter);

        return $this->getPreparedJsonResponse([
            'count' => $this->anrRolfTagService->getCount($formattedParams),
            'tags' => $this->anrRolfTagService->getList($formattedParams),
        ]);
    }

    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getPreparedJsonResponse($this->anrRolfTagService->getRolfTagData($anr, (int)$id));
    }

    public function create($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $isBatchData = $this->isBatchData($data);
        $this->validatePostParams($this->postRolfTagDataInputValidator, $data, $isBatchData);

        if ($this->isBatchData($data)) {
            return $this->getSuccessfulJsonResponse([
                'id' => $this->anrRolfTagService
                    ->createList($anr, $this->postRolfTagDataInputValidator->getValidDataSets()),
            ]);
        }

        return $this->getSuccessfulJsonResponse([
            'id' => $this->anrRolfTagService->create(
                $anr,
                $this->postRolfTagDataInputValidator->getValidData()
            )->getId(),
        ]);
    }

    /**
     * @param array $data
     */
    public function update($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->validatePostParams(
            $this->postRolfTagDataInputValidator
                ->setIncludeFilter(['anr' => $anr])
                ->setExcludeFilter(['id' => (int)$id]),
            $data
        );

        $this->anrRolfTagService->update($anr, (int)$id, $this->postRolfTagDataInputValidator->getValidData());

        return $this->getSuccessfulJsonResponse();
    }

    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->anrRolfTagService->delete($anr, (int)$id);

        return $this->getSuccessfulJsonResponse();
    }

    public function deleteList($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->anrRolfTagService->deleteList($anr, $data);

        return $this->getSuccessfulJsonResponse();
    }
}
