<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice;

use Laminas\Stdlib\ResponseInterface;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Scenario\Service\ScenarioDelegatedAuthenticationService;
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

            // Route matching runs at priority 1. Resolve the BFF identity
            // immediately afterwards and before the route-level RBAC check.
            $eventManager->attach(MvcEvent::EVENT_ROUTE, [$this, 'resolveScenarioDelegatedIdentity'], 1);
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

        if ($exception && $exception->getCode() === 400) {
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
            $roles = $connectedUser->getRolesArray();
        }

        foreach ($roles as $role) {
            if ($mvcEvent->getViewModel()->rbac->isGranted($role, $route)) {
                return;
            }
        }

        $response = $mvcEvent->getResponse();
        $response->setStatusCode($connectedUser === null ? 401 : 403);

        return $response;
    }

    /** Resolve the BFF-held session before the normal RBAC listener runs. */
    public function resolveScenarioDelegatedIdentity(MvcEvent $mvcEvent): ?ResponseInterface
    {
        $routeMatch = $mvcEvent->getRouteMatch();
        if ($routeMatch === null || !in_array($routeMatch->getMatchedRouteName(), [
            'scenario_v1_anr_analysis',
            'scenario_v1_anr_template',
            'scenario_v1_anr_risk_scenarios',
            'scenario_v1_anr_risk_story_references',
            'scenario_v1_anr_criteria_profiles',
            'scenario_v1_anr_criteria_profiles_assigned',
            'scenario_v1_anr_risk_scenario_assessments',
            'scenario_v1_frontoffice_templates',
            'scenario_v1_anr_decision_authority',
        ], true)) {
            return null;
        }
        /** @var Request $request */
        $request = $mvcEvent->getRequest();
        $session = $request->getHeader('x-scenario-session');
        $serviceToken = $request->getHeader('x-scenario-service-token');
        if ($session === false && $serviceToken === false) {
            // Direct FrontOffice API requests retain the established token
            // authentication flow. BFF requests always supply both headers.
            return null;
        }
        if ($session === false || $serviceToken === false) {
            $response = $mvcEvent->getResponse();
            $response->setStatusCode(403);
            $mvcEvent->setResult($response);
            $mvcEvent->stopPropagation(true);

            return $response;
        }
        $serviceManager = $mvcEvent->getApplication()->getServiceManager();
        /** @var ScenarioDelegatedAuthenticationService $authentication */
        $authentication = $serviceManager->get(ScenarioDelegatedAuthenticationService::class);
        $anrId = $routeMatch->getParam('anrid');
        if ($authentication->authenticate(
            $session->getFieldValue(),
            $serviceToken->getFieldValue(),
            $anrId === null ? null : (int) $anrId
        )) {
            return null;
        }
        $response = $mvcEvent->getResponse();
        $response->setStatusCode(403);
        $mvcEvent->setResult($response);
        $mvcEvent->stopPropagation(true);

        return $response;
    }
}
