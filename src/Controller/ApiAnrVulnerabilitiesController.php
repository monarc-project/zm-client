<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Validator\InputValidator\Vulnerability\PostVulnerabilityDataInputValidator;
use Monarc\FrontOffice\InputFormatter\Vulnerability\GetVulnerabilitiesInputFormatter;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service\AnrVulnerabilityService;

class ApiAnrVulnerabilitiesController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private GetVulnerabilitiesInputFormatter $getVulnerabilitiesInputFormatter,
        private PostVulnerabilityDataInputValidator $postVulnerabilityDataInputValidator,
        private AnrVulnerabilityService $anrVulnerabilityService
    ) {
    }

    public function getList()
    {
        $formattedInput = $this->getFormattedInputParams($this->getVulnerabilitiesInputFormatter);

        return $this->getPreparedJsonResponse([
            'count' => $this->anrVulnerabilityService->getCount($formattedInput),
            'vulnerabilities' => $this->anrVulnerabilityService->getList($formattedInput),
        ]);
    }

    /**
     * @param string $id
     */
    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getPreparedJsonResponse($this->anrVulnerabilityService->getVulnerabilityData($anr, $id));
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
            $this->postVulnerabilityDataInputValidator->setIncludeFilter(['anr' => $anr]),
            $data,
            $isBatchData
        );

        if ($isBatchData) {
            return $this->getSuccessfulJsonResponse([
                'id' => $this->anrVulnerabilityService->createList(
                    $anr,
                    $this->postVulnerabilityDataInputValidator->getValidDataSets()
                ),
            ]);
        }

        return $this->getSuccessfulJsonResponse([
            'id' => $this->anrVulnerabilityService->create(
                $anr,
                $this->postVulnerabilityDataInputValidator->getValidData()
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
            $this->postVulnerabilityDataInputValidator
                ->setIncludeFilter(['anr' => $anr])
                ->setExcludeFilter(['uuid' => $id]),
            $data
        );

        $this->anrVulnerabilityService->update($anr, $id, $this->postVulnerabilityDataInputValidator->getValidData());

        return $this->getPreparedJsonResponse(['status' => 'ok']);
    }

    public function patch($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrVulnerabilityService->patch($anr, $id, $data);

        return $this->getPreparedJsonResponse(['status' => 'ok']);
    }

    /**
     * @param string $id
     */
    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrVulnerabilityService->delete($anr, $id);

        return $this->getPreparedJsonResponse(['status' => 'ok']);
    }

    public function deleteList($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrVulnerabilityService->deleteList($anr, $data);

        return $this->getPreparedJsonResponse(['status' => 'ok']);
    }
}
