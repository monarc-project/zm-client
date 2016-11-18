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

            'monarc_api_client_anr' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client-anr[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAnr',
                    ),
                ),
            ),

            'monarc_api_duplicate_client_anr' => array(
                'type' => 'literal',
                'options' => array(
                    'route' => '/api/client-duplicate-anr',
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiDuplicateAnr',
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
        ),
    ),

    'controllers' => array(
        'invokables' => array(
        ),
        'factories' => array(
            '\MonarcFO\Controller\Index'                    => '\MonarcCore\Controller\IndexControllerFactory',
            '\MonarcFO\Controller\ApiAdminPasswords'        => '\MonarcFO\Controller\ApiAdminPasswordsControllerFactory',
            '\MonarcFO\Controller\ApiAdminRoles'            => '\MonarcFO\Controller\ApiAdminRolesControllerFactory',
            '\MonarcFO\Controller\ApiAdminUsers'            => '\MonarcFO\Controller\ApiAdminUsersControllerFactory',
            '\MonarcFO\Controller\ApiAdminUsersRoles'       => '\MonarcFO\Controller\ApiAdminUsersRolesControllerFactory',
            '\MonarcFO\Controller\ApiAnr'                   => '\MonarcFO\Controller\ApiAnrControllerFactory',
            '\MonarcFO\Controller\ApiConfig'                => '\MonarcFO\Controller\ApiConfigControllerFactory',
            '\MonarcFO\Controller\ApiDuplicateAnr'          => '\MonarcFO\Controller\ApiDuplicateAnrControllerFactory',
            '\MonarcFO\Controller\ApiUserPassword'          => '\MonarcFO\Controller\ApiUserPasswordControllerFactory',
            '\MonarcFO\Controller\ApiUserProfile'           => '\MonarcFO\Controller\ApiUserProfileControllerFactory',
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

    'doctrine' => array(
        'driver' => array(
            'Monarc_cli_driver' => array(
                'class' => 'Doctrine\ORM\Mapping\Driver\AnnotationDriver',
                'cache' => 'array',
                'paths' => array(
                    __DIR__ . '/../src/MonarcFO/Model/Entity',
                ),
            ),
            'orm_cli' => array(
                'class' => 'Doctrine\ORM\Mapping\Driver\DriverChain',
                'drivers' => array(
                    'MonarcFO\Model\Entity' => 'Monarc_cli_driver',
                ),
            ),
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
            'monarc_api_anr_risks',
            'monarc_api_anr_risks_op',
            'monarc_api_anr_instances',
            'monarc_api_anr_instances_risks',
            'monarc_api_anr_instances_risksop',
            'monarc_api_anr_instances_consequences',
            'monarc_api_anr_instances',
            'monarc_api_anr_library',
            'monarc_api_client_anr',
            'monarc_api_duplicate_client_anr',
            'monarc_api_models',
            'monarc_api_scales',
            'monarc_api_scales_comments',
            'monarc_api_scales_types',
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
            'monarc_api_scales',
            'monarc_api_scales_comments',
            'monarc_api_scales_types',
            'monarc_api_models',
        ),
    )
);
