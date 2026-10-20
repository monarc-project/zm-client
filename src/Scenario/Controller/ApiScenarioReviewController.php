<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Exception\ActionForbiddenException;
use Monarc\Core\Scenario\Feature\ScenarioCapability;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Scenario\Service\ScenarioReviewService;
use Monarc\FrontOffice\Scenario\Validator\ScenarioReviewValidator;

/** Exposes the ANR-bound Scenario reassessment schedule, review cases, and explicit trigger occurrences. */
final class ApiScenarioReviewController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private ScenarioCapability $capability,
        private ScenarioReviewService $reviews
    ) {
    }

    public function getList()
    {
        $this->capability->assertEnabled();
        $anr = $this->scenarioAnr();
        if ($anr === null) {
            return $this->notFound();
        }

        return match ($this->resource()) {
            'schedule' => $this->getPreparedJsonResponse($this->reviews->summary($anr)),
            'cases' => $this->getPreparedJsonResponse($this->reviews->listCases($anr)),
            'occurrences' => $this->getPreparedJsonResponse($this->reviews->listOccurrences($anr)),
            default => $this->notFound(),
        };
    }

    public function get($id)
    {
        $this->capability->assertEnabled();
        $anr = $this->scenarioAnr();
        if ($anr === null || $this->resource() !== 'cases' || !$this->isUuid((string) $id)) {
            return $this->notFound();
        }
        $case = $this->reviews->case($anr, (string) $id);

        return $case === null ? $this->notFound() : $this->getPreparedJsonResponse($case);
    }

    public function create($data)
    {
        $this->capability->assertEnabled();
        $anr = $this->scenarioAnr();
        if ($anr === null) {
            return $this->notFound();
        }
        try {
            $result = match ($this->resource()) {
                'cases' => $this->reviews->createCase(
                    $anr,
                    ScenarioReviewValidator::createCase($data),
                    $this->correlationId()
                ),
                'occurrences' => $this->reviews->recordTriggerOccurrence(
                    $anr,
                    ScenarioReviewValidator::triggerOccurrence($data),
                    $this->correlationId()
                ),
                default => null,
            };
        } catch (ActionForbiddenException $exception) {
            return $this->error(403, 'forbidden', $exception->getMessage());
        } catch (\LogicException $exception) {
            return $this->error(422, 'invalid_transition', $exception->getMessage());
        } catch (\InvalidArgumentException $exception) {
            return $this->error(422, 'invalid_payload', $exception->getMessage());
        }

        return $result === null ? $this->notFound() : $this->getSuccessfulJsonResponse($result);
    }

    public function patch($id, $data)
    {
        $this->capability->assertEnabled();
        $anr = $this->scenarioAnr();
        // "action" is reserved by Laminas' REST dispatcher. A route parameter
        // with that name would make it try assignAction() before PATCH reaches
        // this controller.
        $action = (string) $this->params()->fromRoute('operation');
        $revision = $this->ifMatchRevision();
        if ($anr === null || $this->resource() !== 'cases' || !$this->isUuid((string) $id) || $revision === null) {
            return $this->invalidPayload();
        }
        try {
            $result = $this->reviews->transition(
                $anr,
                (string) $id,
                $action,
                ScenarioReviewValidator::transition($action, $data),
                $revision,
                $this->correlationId()
            );
        } catch (ActionForbiddenException $exception) {
            return $this->error(403, 'forbidden', $exception->getMessage());
        } catch (\LogicException $exception) {
            return $this->error(422, 'invalid_transition', $exception->getMessage());
        } catch (\InvalidArgumentException $exception) {
            return $this->error(422, 'invalid_payload', $exception->getMessage());
        }

        return $result === null
            ? $this->error(409, 'revision_conflict', 'Reload the Scenario review case before retrying.')
            : $this->getSuccessfulJsonResponse($result);
    }

    private function resource(): string
    {
        return (string) $this->params()->fromRoute('resource');
    }

    private function scenarioAnr(): ?Anr
    {
        $anr = $this->getRequest()->getAttribute('anr');

        return $anr instanceof Anr && $anr->getAnalysisType() === Anr::ANALYSIS_TYPE_SCENARIO ? $anr : null;
    }

    private function ifMatchRevision(): ?int
    {
        $header = $this->getRequest()->getHeader('If-Match');
        $revision = $header === false
            ? false
            : filter_var($header->getFieldValue(), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

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
        return $this->error(422, 'invalid_payload', 'The Scenario review request is invalid.');
    }

    private function notFound(): JsonResponse
    {
        return $this->error(404, 'not_found', 'Scenario review data was not found.');
    }

    private function error(int $status, string $code, string $message): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
