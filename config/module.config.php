<?php
namespace MonarcFO;

return array(
    'router' => array(
        'routes' => array(
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
        ),
    ),

    'controllers' => array(
        'invokables' => array(
        ),
        'factories' => array(
            '\MonarcBO\Controller\ApiUserPassword'          => '\MonarcBO\Controller\ApiUserPasswordControllerFactory',
            '\MonarcBO\Controller\ApiAdminPasswords'        => '\MonarcBO\Controller\ApiAdminPasswordsControllerFactory',
            '\MonarcBO\Controller\ApiAdminRoles'            => '\MonarcBO\Controller\ApiAdminRolesControllerFactory',
            '\MonarcBO\Controller\ApiAdminUsers'            => '\MonarcBO\Controller\ApiAdminUsersControllerFactory',
            '\MonarcBO\Controller\ApiAdminUsersRoles'       => '\MonarcBO\Controller\ApiAdminUsersRolesControllerFactory',
            '\MonarcBO\Controller\ApiUserProfile'           => '\MonarcBO\Controller\ApiUserProfileControllerFactory',
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
        ),
        // Utilisateur réduit : Accès consultation uniquement
        'userminfo'=> array(
            'monarc_api_user_profile',
        ),
    )
);
