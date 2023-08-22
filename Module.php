<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice;

use DateTime;
use Laminas\Stdlib\ResponseInterface;
use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\CronTask\Service\CronTaskService;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\CronTask;
use Monarc\FrontOffice\Model\Entity\Snapshot;
use Monarc\FrontOffice\Model\Table\InstanceTable;
use Monarc\FrontOffice\Model\Table\SnapshotTable;
use Monarc\FrontOffice\Table\AnrTable;
use Monarc\FrontOffice\Table\UserAnrTable;
use Laminas\Http\Request;
use Laminas\Mvc\ModuleRouteListener;
use Laminas\Mvc\MvcEvent;
use Laminas\Permissions\Rbac\Rbac;
use Laminas\Permissions\Rbac\Role;
use Laminas\View\Model\JsonModel;

class Module
{
    public function onBootstrap(MvcEvent $e)
    {
        if ($e->getRequest() instanceof Request) {
            $eventManager = $e->getApplication()->getEventManager();
            $moduleRouteListener = new ModuleRouteListener();
            $moduleRouteListener->attach($eventManager);

            $this->initRbac($e);

            $eventManager->attach(MvcEvent::EVENT_ROUTE, [$this, 'checkRbac'], 0);
            $eventManager->attach(MvcEvent::EVENT_DISPATCH_ERROR, [$this, 'onDispatchError'], 0);
            $eventManager->attach(MvcEvent::EVENT_RENDER_ERROR, [$this, 'onRenderError'], 0);
        }
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onDispatchError($e)
    {
        return $this->getJsonModelError($e);
    }

    public function onRenderError($e)
    {
        return $this->getJsonModelError($e);
    }

    public function getJsonModelError($e)
    {
        $error = $e->getError();
        if (!$error) {
            return null;
        }

        $exception = $e->getParam('exception');
        $exceptionJson = [];
        if ($exception) {
            $exceptionJson = [
                'class' => \get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'message' => $exception->getMessage(),
                'stacktrace' => $exception->getTraceAsString(),
            ];

            if ($exception->getCode() >= 400 && $exception->getCode() < 600) {
                $e->getResponse()->setStatusCode($exception->getCode());
            }
        }
        $errorJson = [
            'message' => $exception
                ? $exception->getMessage()
                : 'An error occurred during execution; please try again later.',
            'error' => $error,
            'exception' => $exceptionJson,
        ];
        if ($error === 'error-router-no-match') {
            $errorJson['message'] = 'Resource not found.';
        }

        if ($exception->getCode() === 400) {
            $model = new JsonModel([
                'errors' => [json_decode($exception->getMessage(), true, 512, JSON_THROW_ON_ERROR)],
            ]);
        } else {
            $model = new JsonModel(['errors' => [$errorJson]]);
        }

        $e->setResult($model);

        return $model;
    }

    public function initRbac(MvcEvent $mvcEvent)
    {
        $sm = $mvcEvent->getApplication()->getServiceManager();
        $config = $sm->get('Config');

        $globalPermissions = $config['permissions'] ?? [];

        $rolesPermissions = $config['roles'] ?? [];

        $rbac = new Rbac();
        foreach ($rolesPermissions as $role => $permissions) {
            $role = new Role($role);

            //global permissions
            foreach ($globalPermissions as $globalPermission) {
                if (!$role->hasPermission($globalPermission)) {
                    $role->addPermission($globalPermission);
                }
            }

            //role permissions
            foreach ($permissions as $permission) {
                if (!$role->hasPermission($permission)) {
                    $role->addPermission($permission);
                }
            }

            $rbac->addRole($role);
        }

        //add role for guest (user not logged)
        $role = new Role('guest');
        foreach ($globalPermissions as $globalPermission) {
            if (!$role->hasPermission($globalPermission)) {
                $role->addPermission($globalPermission);
            }
        }
        $rbac->addRole($role);

        //setting to view
        $mvcEvent->getViewModel()->rbac = $rbac;
    }

    /**
     * @param MvcEvent $mvcEvent
     *
     * @return ResponseInterface|void
     */
    public function checkRbac(MvcEvent $mvcEvent)
    {
        $route = $mvcEvent->getRouteMatch()->getMatchedRouteName();
        $serviceManager = $mvcEvent->getApplication()->getServiceManager();

        /** @var ConnectedUserService $connectedUserService */
        $connectedUserService = $serviceManager->get(ConnectedUserService::class);
        $connectedUser = $connectedUserService->getConnectedUser();

        $roles[] = 'guest';
        if ($connectedUser !== null) {
            $roles = $connectedUser->getRoles();
        }

        $isGranted = false;
        /** @var SnapshotTable $snapshotTable */
        $snapshotTable = $serviceManager->get(SnapshotTable::class);
        /** @var UserAnrTable $userAnrTable */
        $userAnrTable = $serviceManager->get(UserAnrTable::class);
        foreach ($roles as $role) {
            if ($mvcEvent->getViewModel()->rbac->isGranted($role, $route)) {
                $anrId = (int)$mvcEvent->getRouteMatch()->getParam('id');
                if (($route === 'monarc_api_client_anr' && $anrId !== 0)
                    || strncmp($route, 'monarc_api_global_client_anr/', 29) === 0
                ) {
                    if ($route !== 'monarc_api_client_anr') {
                        $anrId = (int)$mvcEvent->getRouteMatch()->getParam('anrid');
                    }
                    if ($anrId === 0) {
                        break;
                    }

                    $result = $this->validateAnrStatusAndGetResponseIfInvalid($anrId, $mvcEvent, $route);
                    if ($result !== null) {
                        return $result;
                    }

                    $userAnr = $userAnrTable->findByAnrIdAndUser($anrId, $connectedUser);
                    if ($userAnr === null) {
                        // We authorise the access for snapshot, but for read only (GET).
                        if ($mvcEvent->getRequest()->getMethod() !== 'GET'
                            && !$this->authorizedPost($route, $mvcEvent->getRequest()->getMethod())
                        ) {
                            break;
                        }
                        /** @var Snapshot|false $snapshot */
                        $snapshot = current($snapshotTable->getEntityByFields(['anr' => $anrId]));
                        if ($snapshot === false) {
                            break;
                        }
                        $userAnr = $userAnrTable->findByAnrAndUser($snapshot->getAnrReference(), $connectedUser);
                        if ($userAnr === null) {
                            // the user did not have access to the anr, from which this snapshot was created.
                            break;
                        }
                        $isGranted = true;
                        break;
                    }

                    if (!$userAnr->hasWriteAccess() && $mvcEvent->getRequest()->getMethod() !== 'GET') {
                        // We authorize POST for the specific actions.
                        if ($this->authorizedPost($route, $mvcEvent->getRequest()->getMethod())) {
                            $isGranted = true;
                        }
                        break;
                    }
                }

                $isGranted = true;
                break;
            }
        }

        if (!$isGranted) {
            $response = $mvcEvent->getResponse();
            $response->setStatusCode($connectedUser === null ? 401 : 403);

            return $response;
        }
    }

    private function authorizedPost($route, $method)
    {
        return $method === 'POST'
            && ($route === 'monarc_api_global_client_anr/export' // export ANR
                || $route === 'monarc_api_global_client_anr/instance_export' // export Instance
                || $route === 'monarc_api_global_client_anr/objects_export' // export  Object
                || $route === 'monarc_api_global_client_anr/deliverable' // generate a report
            );
    }

    /**
     * Validates the anr status for NON GET method requests exclude DELETE (cancellation of background import).
     */
    private function validateAnrStatusAndGetResponseIfInvalid(
        int $anrId,
        MvcEvent $mvcEvent,
        string $route
    ): ?ResponseInterface {
        /* GET requests are always allowed and cancellation of import (delete import process -> PID). */
        if ($mvcEvent->getRequest()->getMethod() === Request::METHOD_GET
            || (
                $mvcEvent->getRequest()->getMethod() === Request::METHOD_DELETE
                && $route === 'monarc_api_global_client_anr/instance_import'
            )
        ) {
            return null;
        }

        $serviceManager = $mvcEvent->getApplication()->getServiceManager();

        /** @var Anr $anr */
        $anr = $serviceManager->get(AnrTable::class)->findById($anrId);
        if ($anr->isActive()) {
            return null;
        }

        /* Allow deleting anr if the status is waiting for import or there is an import error. */
        if ($route === 'monarc_api_client_anr'
            && $mvcEvent->getRequest()->getMethod() === Request::METHOD_DELETE
            && ($anr->getStatus() === AnrSuperClass::STATUS_IMPORT_ERROR
                || $anr->getStatus() === AnrSuperClass::STATUS_AWAITING_OF_IMPORT
            )
        ) {
            return null;
        }

        /* Allow to restore a snapshot if there is an import error. */
        if ($route === 'monarc_api_global_client_anr/snapshot_restore'
            && $anr->getStatus() === AnrSuperClass::STATUS_IMPORT_ERROR
            && $mvcEvent->getRequest()->getMethod() === Request::METHOD_POST
        ) {
            return null;
        }

        $result = [
            'status' => $anr->getStatusName(),
            'importStatus' => [],
        ];
        /** @var CronTaskService $cronTaskService */
        $cronTaskService = $serviceManager->get(CronTaskService::class);

        if ($anr->getStatus() === AnrSuperClass::STATUS_UNDER_IMPORT) {
            $importCronTask = $cronTaskService->getLatestTaskByNameWithParam(
                CronTask::NAME_INSTANCE_IMPORT,
                ['anrId' => $anrId]
            );
            if ($importCronTask !== null && $importCronTask->getStatus() === CronTask::STATUS_IN_PROGRESS) {
                /** @var InstanceTable $instanceTable */
                $instanceTable = $serviceManager->get(InstanceTable::class);
                $timeDiff = $importCronTask->getUpdatedAt()->diff(new DateTime());
                $instancesNumber = $instanceTable->countByAnrIdFromDate($anrId, $importCronTask->getUpdatedAt());
                $result['importStatus'] = [
                    'executionTime' => $timeDiff->h . ' hours ' . $timeDiff->i . ' min ' . $timeDiff->s . ' sec',
                    'createdInstances' => $instancesNumber,
                ];
            }
        } elseif ($anr->getStatus() === AnrSuperClass::STATUS_IMPORT_ERROR) {
            $importCronTask = $cronTaskService->getLatestTaskByNameWithParam(
                CronTask::NAME_INSTANCE_IMPORT,
                ['anrId' => $anrId]
            );
            if ($importCronTask !== null && $importCronTask->getStatus() === CronTask::STATUS_FAILURE) {
                $result['importStatus'] = [
                    'errorMessage' => $importCronTask->getResultMessage(),
                ];
            }
        }

        $response = $mvcEvent->getResponse();
        $response->setContent(json_encode($result, JSON_THROW_ON_ERROR));
        $response->setStatusCode(409);

        return $response;
    }
}
