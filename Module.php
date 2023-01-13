<?php
namespace Monarc\FrontOffice;

use DateTime;
use Laminas\Stdlib\ResponseInterface;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\CronTask\Service\CronTaskService;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\CronTask;
use Monarc\FrontOffice\Model\Entity\Instance;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\InstanceTable;
use Monarc\FrontOffice\Model\Table\SnapshotTable;
use Monarc\FrontOffice\Model\Table\UserAnrTable;
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

            $eventManager->attach(MvcEvent::EVENT_ROUTE, array($this, 'checkRbac'), 0);
            $eventManager->attach(MvcEvent::EVENT_DISPATCH_ERROR, array($this, 'onDispatchError'), 0);
            $eventManager->attach(MvcEvent::EVENT_RENDER_ERROR, array($this, 'onRenderError'), 0);
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
            return;
        }

        $exception = $e->getParam('exception');
        $exceptionJson = array();
        if ($exception) {
            $exceptionJson = array(
                'class' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'message' => $exception->getMessage(),
                'stacktrace' => $exception->getTraceAsString()
            );

            if ($exception->getCode() >= 400 && $exception->getCode() < 600) {
                $e->getResponse()->setStatusCode($exception->getCode());
            }
        }
        $errorJson = array(
            'message'   => $exception ? $exception->getMessage() : 'An error occurred during execution; please try again later.',
            'error'     => $error,
            'exception' => $exceptionJson,
        );
        if ($error == 'error-router-no-match') {
            $errorJson['message'] = 'Resource not found.';
        }
        $model = new JsonModel(array('errors' => array($errorJson)));
        $e->setResult($model);
        return $model;
    }

    /**
     * init Rbac
     *
     * @param MvcEvent $e
     */
    public function initRbac(MvcEvent $e)
    {
        $sm = $e->getApplication()->getServiceManager();
        $config = $sm->get('Config');

        $globalPermissions = isset($config['permissions'])?$config['permissions']:array();

        $rolesPermissions = isset($config['roles'])?$config['roles']:array();

        $rbac = new Rbac();
        foreach ($rolesPermissions as $role => $permissions) {

            $role = new Role($role);

            //global permissions
            foreach($globalPermissions as $globalPermission) {
                if (! $role->hasPermission($globalPermission)) {
                    $role->addPermission($globalPermission);
                }
            }

            //role permissions
            foreach ($permissions as $permission) {
                if (! $role->hasPermission($permission)) {
                    $role->addPermission($permission);
                }
            }

            $rbac->addRole($role);
        }

        //add role for guest (user not logged)
        $role = new Role('guest');
        foreach($globalPermissions as $globalPermission) {
            if (! $role->hasPermission($globalPermission)) {
                $role->addPermission($globalPermission);
            }
        }
        $rbac->addRole($role);

        //setting to view
        $e->getViewModel()->rbac = $rbac;
    }

    /**
     * Check Rbac
     *
     * @param MvcEvent $e
     * @return \Laminas\Stdlib\ResponseInterface
     */
    public function checkRbac(MvcEvent $e)
    {
        $route = $e->getRouteMatch()->getMatchedRouteName();
        $sm = $e->getApplication()->getServiceManager();

        /** @var ConnectedUserService $connectedUserService */
        $connectedUserService = $sm->get(ConnectedUserService::class);
        $connectedUser = $connectedUserService->getConnectedUser();

        $roles[] = 'guest';
        if ($connectedUser !== null) {
            $roles = $connectedUser->getRoles();
        }

        $isGranted = false;
        foreach($roles as $role) {
            if ($e->getViewModel()->rbac->isGranted($role, $route)) {
                $id = (int)$e->getRouteMatch()->getParam('id');
                if(strpos($route, 'monarc_api_global_client_anr/') === 0 || ($route == 'monarc_api_client_anr' && !empty($id))){
                    if($route == 'monarc_api_client_anr') {
                        $anrid = $id;
                    }else{
                        $anrid = (int)$e->getRouteMatch()->getParam('anrid');
                    }
                    if(empty($anrid)){
                        break; // pas besoin d'aller plus loin
                    }else{

                        $result = $this->validateAnrStatusAndGetResponseIfInvalid($anrid, $e, $route);
                        if ($result !== null) {
                            return $result;
                        }

                        $lk = current($sm->get(UserAnrTable::class)->getEntityByFields(['anr'=>$anrid,'user'=>$connectedUser->getId()]));
                        if(empty($lk)){
                            // On doit tester si c'est un snapshot, dans ce cas, on autorise l'accès mais en READ-ONLY
                            if($e->getRequest()->getMethod() != 'GET' && !$this->authorizedPost($route,$e->getRequest()->getMethod())){
                                break; // même si c'est un snapshot, on n'autorise que du GET
                            }
                            $snap = current($sm->get(SnapshotTable::class)->getEntityByFields(['anr'=>$anrid]));
                            if(empty($snap)){
                                break; // ce n'est pas un snapshot
                            }
                            $lk = current($sm->get(UserAnrTable::class)->getEntityByFields(['anr'=>$snap->get('anrReference')->get('id'),'user'=>$connectedUser->getId()]));
                            if(empty($lk)){
                                break; // l'user n'avait de toute façon pas accès à l'anr dont est issue ce snapshot
                            }
                            $isGranted = true;
                            break;
                        }elseif($lk->get('rwd') == 0 && $e->getRequest()->getMethod() != 'GET'){
                            if($this->authorizedPost($route,$e->getRequest()->getMethod())){ // on autorise les POST pour les export
                                $isGranted = true;
                            }
                            break; // les droits ne sont pas bon
                        }else{
                            $isGranted = true;
                            break;
                        }
                    }
                }else{
                    $isGranted = true;
                    break; // pas besoin d'aller plus loin
                }
            }
        }

        if (!$isGranted) {
            $response = $e->getResponse();
            $response->setStatusCode($connectedUser === null ? 401 : 403);

            return $response;
        }
    }

    private function authorizedPost($route, $method)
    {
        return $method === 'POST' &&
                ($route === 'monarc_api_global_client_anr/export' || // export ANR
                $route === 'monarc_api_global_client_anr/instance_export' || // export Instance
                $route === 'monarc_api_global_client_anr/objects_export' || // export  Object
                $route === 'monarc_api_global_client_anr/deliverable'); // generate a report
    }

    /**
     * Validates the anr status for NON GET method requests exclude DELETE (cancelation of background import).
     */
    private function validateAnrStatusAndGetResponseIfInvalid(
        int $anrId,
        MvcEvent $e,
        string $route
    ): ?ResponseInterface {
        if ($e->getRequest()->getMethod() === 'GET'
            || (
                $e->getRequest()->getMethod() === 'DELETE'
                && $route === 'monarc_api_global_client_anr/instance_import'
            )
        ) {
            return null;
        }

        $sm = $e->getApplication()->getServiceManager();

        /** @var Anr $anr */
        $anr = $sm->get(AnrTable::class)->findById($anrId);
        if ($anr->isActive()) {
            return null;
        }

        $result = [
            'status' => $anr->getStatus(),
            'importStatus' => [],
        ];
        /** @var CronTaskService $cronTaskService */
        $cronTaskService = $sm->get(CronTaskService::class);

        if ($anr->getStatus() === AnrSuperClass::STATUS_UNDER_IMPORT) {
            $importCronTask = $cronTaskService->getLatestTaskByNameWithParam(
                CronTask::NAME_INSTANCE_IMPORT,
                ['anrId' => $anrId]
            );
            if ($importCronTask !== null && $importCronTask->getStatus() === CronTask::STATUS_IN_PROGRESS) {
                /** @var InstanceTable $instanceTable */
                $instanceTable = $sm->get(InstanceTable::class);
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

        $response = $e->getResponse();
        $response->setContent(json_encode($result, JSON_THROW_ON_ERROR));
        $response->setStatusCode(409);

        return $response;
    }
}
