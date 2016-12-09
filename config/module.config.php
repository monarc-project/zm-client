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

            'monarc_api_client_assets' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client-anr/:anrid/assets[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                        'anrid' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAnrAssets',
                    ),
                ),
            ),
            'monarc_api_client_assets_import' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client-anr/:anrid/assets/import',
                    'constraints' => array(
                        'anrid' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAnrAssetsImport',
                    ),
                ),
            ),
            'monarc_api_client_assets_import_common' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client-anr/:anrid/assets/importcomm[/:id]',
                    'constraints' => array(
                        'anrid' => '[0-9]+',
                        'id' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAnrAssetsImportCommon',
                    ),
                ),
            ),
            'monarc_api_client_amvs' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client-anr/:anrid/amvs[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                        'anrid' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAnrAmvs',
                    ),
                ),
            ),
            'monarc_api_client_measures' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client-anr/:anrid/measures[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                        'anrid' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAnrMeasures',
                    ),
                ),
            ),
            'monarc_api_client_threats' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client-anr/:anrid/threats[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                        'anrid' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAnrThreats',
                    ),
                ),
            ),
            'monarc_api_client_themes' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client-anr/:anrid/themes[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                        'anrid' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAnrThemes',
                    ),
                ),
            ),
            'monarc_api_client_vulnerabilities' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client-anr/:anrid/vulnerabilities[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                        'anrid' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAnrVulnerabilities',
                    ),
                ),
            ),
            'monarc_api_client_rolf_tags' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client-anr/:anrid/rolf-tags[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                        'anrid' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAnrRolfTags',
                    ),
                ),
            ),
            'monarc_api_client_rolf_categories' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client-anr/:anrid/rolf-categories[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                        'anrid' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAnrRolfCategories',
                    ),
                ),
            ),
            'monarc_api_client_rolf_risks' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client-anr/:anrid/rolf-risks[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                        'anrid' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAnrRolfRisks',
                    ),
                ),
            ),
            'monarc_api_client_objects' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client-anr/:anrid/objects[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                        'anrid' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAnrObjects',
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
            'monarc_api_anr_interviews' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client-anr/:anrid/interviews[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                        'anrid' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAnrInterviews',
                    ),
                ),
            ),
            'monarc_api_client_anr_scales' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client-anr/:anrid/scales[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                        'anrid' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAnrScales',
                    ),
                ),
            ),
            'monarc_api_client_anr_scales_types' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client-anr/:anrid/scales-types[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                        'anrid' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAnrScalesTypes',
                    ),
                ),
            ),
            'monarc_api_client_anr_scales_comments' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client-anr/:anrid/scales/:scaleid/comments[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                        'anrid' => '[0-9]+',
                        'scaleid' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAnrScalesComments',
                    ),
                ),
            ),
            'monarc_api_anr_questions' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client-anr/:anrid/questions[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                        'anrid' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAnrQuestions',
                    ),
                ),
            ),
            'monarc_api_anr_questions_choices' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client-anr/:anrid/questions-choices[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                        'anrid' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAnrQuestionsChoices',
                    ),
                ),
            ),
            'monarc_api_anr_recommandations' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client-anr/:anrid/recommandations[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                        'anrid' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAnrRecommandations',
                    ),
                ),
            ),
            'monarc_api_anr_recommandations_risks' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client-anr/:anrid/recommandations-risks[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                        'anrid' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAnrRecommandationsRisks',
                    ),
                ),
            ),
            'monarc_api_anr_recommandations_measures' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client-anr/:anrid/recommandations-measures[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                        'anrid' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAnrRecommandationsMeasures',
                    ),
                ),
            ),
            'monarc_api_anr_carto_risks' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client-anr/:anrid/carto-risks[/:type]',
                    'constraints' => array(
                        'anrid' => '[0-9]+',
                        'type' => 'all|real|targeted',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAnrCartoRisks',
                        'type' => 'all',
                    ),
                ),
            ),
            'monarc_api_anr_risks' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client-anr/:anrid/risks[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                        'anrid' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAnrRisks',
                    ),
                ),
            ),
            'monarc_api_anr_client_library' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client-anr/:anrid/library[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                        'anrid' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAnrLibrary',
                    ),
                ),
            ),
            'monarc_api_anr_treatment_plan' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client-anr/:anrid/treatment-plan[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                        'anrid' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAnrTreatmentPlan',
                    ),
                ),
            ),
            'monarc_api_anr_client_instance' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client-anr/:anrid/instances[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                        'anrid' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiAnrInstances',
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
            '\MonarcFO\Controller\ApiAnrLibrary'                    => '\MonarcFO\Controller\ApiAnrLibraryControllerFactory',
            '\MonarcFO\Controller\ApiAnrInstances'                  => '\MonarcFO\Controller\ApiAnrInstancesControllerFactory',
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
            'monarc_api_anr_client_instance',
            'monarc_api_anr_instances_risks',
            'monarc_api_anr_instances_risksop',
            'monarc_api_anr_instances_consequences',
            'monarc_api_anr_interviews',
            'monarc_api_anr_client_library',
            'monarc_api_anr_objects',
            'monarc_api_anr_questions',
            'monarc_api_anr_questions_choices',
            'monarc_api_anr_risks',
            'monarc_api_anr_risks_op',
            'monarc_api_client_amvs',
            'monarc_api_client_anr',
            'monarc_api_client_assets',
            'monarc_api_client_assets_import',
            'monarc_api_client_assets_import_common',
            'monarc_api_client_measures',
            'monarc_api_client_objects',
            'monarc_api_client_rolf_categories',
            'monarc_api_client_rolf_risks',
            'monarc_api_client_rolf_tags',
            'monarc_api_client_snapshot',
            'monarc_api_client_snapshot_restore',
            'monarc_api_client_themes',
            'monarc_api_client_threats',
            'monarc_api_client_vulnerabilities',
            'monarc_api_duplicate_client_anr',
            'monarc_api_models',
            'monarc_api_scales',
            'monarc_api_scales_comments',
            'monarc_api_scales_types',
            'monarc_api_user_profile',
            'monarc_api_anr_carto_risks',
            'monarc_api_client_anr_scales',
            'monarc_api_client_anr_scales_types',
            'monarc_api_client_anr_scales_comments',
            'monarc_api_anr_recommandations',
            'monarc_api_anr_recommandations_risks',
            'monarc_api_anr_recommandations_measures',
            'monarc_api_anr_treatment_plan',
        ),
        // Utilisateur : Accès RWD par analyse
        'userfo'=> array(
            'monarc_api_admin_users_roles',
            'monarc_api_anr_instances',
            'monarc_api_anr_instances_risks',
            'monarc_api_anr_instances_risksop',
            'monarc_api_anr_instances_consequences',
            'monarc_api_anr_interviews',
            'monarc_api_anr_client_library',
            'monarc_api_anr_objects',
            'monarc_api_anr_questions',
            'monarc_api_anr_questions_choices',
            'monarc_api_anr_risks',
            'monarc_api_anr_risks_op',
            'monarc_api_client_amvs',
            'monarc_api_client_anr',
            'monarc_api_client_assets',
            'monarc_api_client_assets_import',
            'monarc_api_client_assets_import_common',
            'monarc_api_client_measures',
            'monarc_api_client_objects',
            'monarc_api_client_rolf_categories',
            'monarc_api_client_rolf_risks',
            'monarc_api_client_rolf_tags',
            'monarc_api_client_snapshot',
            'monarc_api_client_snapshot_restore',
            'monarc_api_client_themes',
            'monarc_api_client_threats',
            'monarc_api_client_vulnerabilities',
            'monarc_api_duplicate_client_anr',
            'monarc_api_models',
            'monarc_api_scales',
            'monarc_api_scales_comments',
            'monarc_api_scales_types',
            'monarc_api_user_profile',
            'monarc_api_anr_carto_risks',
            'monarc_api_client_anr_scales',
            'monarc_api_client_anr_scales_types',
            'monarc_api_client_anr_scales_comments',
            'monarc_api_anr_recommandations',
            'monarc_api_anr_recommandations_risks',
            'monarc_api_anr_recommandations_measures',
            'monarc_api_anr_treatment_plan',
        ),
    )
);
