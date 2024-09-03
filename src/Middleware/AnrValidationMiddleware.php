<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Middleware;

use DateTime;
use Doctrine\ORM\EntityNotFoundException;
use Fig\Http\Message\StatusCodeInterface;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Http\Request;
use Laminas\Router\RouteMatch;
use Monarc\Core\Entity\AnrSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\CronTask\Service\CronTaskService;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Table;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AnrValidationMiddleware implements MiddlewareInterface
{
    private Entity\User $connectedUser;

    public function __construct(
        private Table\AnrTable $anrTable,
        private Table\UserAnrTable $userAnrTable,
        private Table\InstanceTable $instanceTable,
        private CronTaskService $cronTaskService,
        private ResponseFactory $responseFactory,
        ConnectedUserService $connectedUserService
    ) {
        /** @var Entity\User $connectedUser */
        $connectedUser = $connectedUserService->getConnectedUser();
        $this->connectedUser = $connectedUser;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var RouteMatch $routeMatch */
        $routeMatch = $request->getAttribute(RouteMatch::class);

        /* Exclude from validation getList and create of /api/client-anr/[:anrid]. */
        if ($this->isRouteAndRequestExcludedFromValidation($routeMatch, $request)) {
            return $handler->handle($request);
        }

        /* Retrieving anr ID from all the routes where /[:anrid]/ is presented in the route. */
        $anrId = $routeMatch->getMatchedRouteName() === 'monarc_api_client_anr'
            ? (int)$routeMatch->getParam('id')
            : (int)$routeMatch->getParam('anrid');
        if ($anrId === 0 && $routeMatch->getMatchedRouteName() === 'monarc_api_duplicate_client_anr') {
            /* Anr ID for the route 'client-duplicate-anr' is passed in the json body as "anr". */
            $anrId = (int)(json_decode((string)$request->getBody(), true, 512, JSON_THROW_ON_ERROR)['anr'] ?? 0);
        }

        try {
            /** @var Entity\Anr $anr */
            $anr = $this->anrTable->findById($anrId);
        } catch (EntityNotFoundException) {
            return $this->responseFactory->createResponse(
                StatusCodeInterface::STATUS_NOT_FOUND,
                sprintf('Analysis with ID "%s" was not found.', $anrId)
            );
        }

        $result = $this->validateAnrStatusAndGetResponseIfInvalid($anr, $request, $routeMatch->getMatchedRouteName());
        if ($result !== null) {
            return $result;
        }

        /* Ensure the record in the anr is presented in the table, means at least read permissions are allowed.
         * It's necessary e.g. for the "monarc_api_duplicate_client_anr" route. */
        $userAnr = $this->userAnrTable->findByAnrAndUser($anr, $this->connectedUser);
        if (($userAnr === null && !$anr->isAnrSnapshot())
            /* There are no permissions set for snapshots,
            so it's necessary to validate the referenced analysis has the access for the user at least to read. */
            || ($anr->isAnrSnapshot()
                && (
                    ($request->getMethod() !== Request::METHOD_GET
                        && !$this->isPostAuthorizedForRoute($routeMatch->getMatchedRouteName(), $request->getMethod())
                    )
                    || $this->userAnrTable
                        ->findByAnrAndUser($anr->getSnapshot()->getAnrReference(), $this->connectedUser) === null
                )
            )
        ) {
            return $this->responseFactory->createResponse(
                StatusCodeInterface::STATUS_FORBIDDEN,
                sprintf('Analysis with ID %s is not accessible for view.', $anrId)
            );
        }

        /* A batch of routes has to be excluded from post (isPostAuthorizedForRoute) to bypass forbidden response. */
        if ($request->getMethod() !== Request::METHOD_GET
            && !$userAnr->hasWriteAccess()
            && !$this->isPostAuthorizedForRoute($routeMatch->getMatchedRouteName(), $request->getMethod())
        ) {
            return $this->responseFactory->createResponse(
                StatusCodeInterface::STATUS_FORBIDDEN,
                sprintf('Analysis with ID %s is not accessible for any modifications.', $anrId)
            );
        }

        $request = $request->withAttribute('anr', $anr);

        return $handler->handle($request);
    }

    private function isRouteAndRequestExcludedFromValidation(RouteMatch $route, ServerRequestInterface $request): bool
    {
        /* The creation of anr or getList is called without anr ID, route "monarc_api_client_anr". */
        return $route->getMatchedRouteName() === 'monarc_api_client_anr'
            && \in_array($request->getMethod(), [Request::METHOD_GET, Request::METHOD_POST], true)
            && $route->getParam('id') === null;
    }

    /**
     * Even if user has view only permission to the analysis, it's allowed to perform export or generate deliverable.
     */
    private function isPostAuthorizedForRoute(string $routeName, string $method)
    {
        return $method === Request::METHOD_POST
            && ($routeName === 'monarc_api_global_client_anr/export' // export ANR
                || $routeName === 'monarc_api_global_client_anr/instance_export' // export Instance
                || $routeName === 'monarc_api_global_client_anr/objects_export' // export  Object
                || $routeName === 'monarc_api_global_client_anr/deliverable' // generate a report
            );
    }

    /**
     * Validates the anr status for NON GET method requests exclude DELETE (cancellation of background import).
     * @throws \JsonException
     */
    private function validateAnrStatusAndGetResponseIfInvalid(
        Entity\Anr $anr,
        ServerRequestInterface $request,
        string $routeName
    ): ?ResponseInterface {
        /* GET requests are always allowed and cancellation of import (delete import process -> PID). */
        if ($request->getMethod() === Request::METHOD_GET
            || ($request->getMethod() === Request::METHOD_DELETE
                && $routeName === 'monarc_api_global_client_anr/instance_import'
            )
        ) {
            return null;
        }

        if ($anr->isActive()) {
            return null;
        }

        /* Allow deleting anr if the status is waiting for import or there is an import error. */
        if ($routeName === 'monarc_api_client_anr'
            && $request->getMethod() === Request::METHOD_DELETE
            && ($anr->getStatus() === AnrSuperClass::STATUS_IMPORT_ERROR
                || $anr->getStatus() === AnrSuperClass::STATUS_AWAITING_OF_IMPORT
            )
        ) {
            return null;
        }

        /* Allow to restore a snapshot if there is an import error. */
        if ($routeName === 'monarc_api_global_client_anr/snapshot_restore'
            && $anr->getStatus() === AnrSuperClass::STATUS_IMPORT_ERROR
            && $request->getMethod() === Request::METHOD_POST
        ) {
            return null;
        }

        $result = [
            'status' => $anr->getStatusName(),
            'importStatus' => [],
        ];

        if ($anr->getStatus() === AnrSuperClass::STATUS_UNDER_IMPORT) {
            $importCronTask = $this->cronTaskService->getLatestTaskByNameWithParam(
                Entity\CronTask::NAME_INSTANCE_IMPORT,
                ['anrId' => $anr->getId()]
            );
            if ($importCronTask !== null && $importCronTask->getStatus() === Entity\CronTask::STATUS_IN_PROGRESS) {
                $timeDiff = $importCronTask->getUpdatedAt() !== null
                    ? $importCronTask->getUpdatedAt()->diff(new DateTime())
                    : $importCronTask->getCreatedAt()->diff(new DateTime());
                $instancesNumber = $this->instanceTable->countByAnrIdFromDate(
                    $anr->getId(),
                    $importCronTask->getUpdatedAt() ?? $importCronTask->getCreatedAt()
                );
                $result['importStatus'] = [
                    'executionTime' => $timeDiff->h . ' hours ' . $timeDiff->i . ' min ' . $timeDiff->s . ' sec',
                    'createdInstances' => $instancesNumber,
                ];
            }
        } elseif ($anr->getStatus() === AnrSuperClass::STATUS_IMPORT_ERROR) {
            $importCronTask = $this->cronTaskService->getLatestTaskByNameWithParam(
                Entity\CronTask::NAME_INSTANCE_IMPORT,
                ['anrId' => $anr->getId()]
            );
            if ($importCronTask !== null && $importCronTask->getStatus() === Entity\CronTask::STATUS_FAILURE) {
                $result['importStatus'] = ['errorMessage' => $importCronTask->getResultMessage()];
            }
        }

        return $this->responseFactory->createResponse(
            StatusCodeInterface::STATUS_CONFLICT,
            json_encode($result, JSON_THROW_ON_ERROR)
        );
    }
}
