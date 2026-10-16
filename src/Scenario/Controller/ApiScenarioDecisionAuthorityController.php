<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Controller;

use Doctrine\ORM\EntityNotFoundException;
use Laminas\Diactoros\Response\JsonResponse;
use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Exception\Exception as CoreException;
use Monarc\Core\Scenario\Feature\ScenarioCapability;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\AnrSupervisor;
use Monarc\FrontOffice\Entity\AnrSupervisorRole;
use Monarc\FrontOffice\Entity\UserRole;
use Monarc\FrontOffice\Scenario\Validator\ScenarioDecisionAuthorityWriteValidator;
use Monarc\FrontOffice\Service\AnrSupervisorService;
use Monarc\FrontOffice\Service\UserService;
use Monarc\FrontOffice\Validator\InputValidator\User\PostUserDataInputValidator;

/** Narrow Scenario API for the existing ANR supervisor and linked-user model. */
final class ApiScenarioDecisionAuthorityController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private ScenarioCapability $scenarioCapability,
        private AnrSupervisorService $supervisorService,
        private ScenarioDecisionAuthorityWriteValidator $writeValidator,
        private PostUserDataInputValidator $userDataValidator,
        private UserService $userService
    ) {
    }

    public function getList()
    {
        $this->scenarioCapability->assertEnabled();
        $anr = $this->scenarioAnr();
        if ($anr === null) {
            return $this->notFound();
        }
        if ($this->resource() === 'users') {
            return $this->getPreparedJsonResponse([
                'items' => [],
                'canCreate' => $this->supervisorService->canCreateUsers(),
            ]);
        }
        if ($this->resource() === 'linkable-users') {
            if (!$this->supervisorService->canManageLinkedUsers($anr)) {
                return $this->forbidden();
            }
            $query = trim((string) $this->params()->fromQuery('query', ''));
            if (mb_strlen($query) > 100) {
                return $this->invalidPayload();
            }
            $excludeSupervisorId = $this->params()->fromQuery('excludeSupervisorId');
            if ($excludeSupervisorId !== null && (!ctype_digit((string) $excludeSupervisorId)
                || (int) $excludeSupervisorId < 1)) {
                return $this->invalidPayload();
            }

            return $this->getPreparedJsonResponse([
                'items' => $this->supervisorService->getLinkableUsers(
                    $anr,
                    $query,
                    $excludeSupervisorId === null ? null : (int) $excludeSupervisorId
                ),
            ]);
        }

        return $this->getPreparedJsonResponse([
            'items' => array_map($this->prepareSupervisor(...), $this->supervisorService->getList($anr)),
            'canManage' => $this->supervisorService->canManageSupervisors($anr),
            'canCreateUsers' => $this->supervisorService->canCreateUsers(),
        ]);
    }

    public function create($data)
    {
        if ($this->resource() === 'users') {
            return $this->createUser($data);
        }

        return $this->save(null, $data);
    }

    public function patch($id, $data)
    {
        return $this->save((int) $id, $data);
    }

    private function save(?int $supervisorId, mixed $data): JsonResponse
    {
        $this->scenarioCapability->assertEnabled();
        $anr = $this->scenarioAnr();
        if ($anr === null) {
            return $this->notFound();
        }
        $allowedKeys = ['linkedUserId', 'isActive', 'name', 'email', 'rolePosition', 'roles'];
        if ($this->resource() !== 'supervisors' || !is_array($data)
            || array_diff(array_keys($data), $allowedKeys) !== []
            || !array_key_exists('isActive', $data)) {
            return $this->invalidPayload();
        }

        try {
            if (!array_key_exists('roles', $data) || $data['roles'] === []) {
                $data['roles'] = [AnrSupervisorRole::ROLE_RESIDUAL_RISK_APPROVER];
            }
            $this->validatePostParams($this->writeValidator, $data);
            $validData = $this->writeValidator->getValidData();
            $linkedUserId = isset($validData['linkedUserId']) && (int) $validData['linkedUserId'] > 0
                ? (int) $validData['linkedUserId']
                : null;
            if ($linkedUserId !== null && !$this->supervisorService->canManageLinkedUsers($anr)) {
                return $this->forbidden();
            }
            if ($linkedUserId === null && !$this->supervisorService->canManageSupervisors($anr)) {
                return $this->forbidden();
            }
            $supervisor = $this->supervisorService->configureResidualRiskApprover(
                $anr,
                $supervisorId,
                $linkedUserId,
                (bool) $validData['isActive'],
                $validData['name'] ?? null,
                $validData['email'] ?? null
            );
        } catch (EntityNotFoundException) {
            return $this->notFound();
        } catch (CoreException $exception) {
            return $this->error(422, 'validation_failed', $exception->getMessage());
        }

        return $this->getSuccessfulJsonResponse($this->prepareSupervisor($supervisor));
    }

    private function createUser(mixed $data): JsonResponse
    {
        if (!$this->supervisorService->canCreateUsers() || !is_array($data)
            || array_diff(array_keys($data), ['firstname', 'lastname', 'email']) !== []
        ) {
            return $this->forbidden();
        }

        try {
            $data['role'] = [UserRole::USER_FO];
            $this->validatePostParams($this->userDataValidator, $data);
            $user = $this->userService->create($this->userDataValidator->getValidData());
        } catch (CoreException $exception) {
            return $this->error(422, 'validation_failed', $exception->getMessage());
        }

        return $this->getSuccessfulJsonResponse([
            'id' => $user->getId(),
            'firstname' => $user->getFirstname(),
            'lastname' => $user->getLastname(),
            'email' => $user->getEmail(),
        ]);
    }

    private function scenarioAnr(): ?Anr
    {
        $anr = $this->getRequest()->getAttribute('anr');

        return $anr instanceof Anr && $anr->getAnalysisType() === Anr::ANALYSIS_TYPE_SCENARIO ? $anr : null;
    }

    private function resource(): string
    {
        return (string) $this->params()->fromRoute('resource');
    }

    /** @return array<string, mixed> */
    private function prepareSupervisor(AnrSupervisor $supervisor): array
    {
        $linkedUser = $supervisor->getLinkedUser();

        return [
            'id' => $supervisor->getId(),
            'linkedUserId' => $linkedUser?->getId(),
            'name' => $supervisor->getName(),
            'email' => $supervisor->getEmail(),
            'roles' => $supervisor->getRolesArray(),
            'isActive' => $supervisor->isActive(),
        ];
    }

    private function forbidden(): JsonResponse
    {
        return $this->error(403, 'forbidden', 'You are not authorised to manage supervisors.');
    }

    private function invalidPayload(): JsonResponse
    {
        return $this->error(422, 'validation_failed', 'The decision-authority payload is invalid.');
    }

    private function notFound(): JsonResponse
    {
        return $this->error(404, 'not_found', 'Scenario analysis or supervisor was not found.');
    }

    private function error(int $status, string $code, string $message): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
