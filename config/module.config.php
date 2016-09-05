<?php
namespace MonarcFO;

return array(
    'router' => array(
        'routes' => array(
            'home' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route' => '/',
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\Index',
                        'action' => 'index',
                    ),
                ),
            ),

            'monarc_api_user_profile' => array(
                'type' => 'literal',
                'options' => array(
                    'route' => '/api/user/profile',
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiUserProfile',
                    ),
                ),
            ),
            'monarc_api_admin_users' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/admin/users[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAdminUsers',
                    ),
                ),
            ),
            'monarc_api_admin_users_roles' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/users-roles[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAdminUsersRoles',
                    ),
                ),
            ),
            'monarc_api_admin_roles' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/admin/roles[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcBO\Controller\ApiAdminRoles',
                    ),
                ),
            ),
            'monarc_api_admin_passwords' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/admin/passwords',
                    'constraints' => array(
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAdminPasswords',
                    ),
                ),
            ),

            'monarc_api_user_password' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/user/password/:id',
                    'constraints' => array(
                        'id' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiUserPassword',
                    ),
                ),
            ),

            'monarc_api_config' => array(
                'type' => 'literal',
                'options' => array(
                    'route' => '/api/config',
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiConfig',
                    ),
                ),
            ),


            'monarc_api_anr' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/anr[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcCore\Controller\ApiAnr',
                    ),
                ),
            ),

            'monarc_api_anr_library' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/anr/:anrid/library[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcCore\Controller\ApiAnrLibrary',
                    ),
                ),
            ),

            'monarc_api_anr_instances' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/anr/:anrid/instances[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcCore\Controller\ApiAnrInstances',
                    ),
                ),
            ),

            'monarc_api_anr_instances_risks' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/anr/:anrid/instances-risks[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcCore\Controller\ApiAnrInstancesRisks',
                    ),
                ),
            ),

            'monarc_api_anr_instances_consequences' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/anr/:anrid/instances-consequences[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcCore\Controller\ApiAnrInstancesConsequences',
                    ),
                ),
            ),

            'monarc_api_anr_instances_risksop' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/anr/:anrid/instances-oprisks[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcCore\Controller\ApiAnrInstancesRisksOp',
                    ),
                ),
            ),

            'monarc_api_models' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/models[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcCore\Controller\ApiModels',
                    ),
                ),
            ),
        ),
    ),

    'controllers' => array(
        'invokables' => array(
        ),
        'factories' => array(
            '\MonarcFO\Controller\Index'                    => '\MonarcCore\Controller\IndexControllerFactory',
            '\MonarcFO\Controller\ApiUserPassword'          => '\MonarcFO\Controller\ApiUserPasswordControllerFactory',
            '\MonarcFO\Controller\ApiAdminPasswords'        => '\MonarcFO\Controller\ApiAdminPasswordsControllerFactory',
            '\MonarcFO\Controller\ApiAdminRoles'            => '\MonarcFO\Controller\ApiAdminRolesControllerFactory',
            '\MonarcFO\Controller\ApiAdminUsers'            => '\MonarcFO\Controller\ApiAdminUsersControllerFactory',
            '\MonarcFO\Controller\ApiAdminUsersRoles'       => '\MonarcFO\Controller\ApiAdminUsersRolesControllerFactory',
            '\MonarcFO\Controller\ApiUserProfile'           => '\MonarcFO\Controller\ApiUserProfileControllerFactory',
            '\MonarcFO\Controller\ApiConfig'                => '\MonarcFO\Controller\ApiConfigControllerFactory',
        ),
    ),

    'view_manager' => array(
        'display_not_found_reason' => true,
        'display_exceptions'       => true,
        'doctype'                  => 'HTML5',
        'not_found_template'       => 'error/404',
        'exception_template'       => 'error/index',
        'strategies' => array(
            'viewJsonStrategy'
        ),
        'template_map' => array(
            'monarc-fo/index/index' => __DIR__ . '/../view/layout/layout.phtml',
            'error/404' => __DIR__ . '/../view/layout/layout.phtml',
        ),
        'template_path_stack' => array(
            __DIR__ . '/../view',
        ),
    ),
    // Placeholder for console routes
    'console' => array(
        'router' => array(
            'routes' => array(
            ),
        ),
    ),
    'roles' => array(
        // Super Admin : Gestion des droits des utilisateurs uniquement (Carnet d’adresses)
        'superadminfo'=> array(
            'monarc_api_admin_users',
            'monarc_api_admin_users_roles',
            'monarc_api_user_password',
            'monarc_api_user_profile',
        ),
        // Utilisateur : Accès RWD par analyse
        'userfo'=> array(
            'monarc_api_user_profile',
            'monarc_api_anr_instances',
            'monarc_api_anr_instances_risks',
            'monarc_api_anr_instances_risksop',
            'monarc_api_anr_instances_consequences',
            'monarc_api_anr_instances',
            'monarc_api_anr_library',
        ),
        // Utilisateur réduit : Accès consultation uniquement
        'userminfo'=> array(
            'monarc_api_user_profile',
            'monarc_api_anr_instances',
            'monarc_api_anr_instances_risks',
            'monarc_api_anr_instances_risksop',
            'monarc_api_anr_instances_consequences',
            'monarc_api_anr_instances',
            'monarc_api_anr_library',
        ),
    )
);
