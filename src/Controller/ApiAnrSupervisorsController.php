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
use Monarc\FrontOffice\Entity\AnrSupervisor;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Service\AnrSupervisorService;
use Monarc\FrontOffice\Validator\InputValidator\AnrSupervisor\PatchAnrSupervisorStatusInputValidator;
use Monarc\FrontOffice\Validator\InputValidator\AnrSupervisor\PostAnrSupervisorDataInputValidator;
use Monarc\FrontOffice\Validator\InputValidator\AnrSupervisor\UpdateAnrSupervisorDataInputValidator;

class ApiAnrSupervisorsController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private AnrSupervisorService $anrSupervisorService,
        private PatchAnrSupervisorStatusInputValidator $patchAnrSupervisorStatusInputValidator,
        private PostAnrSupervisorDataInputValidator $postAnrSupervisorDataInputValidator,
        private UpdateAnrSupervisorDataInputValidator $updateAnrSupervisorDataInputValidator
    ) {
    }

    public function getList()
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $rawUserFilter = $this->params()->fromQuery('userFilter', null);
        if ($rawUserFilter !== null) {
            return $this->getPreparedJsonResponse([
                'users' => $this->anrSupervisorService->getLinkableUsers(trim((string)$rawUserFilter)),
            ]);
        }
        $statusParam = $this->params()->fromQuery('status', null);
        $isActive = $statusParam === null ? null : filter_var($statusParam, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $supervisors = $this->anrSupervisorService->getList(
            $anr,
            trim((string)$this->params()->fromQuery('filter', '')) ?: null,
            trim((string)$this->params()->fromQuery('role', '')) ?: null,
            $isActive
        );

        return $this->getPreparedJsonResponse([
            'count' => count($supervisors),
            'supervisors' => array_map(
                fn (AnrSupervisor $supervisor): array => $this->prepareSupervisorData($supervisor),
                $supervisors
            ),
        ]);
    }

    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getSuccessfulJsonResponse($this->prepareSupervisorData(
            $this->anrSupervisorService->get($anr, (int)$id)
        ));
    }

    public function create($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->validatePostParams($this->postAnrSupervisorDataInputValidator, $data);
        $validData = $this->postAnrSupervisorDataInputValidator->getValidData();
        $this->anrSupervisorService->validateNoDuplicateSupervisor($anr, $validData);

        return $this->getSuccessfulJsonResponse($this->prepareSupervisorData(
            $this->anrSupervisorService->create($anr, $validData)
        ));
    }

    public function update($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->validatePostParams($this->updateAnrSupervisorDataInputValidator, $data);
        $validData = $this->updateAnrSupervisorDataInputValidator->getValidData();
        $this->anrSupervisorService->validateNoDuplicateSupervisor($anr, $validData, (int)$id);

        return $this->getSuccessfulJsonResponse($this->prepareSupervisorData(
            $this->anrSupervisorService->update(
                $anr,
                (int)$id,
                $validData
            )
        ));
    }

    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->anrSupervisorService->delete($anr, (int)$id);

        return $this->getSuccessfulJsonResponse();
    }

    public function patch($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->validatePostParams($this->patchAnrSupervisorStatusInputValidator, $data);
        $validData = $this->patchAnrSupervisorStatusInputValidator->getValidData();

        return $this->getSuccessfulJsonResponse($this->prepareSupervisorData(
            $this->anrSupervisorService->patchStatus($anr, (int)$id, (bool)$validData['isActive'])
        ));
    }

    private function prepareSupervisorData(AnrSupervisor $supervisor): array
    {
        $linkedUser = $supervisor->getLinkedUser();

        return [
            'id' => $supervisor->getId(),
            'name' => $supervisor->getName(),
            'email' => $supervisor->getEmail(),
            'rolePosition' => $supervisor->getRolePosition(),
            'linkedUserId' => $linkedUser?->getId(),
            'linkedUser' => $this->prepareUserData($linkedUser),
            'roles' => $supervisor->getRolesArray(),
            'isActive' => $supervisor->isActive(),
        ];
    }

    private function prepareUserData(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->getId(),
            'firstname' => $user->getFirstname(),
            'lastname' => $user->getLastname(),
            'email' => $user->getEmail(),
        ];
    }
}
