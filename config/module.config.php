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

            'monarc_api_admin_users_rights' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/users-rights[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAdminUsersRights',
                    ),
                ),
            ),

            'monarc_api_client_snapshot' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client-snapshot[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiSnapshot',
                    ),
                ),
            ),

            'monarc_api_client_snapshot_restore' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client-restore-snapshot',
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiSnapshotRestore',
                    ),
                ),
            ),

            'monarc_api_admin_users' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/users[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAdminUsers',
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

            'monarc_api_user_profile' => array(
                'type' => 'literal',
                'options' => array(
                    'route' => '/api/user/profile',
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiUserProfile',
                    ),
                ),
            ),
            'monarc_api_admin_passwords' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/admin/passwords',
                    'constraints' => array(),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAdminPasswords',
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

            'monarc_api_global_client_anr' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client-anr/:anrid/',
                    'constraints' => array(
                        'anrid' => '[0-9]+',
                    ),
                ),
                'may_terminate' => false,
                'child_routes' => array(
                    'assets' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'assets[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrAssets',
                            ),
                        ),
                    ),
                    'assets_import' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'assets/import',
                            'constraints' => array(
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrAssetsImport',
                            ),
                        ),
                    ),
                    'assets_import_common' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'assets/importcomm[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrAssetsImportCommon',
                            ),
                        ),
                    ),
                    'amvs' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'amvs[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrAmvs',
                            ),
                        ),
                    ),
                    'measures' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'measures[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrMeasures',
                            ),
                        ),
                    ),
                    'threats' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'threats[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrThreats',
                            ),
                        ),
                    ),
                    'themes' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'themes[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrThemes',
                            ),
                        ),
                    ),
                    'vulnerabilities' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'vulnerabilities[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrVulnerabilities',
                            ),
                        ),
                    ),
                    'rolf_tags' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'rolf-tags[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrRolfTags',
                            ),
                        ),
                    ),
                    'rolf_categories' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'rolf-categories[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrRolfCategories',
                            ),
                        ),
                    ),
                    'rolf_risks' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'rolf-risks[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrRolfRisks',
                            ),
                        ),
                    ),
                    'objects' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'objects[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrObjects',
                            ),
                        ),
                    ),
                    'interviews' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'interviews[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrInterviews',
                            ),
                        ),
                    ),
                    'scales' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'scales[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrScales',
                            ),
                        ),
                    ),
                    'scales_types' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'scales-types[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrScalesTypes',
                            ),
                        ),
                    ),
                    'scales_comments' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'scales/:scaleid/comments[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                                'scaleid' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrScalesComments',
                            ),
                        ),
                    ),
                    'questions' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'questions[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrQuestions',
                            ),
                        ),
                    ),
                    'questions_choices' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'questions-choices[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrQuestionsChoices',
                            ),
                        ),
                    ),
                    'recommandations' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'recommandations[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrRecommandations',
                            ),
                        ),
                    ),
                    'recommandations_risks' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'recommandations-risks[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrRecommandationsRisks',
                            ),
                        ),
                    ),
                    'recommandations_measures' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'recommandations-measures[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrRecommandationsMeasures',
                            ),
                        ),
                    ),
                    'carto_risks' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'carto-risks[/:type]',
                            'constraints' => array(
                                'type' => 'all|real|targeted',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrCartoRisks',
                                'type' => 'all',
                            ),
                        ),
                    ),
                    'risks' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'risks[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrRisks',
                            ),
                        ),
                    ),
                    'risks_op' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'risksop[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrRisksOp',
                            ),
                        ),
                    ),
                    'library' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'library[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrLibrary',
                            ),
                        ),
                    ),
                    'treatment_plan' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'treatment-plan[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrTreatmentPlan',
                            ),
                        ),
                    ),
                    'instance' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'instances[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrInstances',
                            ),
                        ),
                    ),
                    'objects_categories' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'objects-categories[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrObjectsCategories',
                            ),
                        ),
                    ),
                ),
            ),
        ),
    ),

    'controllers' => array(
        'invokables' => array(
        ),
        'factories' => array(
            '\MonarcFO\Controller\Index'                            => '\MonarcCore\Controller\IndexControllerFactory',
            '\MonarcFO\Controller\ApiAdminPasswords'                => '\MonarcFO\Controller\ApiAdminPasswordsControllerFactory',
            '\MonarcFO\Controller\ApiAdminRoles'                    => '\MonarcFO\Controller\ApiAdminRolesControllerFactory',
            '\MonarcFO\Controller\ApiAdminUsers'                    => '\MonarcFO\Controller\ApiAdminUsersControllerFactory',
            '\MonarcFO\Controller\ApiAdminUsersRoles'               => '\MonarcFO\Controller\ApiAdminUsersRolesControllerFactory',
            '\MonarcFO\Controller\ApiAdminUsersRights'              => '\MonarcFO\Controller\ApiAdminUsersRightsControllerFactory',
            '\MonarcFO\Controller\ApiAnr'                           => '\MonarcFO\Controller\ApiAnrControllerFactory',
            '\MonarcFO\Controller\ApiSnapshot'                      => '\MonarcFO\Controller\ApiSnapshotControllerFactory',
            '\MonarcFO\Controller\ApiSnapshotRestore'               => '\MonarcFO\Controller\ApiSnapshotRestoreControllerFactory',
            '\MonarcFO\Controller\ApiConfig'                        => '\MonarcFO\Controller\ApiConfigControllerFactory',
            '\MonarcFO\Controller\ApiDuplicateAnr'                  => '\MonarcFO\Controller\ApiDuplicateAnrControllerFactory',
            '\MonarcFO\Controller\ApiUserPassword'                  => '\MonarcFO\Controller\ApiUserPasswordControllerFactory',
            '\MonarcFO\Controller\ApiUserProfile'                   => '\MonarcFO\Controller\ApiUserProfileControllerFactory',
            '\MonarcFO\Controller\ApiAnrAssets'                     => '\MonarcFO\Controller\ApiAnrAssetsControllerFactory',
            '\MonarcFO\Controller\ApiAnrAmvs'                       => '\MonarcFO\Controller\ApiAnrAmvsControllerFactory',
            '\MonarcFO\Controller\ApiAnrMeasures'                   => '\MonarcFO\Controller\ApiAnrMeasuresControllerFactory',
            '\MonarcFO\Controller\ApiAnrObjects'                    => '\MonarcFO\Controller\ApiAnrObjectsControllerFactory',
            '\MonarcFO\Controller\ApiAnrQuestions'                  => '\MonarcFO\Controller\ApiAnrQuestionsControllerFactory',
            '\MonarcFO\Controller\ApiAnrQuestionsChoices'           => '\MonarcFO\Controller\ApiAnrQuestionsChoicesControllerFactory',
            '\MonarcFO\Controller\ApiAnrThreats'                    => '\MonarcFO\Controller\ApiAnrThreatsControllerFactory',
            '\MonarcFO\Controller\ApiAnrThemes'                     => '\MonarcFO\Controller\ApiAnrThemesControllerFactory',
            '\MonarcFO\Controller\ApiAnrVulnerabilities'            => '\MonarcFO\Controller\ApiAnrVulnerabilitiesControllerFactory',
            '\MonarcFO\Controller\ApiAnrRolfTags'                   => '\MonarcFO\Controller\ApiAnrRolfTagsControllerFactory',
            '\MonarcFO\Controller\ApiAnrRolfCategories'             => '\MonarcFO\Controller\ApiAnrRolfCategoriesControllerFactory',
            '\MonarcFO\Controller\ApiAnrRolfRisks'                  => '\MonarcFO\Controller\ApiAnrRolfRisksControllerFactory',
            '\MonarcFO\Controller\ApiAnrAssetsImport'               => '\MonarcFO\Controller\ApiAnrAssetsImportControllerFactory',
            '\MonarcFO\Controller\ApiAnrAssetsImportCommon'         => '\MonarcFO\Controller\ApiAnrAssetsImportCommonControllerFactory',
            '\MonarcFO\Controller\ApiAnrInterviews'                 => '\MonarcFO\Controller\ApiAnrInterviewsControllerFactory',
            '\MonarcFO\Controller\ApiAnrRecommandations'            => '\MonarcFO\Controller\ApiAnrRecommandationsControllerFactory',
            '\MonarcFO\Controller\ApiAnrRecommandationsRisks'       => '\MonarcFO\Controller\ApiAnrRecommandationsRisksControllerFactory',
            '\MonarcFO\Controller\ApiAnrRecommandationsMeasures'    => '\MonarcFO\Controller\ApiAnrRecommandationsMeasuresControllerFactory',
            '\MonarcFO\Controller\ApiAnrTreatmentPlan'              => '\MonarcFO\Controller\ApiAnrTreatmentPlanControllerFactory',
            '\MonarcFO\Controller\ApiAnrScales'                     => '\MonarcFO\Controller\ApiAnrScalesControllerFactory',
            '\MonarcFO\Controller\ApiAnrScalesTypes'                => '\MonarcFO\Controller\ApiAnrScalesTypesControllerFactory',
            '\MonarcFO\Controller\ApiAnrScalesComments'             => '\MonarcFO\Controller\ApiAnrScalesCommentsControllerFactory',
            '\MonarcFO\Controller\ApiAnrCartoRisks'                 => '\MonarcFO\Controller\ApiAnrCartoRisksControllerFactory',
            '\MonarcFO\Controller\ApiAnrRisks'                      => '\MonarcFO\Controller\ApiAnrRisksControllerFactory',
            '\MonarcFO\Controller\ApiAnrRisksOp'                    => '\MonarcFO\Controller\ApiAnrRisksOpControllerFactory',
            '\MonarcFO\Controller\ApiAnrLibrary'                    => '\MonarcFO\Controller\ApiAnrLibraryControllerFactory',
            '\MonarcFO\Controller\ApiAnrInstances'                  => '\MonarcFO\Controller\ApiAnrInstancesControllerFactory',
            '\MonarcFO\Controller\ApiAnrObjectsCategories'          => '\MonarcFO\Controller\ApiAnrObjectsCategoriesControllerFactory',
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
                    __DIR__ . '/../../MonarcCore/src/MonarcCore/Model/Entity',
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
            'monarc_api_admin_users_rights',
            'monarc_api_user_password',
            'monarc_api_user_profile',
            'monarc_api_global_client_anr/instance',
            'monarc_api_anr_instances_consequences',
            'monarc_api_global_client_anr/interviews',
            'monarc_api_global_client_anr/library',
            'monarc_api_anr_objects',
            'monarc_api_global_client_anr/questions',
            'monarc_api_global_client_anr/questions_choices',
            'monarc_api_global_client_anr/risks',
            'monarc_api_global_client_anr/risks_op',
            'monarc_api_global_client_anr/amvs',
            'monarc_api_client_anr',
            'monarc_api_global_client_anr/assets',
            'monarc_api_global_client_anr/assets_import',
            'monarc_api_global_client_anr/assets_import_common',
            'monarc_api_global_client_anr/measures',
            'monarc_api_global_client_anr/objects',
            'monarc_api_global_client_anr/rolf_categories',
            'monarc_api_global_client_anr/rolf_risks',
            'monarc_api_global_client_anr/tags',
            'monarc_api_client_snapshot',
            'monarc_api_client_snapshot_restore',
            'monarc_api_global_client_anr/themes',
            'monarc_api_global_client_anr/threats',
            'monarc_api_global_client_anr/vulnerabilities',
            'monarc_api_duplicate_client_anr',
            'monarc_api_models',
            'monarc_api_scales',
            'monarc_api_scales_comments',
            'monarc_api_scales_types',
            'monarc_api_user_profile',
            'monarc_api_global_client_anr/carto_risks',
            'monarc_api_global_client_anr/scales',
            'monarc_api_global_client_anr/scales_types',
            'monarc_api_global_client_anr/scales_comments',
            'monarc_api_global_client_anr/recommandations',
            'monarc_api_global_client_anr/recommandations_risks',
            'monarc_api_global_client_anr/recommandations_measures',
            'monarc_api_global_client_anr/treatment_plan',
            'monarc_api_global_client_anr/objects_categories',
        ),
        // Utilisateur : Accès RWD par analyse
        'userfo'=> array(
            'monarc_api_admin_users_roles',
            'monarc_api_global_client_anr/instance',
            'monarc_api_anr_instances_consequences',
            'monarc_api_global_client_anr/interviews',
            'monarc_api_global_client_anr/library',
            'monarc_api_anr_objects',
            'monarc_api_global_client_anr/questions',
            'monarc_api_global_client_anr/questions_choices',
            'monarc_api_global_client_anr/risks',
            'monarc_api_global_client_anr/risks_op',
            'monarc_api_global_client_anr/amvs',
            'monarc_api_client_anr',
            'monarc_api_global_client_anr/assets',
            'monarc_api_global_client_anr/assets_import',
            'monarc_api_global_client_anr/assets_import_common',
            'monarc_api_global_client_anr/measures',
            'monarc_api_global_client_anr/objects',
            'monarc_api_global_client_anr/rolf_categories',
            'monarc_api_global_client_anr/rolf_risks',
            'monarc_api_global_client_anr/tags',
            'monarc_api_client_snapshot',
            'monarc_api_client_snapshot_restore',
            'monarc_api_global_client_anr/themes',
            'monarc_api_global_client_anr/threats',
            'monarc_api_global_client_anr/vulnerabilities',
            'monarc_api_duplicate_client_anr',
            'monarc_api_models',
            'monarc_api_scales',
            'monarc_api_scales_comments',
            'monarc_api_scales_types',
            'monarc_api_user_profile',
            'monarc_api_global_client_anr/carto_risks',
            'monarc_api_global_client_anr/scales',
            'monarc_api_global_client_anr/scales_types',
            'monarc_api_global_client_anr/scales_comments',
            'monarc_api_global_client_anr/recommandations',
            'monarc_api_global_client_anr/recommandations_risks',
            'monarc_api_global_client_anr/recommandations_measures',
            'monarc_api_global_client_anr/treatment_plan',
            'monarc_api_global_client_anr/objects_categories',
        ),
    )
);
