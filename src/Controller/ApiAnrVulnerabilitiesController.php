<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\InputFormatter\Vulnerability\GetVulnerabilitiesInputFormatter;
use Monarc\Core\Validator\InputValidator\Vulnerability\PostVulnerabilityDataInputValidator;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Service\AnrVulnerabilityService;

class ApiAnrVulnerabilitiesController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    private GetVulnerabilitiesInputFormatter $getVulnerabilitiesInputFormatter;

    private AnrVulnerabilityService $anrVulnerabilityService;

    private PostVulnerabilityDataInputValidator $postVulnerabilityDataInputValidator;

    public function __construct(
        GetVulnerabilitiesInputFormatter $getVulnerabilitiesInputFormatter,
        PostVulnerabilityDataInputValidator $postVulnerabilityDataInputValidator,
        AnrVulnerabilityService $anrVulnerabilityService
    ) {
        $this->getVulnerabilitiesInputFormatter = $getVulnerabilitiesInputFormatter;
        $this->anrVulnerabilityService = $anrVulnerabilityService;
        $this->postVulnerabilityDataInputValidator = $postVulnerabilityDataInputValidator;
    }

    public function getList()
    {
        $formattedInput = $this->getFormattedInputParams($this->getVulnerabilitiesInputFormatter);

        return $this->getPreparedJsonResponse([
            'count' => $this->anrVulnerabilityService->getCount($formattedInput),
            'vulnerabilities' => $this->anrVulnerabilityService->getList($formattedInput),
        ]);
    }

    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getPreparedJsonResponse($this->anrVulnerabilityService->getVulnerabilityData($anr, $id));
    }

    public function create($data)
    {
        $isBatchData = $this->isBatchData($data);
        $this->validatePostParams($this->postVulnerabilityDataInputValidator, $data, $isBatchData);

        $vulnerabilitiesUuids = [];
        $validatedData = $isBatchData
            ? $this->postVulnerabilityDataInputValidator->getValidDataSets()
            : [$this->postVulnerabilityDataInputValidator->getValidData()];
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        foreach ($validatedData as $validatedDataRow) {
            $vulnerabilitiesUuids[] = $this->anrVulnerabilityService->create($anr, $validatedDataRow)->getUuid();
        }

        return $this->getPreparedJsonResponse([
            'status' => 'ok',
            'id' => implode(', ', $vulnerabilitiesUuids),
        ]);
    }

    public function update($id, $data)
    {
        $this->validatePostParams($this->postVulnerabilityDataInputValidator, $data);

        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
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
