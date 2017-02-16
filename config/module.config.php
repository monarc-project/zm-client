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

            'monarc_api_client' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/client[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiClients',
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
                        'controller' => 'MonarcFO\Controller\ApiModels',
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

            'monarc_api_guides' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/guides[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiGuides',
                    ),
                ),
            ),

            'monarc_api_guides_items' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/guides-items[/:id]',
                    'constraints' => array(
                        'id' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiGuidesItems',
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
                    'export' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'export',
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrExport',
                            ),
                        ),
                    ),

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
                            'constraints' => array(),
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
                    'objects_parents' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'objects/:id/parents',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrObject',
                                'action' => 'parents'
                            ),
                        ),
                    ),
                    'objects_export' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'objects/:id/export',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrObjectsExport',
                            ),
                        ),
                    ),
                    'objects_import' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'objects/import[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrObjectsImport',
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
                    'recommandations_historics' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'recommandations-historics[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrRecommandationsHistorics',
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
                    'recommandations_risks_validate' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'recommandations-risks[/:id]/validate',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrRecommandationsRisksValidate',
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
                    'library_category' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'library-category[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrLibraryCategory',
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
                    'instance_export' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'instances/:id/export',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrInstancesExport',
                            ),
                        ),
                    ),
                    'instance_import' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'instances/import',
                            'constraints' => array(),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrInstancesImport',
                            ),
                        ),
                    ),
                    'instance_risk' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'instances-risks[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrInstancesRisks',
                            ),
                        ),
                    ),
                    'instance_risk_op' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'instances-oprisks[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrInstancesRisksOp',
                            ),
                        ),
                    ),
                    'instance_consequences' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'instances-consequences[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrInstancesConsequences',
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
                    'snapshot' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'snapshot[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiSnapshot',
                            ),
                        ),
                    ),
                    'snapshot_restore' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'restore-snapshot/:id',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiSnapshotRestore',
                            ),
                        ),
                    ),
                    'deliverable' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'deliverable[/:id]',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrDeliverable',
                            ),
                        ),
                    ),
                    'objects_objects' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'objects-objects[/:id]',
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrObjectsObjects',
                            ),
                        ),
                    ),
                    'objects_duplication' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => 'objects-duplication',
                            'defaults' => array(
                                'controller' => 'MonarcFO\Controller\ApiAnrObjectsDuplication',
                            ),
                        ),
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
            'monarc_api_model_verify_language' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/api/model-verify-language/:id',
                    'constraints' => array(
                        'id' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'MonarcFO\Controller\ApiModelVerifyLanguage',
                    ),
                ),
            ),
        ),
    ),

    'controllers' => array(
        'invokables' => array(),
        'factories' => array(
            '\MonarcFO\Controller\Index' => '\MonarcCore\Controller\IndexControllerFactory',
            '\MonarcFO\Controller\ApiAdminPasswords' => '\MonarcFO\Controller\ApiAdminPasswordsControllerFactory',
            '\MonarcFO\Controller\ApiAdminRoles' => '\MonarcFO\Controller\ApiAdminRolesControllerFactory',
            '\MonarcFO\Controller\ApiAdminUsers' => '\MonarcFO\Controller\ApiAdminUsersControllerFactory',
            '\MonarcFO\Controller\ApiAdminUsersRoles' => '\MonarcFO\Controller\ApiAdminUsersRolesControllerFactory',
            '\MonarcFO\Controller\ApiAdminUsersRights' => '\MonarcFO\Controller\ApiAdminUsersRightsControllerFactory',
            '\MonarcFO\Controller\ApiAnr' => '\MonarcFO\Controller\ApiAnrControllerFactory',
            '\MonarcFO\Controller\ApiGuides' => '\MonarcFO\Controller\ApiGuidesControllerFactory',
            '\MonarcFO\Controller\ApiGuidesItems' => '\MonarcFO\Controller\ApiGuidesItemsControllerFactory',
            '\MonarcFO\Controller\ApiSnapshot' => '\MonarcFO\Controller\ApiSnapshotControllerFactory',
            '\MonarcFO\Controller\ApiSnapshotRestore' => '\MonarcFO\Controller\ApiSnapshotRestoreControllerFactory',
            '\MonarcFO\Controller\ApiConfig' => '\MonarcFO\Controller\ApiConfigControllerFactory',
            '\MonarcFO\Controller\ApiClients' => '\MonarcFO\Controller\ApiClientsControllerFactory',
            '\MonarcFO\Controller\ApiModels' => '\MonarcFO\Controller\ApiModelsControllerFactory',
            '\MonarcFO\Controller\ApiDuplicateAnr' => '\MonarcFO\Controller\ApiDuplicateAnrControllerFactory',
            '\MonarcFO\Controller\ApiUserPassword' => '\MonarcFO\Controller\ApiUserPasswordControllerFactory',
            '\MonarcFO\Controller\ApiUserProfile' => '\MonarcFO\Controller\ApiUserProfileControllerFactory',
            '\MonarcFO\Controller\ApiAnrAssets' => '\MonarcFO\Controller\ApiAnrAssetsControllerFactory',
            '\MonarcFO\Controller\ApiAnrAmvs' => '\MonarcFO\Controller\ApiAnrAmvsControllerFactory',
            '\MonarcFO\Controller\ApiAnrMeasures' => '\MonarcFO\Controller\ApiAnrMeasuresControllerFactory',
            '\MonarcFO\Controller\ApiAnrObjects' => '\MonarcFO\Controller\ApiAnrObjectsControllerFactory',
            '\MonarcFO\Controller\ApiAnrObjectsObjects' => '\MonarcFO\Controller\ApiAnrObjectsObjectsControllerFactory',
            '\MonarcFO\Controller\ApiAnrObjectsDuplication' => '\MonarcFO\Controller\ApiAnrObjectsDuplicationControllerFactory',
            '\MonarcFO\Controller\ApiAnrObject' => '\MonarcFO\Controller\ApiAnrObjectControllerFactory',
            '\MonarcFO\Controller\ApiAnrQuestions' => '\MonarcFO\Controller\ApiAnrQuestionsControllerFactory',
            '\MonarcFO\Controller\ApiAnrQuestionsChoices' => '\MonarcFO\Controller\ApiAnrQuestionsChoicesControllerFactory',
            '\MonarcFO\Controller\ApiAnrThreats' => '\MonarcFO\Controller\ApiAnrThreatsControllerFactory',
            '\MonarcFO\Controller\ApiAnrThemes' => '\MonarcFO\Controller\ApiAnrThemesControllerFactory',
            '\MonarcFO\Controller\ApiAnrVulnerabilities' => '\MonarcFO\Controller\ApiAnrVulnerabilitiesControllerFactory',
            '\MonarcFO\Controller\ApiAnrRolfTags' => '\MonarcFO\Controller\ApiAnrRolfTagsControllerFactory',
            '\MonarcFO\Controller\ApiAnrRolfCategories' => '\MonarcFO\Controller\ApiAnrRolfCategoriesControllerFactory',
            '\MonarcFO\Controller\ApiAnrRolfRisks' => '\MonarcFO\Controller\ApiAnrRolfRisksControllerFactory',
            '\MonarcFO\Controller\ApiAnrAssetsImport' => '\MonarcFO\Controller\ApiAnrAssetsImportControllerFactory',
            '\MonarcFO\Controller\ApiAnrAssetsImportCommon' => '\MonarcFO\Controller\ApiAnrAssetsImportCommonControllerFactory',
            '\MonarcFO\Controller\ApiAnrInterviews' => '\MonarcFO\Controller\ApiAnrInterviewsControllerFactory',
            '\MonarcFO\Controller\ApiAnrRecommandations' => '\MonarcFO\Controller\ApiAnrRecommandationsControllerFactory',
            '\MonarcFO\Controller\ApiAnrRecommandationsHistorics' => '\MonarcFO\Controller\ApiAnrRecommandationsHistoricsControllerFactory',
            '\MonarcFO\Controller\ApiAnrRecommandationsRisks' => '\MonarcFO\Controller\ApiAnrRecommandationsRisksControllerFactory',
            '\MonarcFO\Controller\ApiAnrRecommandationsRisksValidate' => '\MonarcFO\Controller\ApiAnrRecommandationsRisksValidateControllerFactory',
            '\MonarcFO\Controller\ApiAnrRecommandationsMeasures' => '\MonarcFO\Controller\ApiAnrRecommandationsMeasuresControllerFactory',
            '\MonarcFO\Controller\ApiAnrTreatmentPlan' => '\MonarcFO\Controller\ApiAnrTreatmentPlanControllerFactory',
            '\MonarcFO\Controller\ApiAnrScales' => '\MonarcFO\Controller\ApiAnrScalesControllerFactory',
            '\MonarcFO\Controller\ApiAnrScalesTypes' => '\MonarcFO\Controller\ApiAnrScalesTypesControllerFactory',
            '\MonarcFO\Controller\ApiAnrScalesComments' => '\MonarcFO\Controller\ApiAnrScalesCommentsControllerFactory',
            '\MonarcFO\Controller\ApiAnrCartoRisks' => '\MonarcFO\Controller\ApiAnrCartoRisksControllerFactory',
            '\MonarcFO\Controller\ApiAnrRisks' => '\MonarcFO\Controller\ApiAnrRisksControllerFactory',
            '\MonarcFO\Controller\ApiAnrRisksOp' => '\MonarcFO\Controller\ApiAnrRisksOpControllerFactory',
            '\MonarcFO\Controller\ApiAnrLibrary' => '\MonarcFO\Controller\ApiAnrLibraryControllerFactory',
            '\MonarcFO\Controller\ApiAnrLibraryCategory' => '\MonarcFO\Controller\ApiAnrLibraryCategoryControllerFactory',
            '\MonarcFO\Controller\ApiAnrInstances' => '\MonarcFO\Controller\ApiAnrInstancesControllerFactory',
            '\MonarcFO\Controller\ApiAnrInstancesRisks' => '\MonarcFO\Controller\ApiAnrInstancesRisksControllerFactory',
            '\MonarcFO\Controller\ApiAnrInstancesRisksOp' => '\MonarcFO\Controller\ApiAnrInstancesRisksOpControllerFactory',
            '\MonarcFO\Controller\ApiAnrInstancesImport' => '\MonarcFO\Controller\ApiAnrInstancesImportControllerFactory',
            '\MonarcFO\Controller\ApiAnrInstancesExport' => '\MonarcFO\Controller\ApiAnrInstancesExportControllerFactory',
            '\MonarcFO\Controller\ApiAnrObjectsCategories' => '\MonarcFO\Controller\ApiAnrObjectsCategoriesControllerFactory',
            '\MonarcFO\Controller\ApiAnrObjectsExport' => '\MonarcFO\Controller\ApiAnrObjectsExportControllerFactory',
            '\MonarcFO\Controller\ApiAnrObjectsImport' => '\MonarcFO\Controller\ApiAnrObjectsImportControllerFactory',
            '\MonarcFO\Controller\ApiAnrDeliverable' => '\MonarcFO\Controller\ApiAnrDeliverableControllerFactory',
            '\MonarcFO\Controller\ApiAnrExport' => '\MonarcFO\Controller\ApiAnrExportControllerFactory',
            '\MonarcFO\Controller\ApiAnrInstancesConsequences' => '\MonarcFO\Controller\ApiAnrInstancesConsequencesControllerFactory',
            '\MonarcFO\Controller\ApiModelVerifyLanguage' => '\MonarcFO\Controller\ApiModelVerifyLanguageControllerFactory',
        ),
    ),

    'view_manager' => array(
        'display_not_found_reason' => true,
        'display_exceptions' => true,
        'doctype' => 'HTML5',
        'not_found_template' => 'error/404',
        'exception_template' => 'error/index',
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

    'service_manager' => array(
        'invokables' => array(),
        'factories' => array(
            '\MonarcCli\Model\Db' => '\MonarcFO\Service\Model\DbCliFactory',

            //tables
            '\MonarcFO\Model\Table\AmvTable' => '\MonarcFO\Service\Model\Table\AmvServiceModelTable',
            '\MonarcFO\Model\Table\AnrObjectCategoryTable' => '\MonarcFO\Service\Model\Table\AnrObjectCategoryServiceModelTable',
            '\MonarcFO\Model\Table\AnrTable' => '\MonarcFO\Service\Model\Table\AnrServiceModelTable',
            '\MonarcFO\Model\Table\AssetTable' => '\MonarcFO\Service\Model\Table\AssetServiceModelTable',
            '\MonarcFO\Model\Table\ClientTable' => '\MonarcFO\Service\Model\Table\ClientServiceModelTable',
            '\MonarcFO\Model\Table\DeliveryTable' => '\MonarcFO\Service\Model\Table\DeliveryServiceModelTable',
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
            '\MonarcFO\Model\Table\RecommandationTable' => '\MonarcFO\Service\Model\Table\RecommandationServiceModelTable',
            '\MonarcFO\Model\Table\RecommandationHistoricTable' => '\MonarcFO\Service\Model\Table\RecommandationHistoricServiceModelTable',
            '\MonarcFO\Model\Table\RecommandationMeasureTable' => '\MonarcFO\Service\Model\Table\RecommandationMeasureServiceModelTable',
            '\MonarcFO\Model\Table\RecommandationRiskTable' => '\MonarcFO\Service\Model\Table\RecommandationRiskServiceModelTable',
            '\MonarcFO\Model\Table\ScaleTable' => '\MonarcFO\Service\Model\Table\ScaleServiceModelTable',
            '\MonarcFO\Model\Table\ScaleCommentTable' => '\MonarcFO\Service\Model\Table\ScaleCommentServiceModelTable',
            '\MonarcFO\Model\Table\ScaleImpactTypeTable' => '\MonarcFO\Service\Model\Table\ScaleImpactTypeServiceModelTable',
            '\MonarcFO\Model\Table\SnapshotTable' => '\MonarcFO\Service\Model\Table\SnapshotServiceModelTable',
            '\MonarcFO\Model\Table\ThemeTable' => '\MonarcFO\Service\Model\Table\ThemeServiceModelTable',
            '\MonarcFO\Model\Table\ThreatTable' => '\MonarcFO\Service\Model\Table\ThreatServiceModelTable',
            '\MonarcFO\Model\Table\UserTable' => '\MonarcFO\Service\Model\Table\UserServiceModelTable',
            '\MonarcFO\Model\Table\UserAnrTable' => '\MonarcFO\Service\Model\Table\UserAnrServiceModelTable',
            '\MonarcFO\Model\Table\UserRoleTable' => '\MonarcFO\Service\Model\Table\UserRoleServiceModelTable',
            '\MonarcFO\Model\Table\UserTokenTable' => '\MonarcFO\Service\Model\Table\UserTokenServiceModelTable',
            '\MonarcFO\Model\Table\VulnerabilityTable' => '\MonarcFO\Service\Model\Table\VulnerabilityServiceModelTable',
            '\MonarcFO\Model\Table\QuestionTable' => '\MonarcFO\Service\Model\Table\QuestionServiceModelTable',
            '\MonarcFO\Model\Table\QuestionChoiceTable' => '\MonarcFO\Service\Model\Table\QuestionChoiceServiceModelTable',

            //entities
            '\MonarcFO\Model\Entity\Amv' => '\MonarcFO\Service\Model\Entity\AmvServiceModelEntity',
            '\MonarcFO\Model\Entity\Anr' => '\MonarcFO\Service\Model\Entity\AnrServiceModelEntity',
            '\MonarcFO\Model\Entity\AnrObjectCategory' => '\MonarcFO\Service\Model\Entity\AnrObjectCategoryServiceModelEntity',
            '\MonarcFO\Model\Entity\Asset' => '\MonarcFO\Service\Model\Entity\AssetServiceModelEntity',
            '\MonarcFO\Model\Entity\Client' => '\MonarcFO\Service\Model\Entity\ClientServiceModelEntity',
            '\MonarcFO\Model\Entity\Delivery' => '\MonarcFO\Service\Model\Entity\DeliveryServiceModelEntity',
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
            '\MonarcFO\Model\Entity\Recommandation' => '\MonarcFO\Service\Model\Entity\RecommandationServiceModelEntity',
            '\MonarcFO\Model\Entity\RecommandationHistoric' => '\MonarcFO\Service\Model\Entity\RecommandationHistoricServiceModelEntity',
            '\MonarcFO\Model\Entity\RecommandationMeasure' => '\MonarcFO\Service\Model\Entity\RecommandationMeasureServiceModelEntity',
            '\MonarcFO\Model\Entity\RecommandationRisk' => '\MonarcFO\Service\Model\Entity\RecommandationRiskServiceModelEntity',
            '\MonarcFO\Model\Entity\Scale' => '\MonarcFO\Service\Model\Entity\ScaleServiceModelEntity',
            '\MonarcFO\Model\Entity\ScaleComment' => '\MonarcFO\Service\Model\Entity\ScaleCommentServiceModelEntity',
            '\MonarcFO\Model\Entity\ScaleImpactType' => '\MonarcFO\Service\Model\Entity\ScaleImpactTypeServiceModelEntity',
            '\MonarcFO\Model\Entity\Snapshot' => '\MonarcFO\Service\Model\Entity\SnapshotServiceModelEntity',
            '\MonarcFO\Model\Entity\Theme' => '\MonarcFO\Service\Model\Entity\ThemeServiceModelEntity',
            '\MonarcFO\Model\Entity\Threat' => '\MonarcFO\Service\Model\Entity\ThreatServiceModelEntity',
            '\MonarcFO\Model\Entity\User' => '\MonarcFO\Service\Model\Entity\UserServiceModelEntity',
            '\MonarcFO\Model\Entity\UserAnr' => '\MonarcFO\Service\Model\Entity\UserAnrServiceModelEntity',
            '\MonarcFO\Model\Entity\UserRole' => '\MonarcFO\Service\Model\Entity\UserRoleServiceModelEntity',
            '\MonarcFO\Model\Entity\UserToken' => '\MonarcFO\Service\Model\Entity\UserTokenServiceModelEntity',
            '\MonarcFO\Model\Entity\Vulnerability' => '\MonarcFO\Service\Model\Entity\VulnerabilityServiceModelEntity',
            '\MonarcFO\Model\Entity\Question' => '\MonarcFO\Service\Model\Entity\QuestionServiceModelEntity',
            '\MonarcFO\Model\Entity\QuestionChoice' => '\MonarcFO\Service\Model\Entity\QuestionChoiceServiceModelEntity',

            //services
            '\MonarcFO\Service\AnrService' => '\MonarcFO\Service\AnrServiceFactory',
            '\MonarcFO\Service\SnapshotService' => '\MonarcFO\Service\SnapshotServiceFactory',
            '\MonarcFO\Service\UserService' => '\MonarcFO\Service\UserServiceFactory',
            '\MonarcFO\Service\UserAnrService' => '\MonarcFO\Service\UserAnrServiceFactory',
            '\MonarcFO\Service\UserRoleService' => '\MonarcFO\Service\UserRoleServiceFactory',
            '\MonarcFO\Service\AnrAssetService' => '\MonarcFO\Service\AnrAssetServiceFactory',
            '\MonarcFO\Service\AnrAssetCommonService' => '\MonarcFO\Service\AnrAssetCommonServiceFactory',
            '\MonarcFO\Service\AnrAmvService' => '\MonarcFO\Service\AnrAmvServiceFactory',
            '\MonarcFO\Service\AnrInterviewService' => '\MonarcFO\Service\AnrInterviewServiceFactory',
            '\MonarcFO\Service\AnrMeasureService' => '\MonarcFO\Service\AnrMeasureServiceFactory',
            '\MonarcFO\Service\AnrQuestionService' => '\MonarcFO\Service\AnrQuestionServiceFactory',
            '\MonarcFO\Service\AnrQuestionChoiceService' => '\MonarcFO\Service\AnrQuestionChoiceServiceFactory',
            '\MonarcFO\Service\AnrThreatService' => '\MonarcFO\Service\AnrThreatServiceFactory',
            '\MonarcFO\Service\AnrThemeService' => '\MonarcFO\Service\AnrThemeServiceFactory',
            '\MonarcFO\Service\AnrVulnerabilityService' => '\MonarcFO\Service\AnrVulnerabilityServiceFactory',
            '\MonarcFO\Service\AnrRolfTagService' => '\MonarcFO\Service\AnrRolfTagServiceFactory',
            '\MonarcFO\Service\AnrRolfCategoryService' => '\MonarcFO\Service\AnrRolfCategoryServiceFactory',
            '\MonarcFO\Service\AnrRolfRiskService' => '\MonarcFO\Service\AnrRolfRiskServiceFactory',
            '\MonarcFO\Service\AmvService' => '\MonarcFO\Service\AmvServiceFactory',
            '\MonarcFO\Service\AssetService' => '\MonarcFO\Service\AssetServiceFactory',
            '\MonarcFO\Service\ClientService' => '\MonarcFO\Service\ClientServiceFactory',
            '\MonarcFO\Service\ObjectService' => '\MonarcFO\Service\ObjectServiceFactory',
            '\MonarcFO\Service\ObjectCategoryService' => '\MonarcFO\Service\ObjectCategoryServiceFactory',
            '\MonarcFO\Service\ObjectObjectService' => '\MonarcFO\Service\ObjectObjectServiceFactory',
            '\MonarcFO\Service\PasswordService' => '\MonarcFO\Service\PasswordServiceFactory',
            '\MonarcFO\Service\MailService' => '\MonarcFO\Service\MailServiceFactory',
            '\MonarcFO\Service\ModelService' => '\MonarcFO\Service\ModelServiceFactory',
            '\MonarcFO\Service\AnrLibraryService' => '\MonarcFO\Service\AnrLibraryServiceFactory',
            '\MonarcFO\Service\AnrRecommandationService' => '\MonarcFO\Service\AnrRecommandationServiceFactory',
            '\MonarcFO\Service\AnrRecommandationHistoricService' => '\MonarcFO\Service\AnrRecommandationHistoricServiceFactory',
            '\MonarcFO\Service\AnrRecommandationMeasureService' => '\MonarcFO\Service\AnrRecommandationMeasureServiceFactory',
            '\MonarcFO\Service\AnrRecommandationRiskService' => '\MonarcFO\Service\AnrRecommandationRiskServiceFactory',
            '\MonarcFO\Service\AnrScaleService' => '\MonarcFO\Service\AnrScaleServiceFactory',
            '\MonarcFO\Service\AnrScaleTypeService' => '\MonarcFO\Service\AnrScaleTypeServiceFactory',
            '\MonarcFO\Service\AnrScaleCommentService' => '\MonarcFO\Service\AnrScaleCommentServiceFactory',
            '\MonarcFO\Service\AnrCheckStartedService' => '\MonarcFO\Service\AnrCheckStartedServiceFactory',
            '\MonarcFO\Service\AnrCartoRiskService' => '\MonarcFO\Service\AnrCartoRiskServiceFactory',
            '\MonarcFO\Service\AnrRiskService' => '\MonarcFO\Service\AnrRiskServiceFactory',
            '\MonarcFO\Service\AnrObjectService' => '\MonarcFO\Service\AnrObjectServiceFactory',
            '\MonarcFO\Service\AnrInstanceConsequenceService' => '\MonarcFO\Service\AnrInstanceConsequenceServiceFactory',
            '\MonarcFO\Service\AnrInstanceRiskOpService' => '\MonarcFO\Service\AnrInstanceRiskOpServiceFactory',
            '\MonarcFO\Service\AnrInstanceRiskService' => '\MonarcFO\Service\AnrInstanceRiskServiceFactory',
            '\MonarcFO\Service\AnrInstanceService' => '\MonarcFO\Service\AnrInstanceServiceFactory',
            '\MonarcFO\Service\AnrRiskOpService' => '\MonarcFO\Service\AnrRiskOpServiceFactory',
            '\MonarcFO\Service\AnrObjectCategoryService' => '\MonarcFO\Service\AnrObjectCategoryServiceFactory',
            '\MonarcFO\Service\ObjectExportService' => '\MonarcFO\Service\ObjectExportServiceFactory',
            '\MonarcFO\Service\AssetExportService' => '\MonarcFO\Service\AssetExportServiceFactory',
            '\MonarcFO\Service\DeliverableGenerationService' => '\MonarcFO\Service\DeliverableGenerationServiceFactory',

            //validators
            '\MonarcFO\Validator\UniqueClientProxyAlias' => '\MonarcFO\Validator\UniqueClientProxyAlias',
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
            'routes' => array(),
        ),
    ),
    'roles' => array(
        // Super Admin : Gestion des droits des utilisateurs uniquement (Carnet d’adresses)
        'superadminfo' => array(
            'monarc_api_admin_users',
            'monarc_api_admin_users_roles',
            'monarc_api_admin_users_rights',
            'monarc_api_user_password',
            'monarc_api_user_profile',
            'monarc_api_client_anr',
            'monarc_api_guides',
            'monarc_api_guides_items',
            'monarc_api_models',
            'monarc_api_client',
            'monarc_api_user_profile',
            'monarc_api_anr_carto_risks',
            'monarc_api_global_client_anr/carto_risks',
        ),
        // Utilisateur : Accès RWD par analyse
        'userfo' => array(
            'monarc_api_admin_users_roles',
            'monarc_api_global_client_anr/instance',
            'monarc_api_global_client_anr/instance_risk',
            'monarc_api_global_client_anr/instance_risk_op',
            'monarc_api_global_client_anr/instance_export',
            'monarc_api_global_client_anr/instance_import',
            'monarc_api_global_client_anr/instance_consequences',
            'monarc_api_anr_instances_consequences',
            'monarc_api_global_client_anr/interviews',
            'monarc_api_global_client_anr/library',
            'monarc_api_global_client_anr/library_category',
            'monarc_api_anr_objects',
            'monarc_api_anr_objects_objects',
            'monarc_api_anr_objects_duplication',
            'monarc_api_global_client_anr/export',
            'monarc_api_guides',
            'monarc_api_guides_items',
            'monarc_api_anr_objects_parents',
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
            'monarc_api_global_client_anr/objects_parents',
            'monarc_api_global_client_anr/objects_objects',
            'monarc_api_global_client_anr/objects_duplication',
            'monarc_api_global_client_anr/objects_export',
            'monarc_api_global_client_anr/objects_import',
            'monarc_api_global_client_anr/rolf_categories',
            'monarc_api_global_client_anr/rolf_risks',
            'monarc_api_global_client_anr/rolf_tags',
            'monarc_api_global_client_anr/tags',
            'monarc_api_global_client_anr/snapshot',
            'monarc_api_global_client_anr/snapshot_restore',
            'monarc_api_global_client_anr/themes',
            'monarc_api_global_client_anr/threats',
            'monarc_api_global_client_anr/vulnerabilities',
            'monarc_api_global_client_anr/deliverable',
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
            'monarc_api_anr_recommandations_historics',
            'monarc_api_anr_recommandations_risks',
            'monarc_api_anr_recommandations_risks_validate',
            'monarc_api_anr_recommandations_measures',
            'monarc_api_anr_treatment_plan',
            'monarc_api_anr_client_objects_categories',
            'monarc_api_user_password',
            'monarc_api_model_verify_language',
            'monarc_api_global_client_anr/carto_risks',
            'monarc_api_global_client_anr/scales',
            'monarc_api_global_client_anr/scales_types',
            'monarc_api_global_client_anr/scales_comments',
            'monarc_api_global_client_anr/recommandations',
            'monarc_api_global_client_anr/recommandations_historics',
            'monarc_api_global_client_anr/recommandations_risks',
            'monarc_api_global_client_anr/recommandations_risks_validate',
            'monarc_api_global_client_anr/recommandations_measures',
            'monarc_api_global_client_anr/treatment_plan',
            'monarc_api_global_client_anr/objects_categories',
        ),
    ),
    'activeLanguages' => array('fr'),
);