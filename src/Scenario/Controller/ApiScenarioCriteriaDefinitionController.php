<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Exception\ActionForbiddenException;
use Monarc\Core\Scenario\Feature\ScenarioCapability;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Scenario\Service\ScenarioCriteriaDefinitionService;
use Monarc\FrontOffice\Scenario\Validator\ScenarioCriteriaDefinitionValidator;

/** Exposes the ANR-bound Context Establishment criteria draft and immutable lock workflow. */
final class ApiScenarioCriteriaDefinitionController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private ScenarioCapability $capability,
        private ScenarioCriteriaDefinitionService $criteria,
        private ScenarioCriteriaDefinitionValidator $validator
    ) {
    }

    public function getList()
    {
        $anr = $this->scenarioAnr();
        if ($anr === null) {
            return $this->notFound();
        }

        return match ($this->resource()) {
            'source' => $this->getPreparedJsonResponse($this->criteria->source($anr)),
            'draft' => $this->getPreparedJsonResponse($this->criteria->definition($anr)),
            default => $this->notFound(),
        };
    }

    public function create($data)
    {
        $anr = $this->scenarioAnr();
        if ($anr === null) {
            return $this->notFound();
        }
        $revision = $this->ifMatch();
        if ($revision === null) {
            return $this->conflict();
        }
        try {
            match ($this->resource()) {
                'draft' => $this->validator->createDraft($data),
                'lock' => $this->validator->lock($data),
                default => null,
            };
            $result = match ($this->resource()) {
                'draft' => $this->criteria->createDraft(
                    $anr,
                    $revision,
                    $this->correlationId()
                ),
                'lock' => $this->criteria->lock(
                    $anr,
                    $revision,
                    $this->correlationId()
                ),
                default => null,
            };
        } catch (ActionForbiddenException $exception) {
            return $this->error(403, 'forbidden', $exception->getMessage());
        } catch (\InvalidArgumentException $exception) {
            return $this->error(422, 'invalid_payload', $exception->getMessage());
        }

        return $result === null
            ? $this->conflict()
            : $this->getSuccessfulJsonResponse($result);
    }

    public function patchList($data)
    {
        $anr = $this->scenarioAnr();
        if ($anr === null || $this->resource() !== 'draft') {
            return $this->notFound();
        }
        $revision = $this->ifMatch();
        if ($revision === null) {
            return $this->conflict();
        }
        try {
            $result = $this->criteria->updateDraft(
                $anr,
                $this->validator->updateDraft($data),
                $revision,
                $this->correlationId()
            );
        } catch (ActionForbiddenException $exception) {
            return $this->error(403, 'forbidden', $exception->getMessage());
        } catch (\InvalidArgumentException $exception) {
            return $this->error(422, 'invalid_payload', $exception->getMessage());
        }

        return $result === null
            ? $this->conflict()
            : $this->getSuccessfulJsonResponse($result);
    }

    private function scenarioAnr(): ?Anr
    {
        $this->capability->assertEnabled();
        $anr = $this->getRequest()->getAttribute('anr');

        return $anr instanceof Anr && $anr->getAnalysisType() === Anr::ANALYSIS_TYPE_SCENARIO ? $anr : null;
    }

    private function resource(): string
    {
        return (string) $this->params()->fromRoute('resource');
    }

    private function ifMatch(): ?int
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

    private function conflict(): JsonResponse
    {
        return $this->error(409, 'revision_conflict', 'Reload the Scenario criteria before retrying.');
    }

    private function notFound(): JsonResponse
    {
        return $this->error(404, 'not_found', 'Scenario criteria were not found.');
    }

    private function error(int $status, string $code, string $message): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
