<?php
namespace MonarcFO;

use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\Permissions\Rbac\Rbac;
use Zend\Permissions\Rbac\Role;
use Zend\View\Model\JsonModel;

class Module
{
    public function onBootstrap(MvcEvent $e)
    {
        if(!$e->getRequest() instanceof \Zend\Console\Request){
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

    public function getAutoloaderConfig()
    {
        return array(
            // ./vendor/bin/classmap_generator.php --library module/MonarcFO/src/MonarcFO -w -s -o module/MonarcFO/autoload_classmap.php
            'Zend\Loader\ClassMapAutoloader' => array(
                __DIR__ . '/autoload_classmap.php',
            ),
            /*'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),*/
        );
    }

    public function getServiceConfig()
    {
        return array(
            'invokables' => array(
            ),
            'factories' => array(
            ),
        );
    }


    public function getValidatorConfig(){
        return array(
            'invokables' => array(
            ),
        );
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
     * @return \Zend\Stdlib\ResponseInterface
     */
    public function checkRbac(MvcEvent $e) {
        $route = $e->getRouteMatch()->getMatchedRouteName();

        //retrieve connected user
        $sm = $e->getApplication()->getServiceManager();
        $connectedUserService = $sm->get('\MonarcCore\Service\ConnectedUserService');
        $connectedUser = $connectedUserService->getConnectedUser();

        //retrieve user roles
        $userRoleService = $sm->get('\MonarcCore\Service\UserRoleService');
        $userRoles = $userRoleService->getList(1, 25, null, $connectedUser['id']);

        $roles = [];
        $isSuperAdmin = false;
        foreach($userRoles as $userRole) {
            $roles[] = $userRole['role'];
            if ($userRole['role'] == 'superadminfo') {
                $isSuperAdmin = true;
            }
        }

        if (empty($roles)) {
            $roles[] = 'guest';
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
                        $lk = current($sm->get('MonarcFO\Model\Table\UserAnrTable')->getEntityByFields(['anr'=>$anrid,'user'=>$connectedUser['id']]));
                        if(empty($lk)){
                            // On doit tester si c'est un snapshot, dans ce cas, on autorise l'accès mais en READ-ONLY
                            if($e->getRequest()->getMethod() != 'GET' && !$this->authorizedPost($route,$e->getRequest()->getMethod())){
                                break; // même si c'est un snapshot, on n'autorise que du GET
                            }
                            $snap = current($sm->get('MonarcFO\Model\Table\SnapshotTable')->getEntityByFields(['anr'=>$anrid]));
                            if(empty($snap)){
                                break; // ce n'est pas un snapshot
                            }
                            $lk = current($sm->get('MonarcFO\Model\Table\UserAnrTable')->getEntityByFields(['anr'=>$snap->get('anrReference')->get('id'),'user'=>$connectedUser['id']]));
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

        if (! $isGranted) {
            $response = $e->getResponse();
            $response->setStatusCode(401);

            return $response;
        }

    }
    private function authorizedPost($route, $method){
        return $method == 'POST' &&
                ($route == 'monarc_api_global_client_anr/export' || // export ANR
                $route == 'monarc_api_global_client_anr/instance_export' || // export Instance
                $route == 'monarc_api_global_client_anr/objects_export' || // export  Object
                $route == 'monarc_api_global_client_anr/deliverable'); // generate a report
    }
}
