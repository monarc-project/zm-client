<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Controller;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Scenario\Feature\ScenarioCapability;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Scenario\Table\ScenarioInterestedPartyReferenceTable;
use Monarc\FrontOffice\Scenario\Validator\ScenarioInterestedPartyReferenceValidator;

/** Handles analysis-level Scenario evidence links without mutating legacy interested parties. */
final class ApiScenarioInterestedPartyReferenceController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private ScenarioCapability $capability,
        private ConnectedUserService $connectedUserService,
        private ScenarioInterestedPartyReferenceTable $references,
        private ScenarioInterestedPartyReferenceValidator $validator
    ) {
    }

    public function getList()
    {
        $this->capability->assertEnabled();
        $anr = $this->scenarioAnr();
        if ($anr === null) {
            return $this->notFound();
        }
        $query = trim((string) $this->params()->fromQuery('query', ''));
        if (mb_strlen($query) > 100) {
            return $this->invalidPayload();
        }

        return $this->getPreparedJsonResponse($this->references->list(
            (int) $anr->getId(),
            $query,
            max(1, (int) $this->params()->fromQuery('page', 1)),
            min(100, max(1, (int) $this->params()->fromQuery('pageSize', 25)))
        ));
    }

    public function create($data)
    {
        $this->capability->assertEnabled();
        $anr = $this->scenarioAnr();
        $revision = $this->ifMatchRevision();
        if ($anr === null) {
            return $this->notFound();
        }
        if ($revision === null || !is_array($data) || array_keys($data) !== ['interestedPartyId']) {
            return $this->invalidPayload();
        }
        $this->validatePostParams($this->validator, $data);
        $linked = $this->references->attach(
            (int) $anr->getId(),
            $this->actorId(),
            (int) $this->validator->getValidData()['interestedPartyId'],
            $revision,
            $this->correlationId()
        );

        return $linked === null
            ? $this->revisionConflict()
            : $this->getSuccessfulJsonResponse($linked);
    }

    public function delete($id)
    {
        $this->capability->assertEnabled();
        $anr = $this->scenarioAnr();
        $revision = $this->ifMatchRevision();
        if ($anr === null || $revision === null || !$this->isUuid((string) $id)) {
            return $this->invalidPayload();
        }
        $deleted = $this->references->detach(
            (int) $anr->getId(),
            $this->actorId(),
            (string) $id,
            $revision,
            $this->correlationId()
        );

        return $deleted ? new EmptyResponse(204) : $this->revisionConflict();
    }

    private function scenarioAnr(): ?Anr
    {
        $anr = $this->getRequest()->getAttribute('anr');

        return $anr instanceof Anr && $anr->getAnalysisType() === Anr::ANALYSIS_TYPE_SCENARIO ? $anr : null;
    }

    private function actorId(): int
    {
        $user = $this->connectedUserService->getConnectedUser();

        return $user instanceof User ? (int) $user->getId() : 0;
    }

    private function ifMatchRevision(): ?int
    {
        $header = $this->getRequest()->getHeader('If-Match');
        $revision = $header === false ? false : filter_var(
            $header->getFieldValue(),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        return $revision === false ? null : $revision;
    }

    private function correlationId(): string
    {
        $header = $this->getRequest()->getHeader('X-Correlation-ID');

        return $header === false ? bin2hex(random_bytes(16)) : substr($header->getFieldValue(), 0, 128);
    }

    private function isUuid(string $value): bool
    {
        return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $value) === 1;
    }

    private function invalidPayload(): JsonResponse
    {
        return new JsonResponse(['error' => [
            'code' => 'invalid_payload',
            'message' => 'The interested-party reference payload is invalid.',
        ]], 422);
    }

    private function revisionConflict(): JsonResponse
    {
        return new JsonResponse(['error' => [
            'code' => 'revision_conflict',
            'message' => 'Reload the Scenario analysis before retrying.',
        ]], 409);
    }

    private function notFound(): JsonResponse
    {
        return new JsonResponse(['error' => [
            'code' => 'not_found',
            'message' => 'Scenario analysis was not found.',
        ]], 404);
    }
}
