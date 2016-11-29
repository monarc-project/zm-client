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
                '\MonarcCli\Model\Db' => '\MonarcFO\Service\Model\DbCliFactory',

                //tables
                '\MonarcFO\Model\Table\AmvTable' => '\MonarcFO\Service\Model\Table\AmvServiceModelTable',
                '\MonarcFO\Model\Table\AnrObjectCategoryTable' => '\MonarcFO\Service\Model\Table\AnrObjectCategoryServiceModelTable',
                '\MonarcFO\Model\Table\AnrTable' => '\MonarcFO\Service\Model\Table\AnrServiceModelTable',
                '\MonarcFO\Model\Table\AssetTable' => '\MonarcFO\Service\Model\Table\AssetServiceModelTable',
                '\MonarcFO\Model\Table\InstanceTable' => '\MonarcFO\Service\Model\Table\InstanceServiceModelTable',
                '\MonarcFO\Model\Table\InstanceConsequenceTable' => '\MonarcFO\Service\Model\Table\InstanceConsequenceServiceModelTable',
                '\MonarcFO\Model\Table\InstanceRiskTable' => '\MonarcFO\Service\Model\Table\InstanceRiskServiceModelTable',
                '\MonarcFO\Model\Table\InstanceRiskOpTable' => '\MonarcFO\Service\Model\Table\InstanceRiskOpServiceModelTable',
                '\MonarcFO\Model\Table\InterviewTable' => '\MonarcFO\Service\Model\Table\InterviewServiceModelTable',
                '\MonarcFO\Model\Table\MeasureTable' => '\MonarcFO\Service\Model\Table\MeasureServiceModelTable',
                '\MonarcFO\Model\Table\ObjectTable' => '\MonarcFO\Service\Model\Table\ObjectServiceModelTable',
                '\MonarcFO\Model\Table\ObjectCategoryTable' => '\MonarcFO\Service\Model\Table\ObjectCategoryServiceModelTable',
                '\MonarcFO\Model\Table\ObjectObjectTable' => '\MonarcFO\Service\Model\Table\ObjectObjectServiceModelTable',
                '\MonarcFO\Model\Table\PasswordTokenTable' => '\MonarcFO\Service\Model\Table\PasswordTokenServiceModelTable',
                '\MonarcFO\Model\Table\RolfCategoryTable' => '\MonarcFO\Service\Model\Table\RolfCategoryServiceModelTable',
                '\MonarcFO\Model\Table\RolfRiskTable' => '\MonarcFO\Service\Model\Table\RolfRiskServiceModelTable',
                '\MonarcFO\Model\Table\RolfTagTable' => '\MonarcFO\Service\Model\Table\RolfTagServiceModelTable',
                '\MonarcFO\Model\Table\ScaleTable' => '\MonarcFO\Service\Model\Table\ScaleServiceModelTable',
                '\MonarcFO\Model\Table\ScaleCommentTable' => '\MonarcFO\Service\Model\Table\ScaleCommentServiceModelTable',
                '\MonarcFO\Model\Table\ScaleImpactTypeTable' => '\MonarcFO\Service\Model\Table\ScaleImpactTypeServiceModelTable',
                '\MonarcFO\Model\Table\ThemeTable' => '\MonarcFO\Service\Model\Table\ThemeServiceModelTable',
                '\MonarcFO\Model\Table\ThreatTable' => '\MonarcFO\Service\Model\Table\ThreatServiceModelTable',
                '\MonarcFO\Model\Table\UserTable' => '\MonarcFO\Service\Model\Table\UserServiceModelTable',
                '\MonarcFO\Model\Table\UserAnrTable' => '\MonarcFO\Service\Model\Table\UserAnrServiceModelTable',
                '\MonarcFO\Model\Table\UserRoleTable' => '\MonarcFO\Service\Model\Table\UserRoleServiceModelTable',
                '\MonarcFO\Model\Table\VulnerabilityTable' => '\MonarcFO\Service\Model\Table\VulnerabilityServiceModelTable',

                //entities
                '\MonarcFO\Model\Entity\Amv' => '\MonarcFO\Service\Model\Entity\AmvServiceModelEntity',
                '\MonarcFO\Model\Entity\Anr' => '\MonarcFO\Service\Model\Entity\AnrServiceModelEntity',
                '\MonarcFO\Model\Entity\AnrObjectCategory' => '\MonarcFO\Service\Model\Entity\AnrObjectCategoryServiceModelEntity',
                '\MonarcFO\Model\Entity\Asset' => '\MonarcFO\Service\Model\Entity\AssetServiceModelEntity',
                '\MonarcFO\Model\Entity\Instance' => '\MonarcFO\Service\Model\Entity\InstanceServiceModelEntity',
                '\MonarcFO\Model\Entity\InstanceConsequence' => '\MonarcFO\Service\Model\Entity\InstanceConsequenceServiceModelEntity',
                '\MonarcFO\Model\Entity\InstanceRisk' => '\MonarcFO\Service\Model\Entity\InstanceRiskServiceModelEntity',
                '\MonarcFO\Model\Entity\InstanceRiskOp' => '\MonarcFO\Service\Model\Entity\InstanceRiskOpServiceModelEntity',
                '\MonarcFO\Model\Entity\Interview' => '\MonarcFO\Service\Model\Entity\InterviewServiceModelEntity',
                '\MonarcFO\Model\Entity\Measure' => '\MonarcFO\Service\Model\Entity\MeasureServiceModelEntity',
                '\MonarcFO\Model\Entity\Object' => '\MonarcFO\Service\Model\Entity\ObjectServiceModelEntity',
                '\MonarcFO\Model\Entity\ObjectCategory' => '\MonarcFO\Service\Model\Entity\ObjectCategoryServiceModelEntity',
                '\MonarcFO\Model\Entity\ObjectObject' => '\MonarcFO\Service\Model\Entity\ObjectObjectServiceModelEntity',
                '\MonarcFO\Model\Entity\PasswordToken' => '\MonarcFO\Service\Model\Entity\PasswordTokenServiceModelEntity',
                '\MonarcFO\Model\Entity\RolfCategory' => '\MonarcFO\Service\Model\Entity\RolfCategoryServiceModelEntity',
                '\MonarcFO\Model\Entity\RolfRisk' => '\MonarcFO\Service\Model\Entity\RolfRiskServiceModelEntity',
                '\MonarcFO\Model\Entity\RolfTag' => '\MonarcFO\Service\Model\Entity\RolfTagServiceModelEntity',
                '\MonarcFO\Model\Entity\Scale' => '\MonarcFO\Service\Model\Entity\ScaleServiceModelEntity',
                '\MonarcFO\Model\Entity\ScaleComment' => '\MonarcFO\Service\Model\Entity\ScaleCommentServiceModelEntity',
                '\MonarcFO\Model\Entity\ScaleImpactType' => '\MonarcFO\Service\Model\Entity\ScaleImpactTypeServiceModelEntity',
                '\MonarcFO\Model\Entity\Theme' => '\MonarcFO\Service\Model\Entity\ThemeServiceModelEntity',
                '\MonarcFO\Model\Entity\Threat' => '\MonarcFO\Service\Model\Entity\ThreatServiceModelEntity',
                '\MonarcFO\Model\Entity\User' => '\MonarcFO\Service\Model\Entity\UserServiceModelEntity',
                '\MonarcFO\Model\Entity\UserAnr' => '\MonarcFO\Service\Model\Entity\UserAnrServiceModelEntity',
                '\MonarcFO\Model\Entity\UserRole' => '\MonarcFO\Service\Model\Entity\UserRoleServiceModelEntity',
                '\MonarcFO\Model\Entity\Vulnerability' => '\MonarcFO\Service\Model\Entity\VulnerabilityServiceModelEntity',

                //services
                '\MonarcFO\Service\AnrService' => '\MonarcFO\Service\AnrServiceFactory',
                '\MonarcFO\Service\UserService' => '\MonarcFO\Service\UserServiceFactory',
                '\MonarcFO\Service\UserAnrService' => '\MonarcFO\Service\UserAnrServiceFactory',
                '\MonarcFO\Service\UserRoleService' => '\MonarcFO\Service\UserRoleServiceFactory',
                '\MonarcFO\Service\AnrAssetService' => '\MonarcFO\Service\AnrAssetServiceFactory',
                '\MonarcFO\Service\AnrAssetCommonService' => '\MonarcFO\Service\AnrAssetCommonServiceFactory',
                '\MonarcFO\Service\AnrAmvService' => '\MonarcFO\Service\AnrAmvServiceFactory',
                '\MonarcFO\Service\AnrInterviewService' => '\MonarcFO\Service\AnrInterviewServiceFactory',
                '\MonarcFO\Service\AnrMeasureService' => '\MonarcFO\Service\AnrMeasureServiceFactory',
                '\MonarcFO\Service\AnrThreatService' => '\MonarcFO\Service\AnrThreatServiceFactory',
                '\MonarcFO\Service\AnrThemeService' => '\MonarcFO\Service\AnrThemeServiceFactory',
                '\MonarcFO\Service\AnrVulnerabilityService' => '\MonarcFO\Service\AnrVulnerabilityServiceFactory',
                '\MonarcFO\Service\AnrRolfTagService' => '\MonarcFO\Service\AnrRolfTagServiceFactory',
                '\MonarcFO\Service\AnrRolfCategoryService' => '\MonarcFO\Service\AnrRolfCategoryServiceFactory',
                '\MonarcFO\Service\AnrRolfRiskService' => '\MonarcFO\Service\AnrRolfRiskServiceFactory',
                '\MonarcFO\Service\AmvService' => '\MonarcFO\Service\AmvServiceFactory',
                '\MonarcFO\Service\AssetService' => '\MonarcFO\Service\AssetServiceFactory',
                '\MonarcFO\Service\ObjectService' => '\MonarcFO\Service\ObjectServiceFactory',
                '\MonarcFO\Service\ObjectObjectService' => '\MonarcFO\Service\ObjectObjectServiceFactory',
                '\MonarcFO\Service\PasswordService' => '\MonarcFO\Service\PasswordServiceFactory',
                '\MonarcFO\Service\MailService' => '\MonarcFO\Service\MailServiceFactory',
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
