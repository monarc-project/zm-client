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
        $eventManager = $e->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);

        $this->initRbac($e);

        $eventManager->attach(MvcEvent::EVENT_ROUTE, array($this, 'checkRbac'), 0);
        $eventManager->attach(MvcEvent::EVENT_DISPATCH_ERROR, array($this, 'onDispatchError'), 0);
        $eventManager->attach(MvcEvent::EVENT_RENDER_ERROR, array($this, 'onRenderError'), 0);
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getAutoloaderConfig()
    {
        return array(
            // ./vendor/bin/classmap_generator.php --library module/MonarcBO/src/MonarcBO -w -s -o module/MonarcBO/autoload_classmap.php
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
                '\MonarcCli\Model\Db' => '\MonarcFO\Service\Model\DbCliFactory',

                //tables
                '\MonarcFO\Model\Table\AmvTable' => '\MonarcFO\Service\Model\Table\AmvServiceModelTable',
                '\MonarcFO\Model\Table\AnrTable' => '\MonarcFO\Service\Model\Table\AnrServiceModelTable',
                '\MonarcFO\Model\Table\AssetTable' => '\MonarcFO\Service\Model\Table\AssetServiceModelTable',
                '\MonarcFO\Model\Table\MeasureTable' => '\MonarcFO\Service\Model\Table\MeasureServiceModelTable',
                '\MonarcFO\Model\Table\ObjectTable' => '\MonarcFO\Service\Model\Table\ObjectServiceModelTable',
                '\MonarcFO\Model\Table\RolfCategoryTable' => '\MonarcFO\Service\Model\Table\RolfCategoryServiceModelTable',
                '\MonarcFO\Model\Table\RolfRiskTable' => '\MonarcFO\Service\Model\Table\RolfRiskServiceModelTable',
                '\MonarcFO\Model\Table\RolfTagTable' => '\MonarcFO\Service\Model\Table\RolfTagServiceModelTable',
                '\MonarcFO\Model\Table\ThreatTable' => '\MonarcFO\Service\Model\Table\ThreatServiceModelTable',
                '\MonarcFO\Model\Table\VulnerabilityTable' => '\MonarcFO\Service\Model\Table\VulnerabilityServiceModelTable',

                //entities
                '\MonarcFO\Model\Entity\Amv' => '\MonarcFO\Service\Model\Entity\AmvServiceModelEntity',
                '\MonarcFO\Model\Entity\Anr' => '\MonarcFO\Service\Model\Entity\AnrServiceModelEntity',
                '\MonarcFO\Model\Entity\Asset' => '\MonarcFO\Service\Model\Entity\AssetServiceModelEntity',
                '\MonarcFO\Model\Entity\Measure' => '\MonarcFO\Service\Model\Entity\MeasureServiceModelEntity',
                '\MonarcFO\Model\Entity\Object' => '\MonarcFO\Service\Model\Entity\ObjectServiceModelEntity',
                '\MonarcFO\Model\Entity\RolfCategory' => '\MonarcFO\Service\Model\Entity\RolfCategoryServiceModelEntity',
                '\MonarcFO\Model\Entity\RolfRisk' => '\MonarcFO\Service\Model\Entity\RolfRiskServiceModelEntity',
                '\MonarcFO\Model\Entity\RolfTag' => '\MonarcFO\Service\Model\Entity\RolfTagServiceModelEntity',
                '\MonarcFO\Model\Entity\Threat' => '\MonarcFO\Service\Model\Entity\ThreatServiceModelEntity',
                '\MonarcFO\Model\Entity\Vulnerability' => '\MonarcFO\Service\Model\Entity\VulnerabilityServiceModelEntity',

                //services
                '\MonarcFO\Service\AnrService' => '\MonarcFO\Service\AnrServiceFactory',
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
        foreach($userRoles as $userRole) {
            $roles[] = $userRole['role'];
        }

        if (empty($roles)) {
            $roles[] = 'guest';
        }

        $isGranted = false;
        foreach($roles as $role) {
            if ($e->getViewModel()->rbac->isGranted($role, $route)) {
                $isGranted = true;
            }
        }

        if (! $isGranted) {
            $response = $e->getResponse();
            $response->setStatusCode(401);

            return $response;
        }

    }

}
