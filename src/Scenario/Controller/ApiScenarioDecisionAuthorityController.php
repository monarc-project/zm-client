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
use Monarc\FrontOffice\Scenario\Validator\ScenarioDecisionAuthorityWriteValidator;
use Monarc\FrontOffice\Service\AnrSupervisorService;

/** Narrow Scenario API for the existing ANR supervisor and linked-user model. */
final class ApiScenarioDecisionAuthorityController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private ScenarioCapability $capability,
        private AnrSupervisorService $supervisors,
        private ScenarioDecisionAuthorityWriteValidator $validator
    ) {
    }

    public function getList()
    {
        $this->capability->assertEnabled();
        $anr = $this->scenarioAnr();
        if ($anr === null) {
            return $this->notFound();
        }
        if ($this->resource() === 'linkable-users') {
            if (!$this->supervisors->canManageLinkedUsers($anr)) {
                return $this->forbidden();
            }
            $query = trim((string) $this->params()->fromQuery('query', ''));
            if (mb_strlen($query) > 100) {
                return $this->invalidPayload();
            }

            return $this->getPreparedJsonResponse([
                'items' => $this->supervisors->getLinkableUsers($anr, $query),
            ]);
        }

        return $this->getPreparedJsonResponse([
            'items' => array_map($this->prepareSupervisor(...), $this->supervisors->getList($anr)),
        ]);
    }

    public function create($data)
    {
        return $this->save(null, $data);
    }

    public function patch($id, $data)
    {
        return $this->save((int) $id, $data);
    }

    private function save(?int $supervisorId, mixed $data): JsonResponse
    {
        $this->capability->assertEnabled();
        $anr = $this->scenarioAnr();
        if ($anr === null) {
            return $this->notFound();
        }
        if ($this->resource() !== 'supervisors' || !is_array($data)
            || array_diff(array_keys($data), ['linkedUserId', 'isActive']) !== []
            || array_diff(['linkedUserId', 'isActive'], array_keys($data)) !== []) {
            return $this->invalidPayload();
        }
        if (!$this->supervisors->canManageLinkedUsers($anr)) {
            return $this->forbidden();
        }

        try {
            $this->validatePostParams($this->validator, $data);
            $validData = $this->validator->getValidData();
            $supervisor = $this->supervisors->configureResidualRiskApprover(
                $anr,
                $supervisorId,
                (int) $validData['linkedUserId'],
                (bool) $validData['isActive']
            );
        } catch (EntityNotFoundException) {
            return $this->notFound();
        } catch (CoreException $exception) {
            return $this->error(422, 'validation_failed', $exception->getMessage());
        }

        return $this->getSuccessfulJsonResponse($this->prepareSupervisor($supervisor));
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
        return $this->error(403, 'forbidden', 'You are not authorised to manage linked supervisors.');
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
