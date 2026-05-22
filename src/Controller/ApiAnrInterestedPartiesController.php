<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\InterestedParty;
use Monarc\FrontOffice\Service\InterestedPartyService;

class ApiAnrInterestedPartiesController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(private InterestedPartyService $interestedPartyService)
    {
    }

    public function getList()
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $interestedParties = $this->interestedPartyService->getList($anr);

        return $this->getPreparedJsonResponse([
            'count' => count($interestedParties),
            'interestedParties' => array_map([$this, 'prepareInterestedPartyData'], $interestedParties),
        ]);
    }

    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getPreparedJsonResponse(
            $this->prepareInterestedPartyData($this->interestedPartyService->get($anr, (int)$id))
        );
    }

    public function create($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getSuccessfulJsonResponse($this->prepareInterestedPartyData(
            $this->interestedPartyService->create($anr, (array)$data)
        ));
    }

    public function update($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getSuccessfulJsonResponse($this->prepareInterestedPartyData(
            $this->interestedPartyService->update($anr, (int)$id, (array)$data)
        ));
    }

    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->interestedPartyService->delete($anr, (int)$id);

        return $this->getSuccessfulJsonResponse();
    }

    private function prepareInterestedPartyData(InterestedParty $interestedParty): array
    {
        return [
            'id' => $interestedParty->getId(),
            'stakeholder' => $interestedParty->getStakeholder(),
            'requirement' => $interestedParty->getRequirement(),
            'position' => $interestedParty->getPosition(),
        ];
    }
}
