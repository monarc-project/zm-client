<?php

use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Monarc\FrontOffice\Controller;
use Monarc\FrontOffice\Model\Table;
use Monarc\FrontOffice\Model\Entity;
use Monarc\Frontoffice\Service;
use Monarc\FrontOffice\Validator\UniqueClientProxyAlias;
use Zend\Di\Container\AutowireFactory;

return [
    'router' => [
        'routes' => [
            'monarc_api_admin_users_roles' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/api/users-roles[/:id]',
                    'constraints' => [
                        'id' => '[0-9]+',
                    ],
                    'defaults' => [
                        'controller' => Controller\ApiAdminUsersRolesController::class,
                    ],
                ],
            ],

            'monarc_api_admin_users_rights' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/api/users-rights[/:id]',
                    'constraints' => [
                        'id' => '[0-9]+',
                    ],
                    'defaults' => [
                        'controller' => Controller\ApiAdminUsersRightsController::class,
                    ],
                ],
            ],

            'monarc_api_admin_users' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/api/users[/:id]',
                    'constraints' => [
                        'id' => '[0-9]+',
                    ],
                    'defaults' => [
                        'controller' => Controller\ApiAdminUsersController::class,
                    ],
                ],
            ],

            'monarc_api_client' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/api/client[/:id]',
                    'constraints' => [
                        'id' => '[0-9]+',
                    ],
                    'defaults' => [
                        'controller' => Controller\ApiClientsController::class,
                    ],
                ],
            ],

            'monarc_api_models' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/api/models[/:id]',
                    'constraints' => [
                        'id' => '[0-9]+',
                    ],
                    'defaults' => [
                        'controller' => Controller\ApiModelsController::class,
                    ],
                ],
            ],

            'monarc_api_referentials' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/api/referentials[/:id]',
                    'constraints' => [
                        'id' => '[0-9]+',
                    ],
                    'defaults' => [
                        'controller' => Controller\ApiReferentialsController::class,
                    ],
                ],
            ],

            'monarc_api_duplicate_client_anr' => [
                'type' => 'literal',
                'options' => [
                    'route' => '/api/client-duplicate-anr',
                    'defaults' => [
                        'controller' => Controller\ApiDuplicateAnrController::class,
                    ],
                ],
            ],

            'monarc_api_config' => [
                'type' => 'literal',
                'options' => [
                    'route' => '/api/config',
                    'defaults' => [
                        'controller' => Controller\ApiConfigController::class,
                    ],
                ],
            ],

            'monarc_api_user_profile' => [
                'type' => 'literal',
                'options' => [
                    'route' => '/api/user/profile',
                    'defaults' => [
                        'controller' => Controller\ApiUserProfileController::class,
                    ],
                ],
            ],
            'monarc_api_admin_passwords' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/api/admin/passwords',
                    'constraints' => [],
                    'defaults' => [
                        'controller' => Controller\ApiAdminPasswordsController::class,
                    ],
                ],
            ],

            'monarc_api_client_anr' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/api/client-anr[/:id]',
                    'constraints' => [
                        'id' => '[0-9]+',
                    ],
                    'defaults' => [
                        'controller' => Controller\ApiAnrController::class,
                    ],
                ],
            ],

            'monarc_api_guides' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/api/guides[/:id]',
                    'constraints' => [
                        'id' => '[0-9]+',
                    ],
                    'defaults' => [
                        'controller' => Controller\ApiGuidesController::class,
                    ],
                ],
            ],

            'monarc_api_guides_items' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/api/guides-items[/:id]',
                    'constraints' => [
                        'id' => '[0-9]+',
                    ],
                    'defaults' => [
                        'controller' => Controller\ApiGuidesItemsController::class,
                    ],
                ],
            ],

            'monarc_api_global_client_anr' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/api/client-anr/:anrid/',
                    'constraints' => [
                        'anrid' => '[0-9]+',
                    ],
                ],
                'may_terminate' => false,
                'child_routes' => [
                    'export' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'export',
                            'defaults' => [
                                'controller' => Controller\ApiAnrExportController::class,
                            ],
                        ],
                    ],

                    'assets' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'assets[/:id]',
                            'constraints' => [
                                'id' => '[a-f0-9-]*',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrAssetsController::class,
                            ],
                        ],
                    ],
                    'amvs' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'amvs[/:id]',
                            'constraints' => [
                                'id' => '[a-f0-9-]*',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrAmvsController::class,
                            ],
                        ],
                    ],
                    'referentials' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'referentials[/:id]',
                            'constraints' => [
                                'id' => '[a-f0-9-]*',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrReferentialsController::class,
                            ],
                        ],
                    ],
                    'measures' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'measures[/:id]',
                            'constraints' => [
                                'id' => '[a-f0-9-]*',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrMeasuresController::class,
                            ],
                        ],
                    ],
                    'measuresmeasures' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'measuresmeasures[/:id]',
                            'constraints' => [
                                'id' => '[a-f0-9-]*',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrMeasuresMeasuresController::class,
                            ],
                        ],
                    ],
                    'threats' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'threats[/:id]',
                            'constraints' => [
                                'id' => '[a-f0-9-]*',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrThreatsController::class,
                            ],
                        ],
                    ],
                    'themes' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'themes[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrThemesController::class,
                            ],
                        ],
                    ],
                    'vulnerabilities' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'vulnerabilities[/:id]',
                            'constraints' => [
                                'id' => '[a-f0-9-]*',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrVulnerabilitiesController::class,
                            ],
                        ],
                    ],
                    'rolf_tags' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'rolf-tags[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrRolfTagsController::class,
                            ],
                        ],
                    ],
                    'rolf_risks' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'rolf-risks[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrRolfRisksController::class,
                            ],
                        ],
                    ],
                    'objects' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'objects[/:id]',
                            'constraints' => [
                                'id' => '[a-f0-9-]*',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrObjectsController::class,
                            ],
                        ],
                    ],
                    'objects_parents' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'objects/:id/parents',
                            'constraints' => [
                                'id' => '[a-f0-9-]*',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrObjectController::class,
                                'action' => 'parents'
                            ],
                        ],
                    ],
                    'objects_export' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'objects/:id/export',
                            'constraints' => [
                                'id' => '[a-f0-9-]*',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrObjectsExportController::class,
                            ],
                        ],
                    ],
                    'objects_import' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'objects/import[/:id]',
                            'constraints' => [
                                'id' => '[a-f0-9-]*',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrObjectsImportController::class,
                            ],
                        ],
                    ],
                    'interviews' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'interviews[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrInterviewsController::class,
                            ],
                        ],
                    ],
                    'scales' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'scales[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrScalesController::class,
                            ],
                        ],
                    ],
                    'scales_types' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'scales-types[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrScalesTypesController::class,
                            ],
                        ],
                    ],
                    'scales_comments' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'scales/:scaleid/comments[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                                'scaleid' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrScalesCommentsController::class,
                            ],
                        ],
                    ],
                    'questions' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'questions[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrQuestionsController::class,
                            ],
                        ],
                    ],
                    'questions_choices' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'questions-choices[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrQuestionsChoicesController::class,
                            ],
                        ],
                    ],
                    'recommandations' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'recommandations[/:id]',
                            'constraints' => [
                                'id' => '[a-f0-9-]*',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrRecommandationsController::class,
                            ],
                        ],
                    ],
                    'recommandations_historics' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'recommandations-historics[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrRecommandationsHistoricsController::class,
                            ],
                        ],
                    ],
                    'recommandations_risks' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'recommandations-risks[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrRecommandationsRisksController::class,
                            ],
                        ],
                    ],
                    'recommandations_risks_validate' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'recommandations-risks[/:id]/validate',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrRecommandationsRisksValidateController::class,
                            ],
                        ],
                    ],
                    'recommandations_sets' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'recommandations-sets[/:id]',
                            'constraints' => [
                                'id' => '[a-f0-9-]*',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrRecommandationsSetsController::class,
                            ],
                        ],
                    'records' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'records[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrRecordsController::class,
                            ],
                        ],
                    ],
                    'record_actors' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'record-actors[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrRecordActorsController::class,
                            ],
                        ],
                    ],
                    'record_data_categories' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'record-data-categories',
                            'defaults' => [
                                'controller' => Controller\ApiAnrRecordDataCategoriesController::class,
                            ],
                        ],
                    ],
                    'record_international_transfers' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'record-international-transfers[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrRecordInternationalTransfersController::class,
                            ],
                        ],
                    ],
                    'record_personal_data' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'record-personal-data[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrRecordPersonalDataController::class,
                            ],
                        ],
                    ],
                    'record_processors' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'record-processors[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrRecordProcessorsController::class,
                            ],
                        ],
                    ],
                    'record_recipients' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'record-recipients[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrRecordRecipientsController::class,
                            ],
                        ],
                    ],
                    'record_export' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'records/:id/export',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrRecordsExportController::class,
                            ],
                        ],
                    ],
                    'records_export' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'records/export',
                            'defaults' => [
                                'controller' => Controller\ApiAnrRecordsExportController::class,
                            ],
                        ],
                    ],
                    'record_import' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'records/import',
                            'defaults' => [
                                'controller' => Controller\ApiAnrRecordsImportController::class,
                            ],
                        ],
                    ],
                    'record_duplicate' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'records/duplicate',
                            'defaults' => [
                                'controller' => Controller\ApiAnrRecordDuplicateController::class,
                            ],
                        ],
                    ],
                    'carto_risks' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'carto-risks-dashboard[/:type]',
                            'constraints' => [
                                'type' => 'all|real|targeted',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiDashboardAnrCartoRisksController::class,
                                'type' => 'all',
                            ],
                        ],
                    ],
                    'risks' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'risks[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrRisksController::class,
                            ],
                        ],
                    ],
                    'dashboard' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'risks-dashboard[/:id]',
                            'defaults' => [
                                'controller' => Controller\ApiDashboardAnrRisksController::class,
                            ],
                        ],
                    ],
                    'risks_op' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'risksop[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrRisksOpController::class,
                            ],
                        ],
                    ],
                    'library' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'library[/:id]',
                            'constraints' => [
                                'id' => '[a-f0-9-]*',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrLibraryController::class,
                            ],
                        ],
                    ],
                    'library_category' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'library-category[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrLibraryCategoryController::class,
                            ],
                        ],
                    ],
                    'treatment_plan' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'treatment-plan[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrTreatmentPlanController::class,
                            ],
                        ],
                    ],
                    'soa' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'soa[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiSoaController::class,
                            ],
                        ],
                    ], 'soacategory' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'soacategory[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiSoaCategoryController::class,
                            ],
                        ],
                        ],
                    'instance' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'instances[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrInstancesController::class,
                            ],
                        ],
                    ],
                    'instance_export' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'instances/:id/export',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrInstancesExportController::class,
                            ],
                        ],
                    ],
                    'instance_import' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'instances/import',
                            'constraints' => [],
                            'defaults' => [
                                'controller' => Controller\ApiAnrInstancesImportController::class,
                            ],
                        ],
                    ],
                    'instance_risk' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'instances-risks[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrInstancesRisksController::class,
                            ],
                        ],
                    ],
                    'instance_risk_op' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'instances-oprisks[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrInstancesRisksOpController::class,
                            ],
                        ],
                    ],
                    'instance_consequences' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'instances-consequences[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrInstancesConsequencesController::class,
                            ],
                        ],
                    ],
                    'objects_categories' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'objects-categories[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrObjectsCategoriesController::class,
                            ],
                        ],
                    ],
                    'snapshot' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'snapshot[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiSnapshotController::class,
                            ],
                        ],
                    ],
                    'snapshot_restore' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'restore-snapshot/:id',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiSnapshotRestoreController::class,
                            ],
                        ],
                    ],
                    'deliverable' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'deliverable[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrDeliverableController::class,
                            ],
                        ],
                    ],
                    'objects_objects' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'objects-objects[/:id]',
                            'defaults' => [
                                'controller' => Controller\ApiAnrObjectsObjectsController::class,
                            ],
                        ],
                    ],
                    'objects_duplication' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'objects-duplication',
                            'defaults' => [
                                'controller' => Controller\ApiAnrObjectsDuplicationController::class,
                            ],
                        ],
                    ],
                    ],
                ],
                'monarc_api_doc_models' => [
                    'type' => 'segment',
                    'options' => [
                        'route' => '/api/deliveriesmodels[/:id]',
                        'constraints' => [
                            'id' => '[0-9]+',
                        ],
                        'defaults' => [
                            'controller' => Controller\ApiDeliveriesModelsController::class,
                        ],
                    ],
                ],
                'monarc_api_user_password' => [
                    'type' => 'segment',
                    'options' => [
                        'route' => '/api/user/password/:id',
                        'constraints' => [
                            'id' => '[0-9]+',
                        ],
                        'defaults' => [
                            'controller' => Controller\ApiUserPasswordController::class,
                        ],
                    ],
                ],
                'monarc_api_model_verify_language' => [
                    'type' => 'segment',
                    'options' => [
                        'route' => '/api/model-verify-language/:id',
                        'constraints' => [
                            'id' => '[0-9]+',
                        ],
                        'defaults' => [
                            'controller' => Controller\ApiModelVerifyLanguageController::class,
                        ],
                    ],
                ],
            ],
        ],
    ],

    'controllers' => [
        'factories' => [
            Controller\ApiAdminPasswordsController::class => Controller\ApiAdminPasswordsControllerFactory::class,
            Controller\ApiAdminUsersController::class => Controller\ApiAdminUsersControllerFactory::class,
            Controller\ApiAdminUsersRolesController::class => Controller\ApiAdminUsersRolesControllerFactory::class,
            Controller\ApiAdminUsersRightsController::class => Controller\ApiAdminUsersRightsControllerFactory::class,
            Controller\ApiAnrController::class => Controller\ApiAnrControllerFactory::class,
            Controller\ApiGuidesController::class => Controller\ApiGuidesControllerFactory::class,
            Controller\ApiGuidesItemsController::class => Controller\ApiGuidesItemsControllerFactory::class,
            Controller\ApiSnapshotController::class => Controller\ApiSnapshotControllerFactory::class,
            Controller\ApiSnapshotRestoreController::class => Controller\ApiSnapshotRestoreControllerFactory::class,
            Controller\ApiConfigController::class => AutowireFactory::class,
            Controller\ApiClientsController::class => Controller\ApiClientsControllerFactory::class,
            Controller\ApiModelsController::class => Controller\ApiModelsControllerFactory::class,
            Controller\ApiReferentialsController::class => Controller\ApiReferentialsControllerFactory::class,
            Controller\ApiDuplicateAnrController::class => Controller\ApiDuplicateAnrControllerFactory::class,
            Controller\ApiUserPasswordController::class => Controller\ApiUserPasswordControllerFactory::class,
            Controller\ApiUserProfileController::class => AutowireFactory::class,
            Controller\ApiAnrAssetsController::class => Controller\ApiAnrAssetsControllerFactory::class,
            Controller\ApiAnrAmvsController::class => Controller\ApiAnrAmvsControllerFactory::class,
            Controller\ApiAnrReferentialsController::class => Controller\ApiAnrReferentialsControllerFactory::class,
            Controller\ApiAnrMeasuresController::class => Controller\ApiAnrMeasuresControllerFactory::class,
            Controller\ApiAnrMeasuresMeasuresController::class => Controller\ApiAnrMeasuresMeasuresControllerFactory::class,
            Controller\ApiAnrObjectsController::class => Controller\ApiAnrObjectsControllerFactory::class,
            Controller\ApiAnrObjectsObjectsController::class => Controller\ApiAnrObjectsObjectsControllerFactory::class,
            Controller\ApiAnrObjectsDuplicationController::class => Controller\ApiAnrObjectsDuplicationControllerFactory::class,
            Controller\ApiAnrObjectController::class => Controller\ApiAnrObjectControllerFactory::class,
            Controller\ApiAnrQuestionsController::class => Controller\ApiAnrQuestionsControllerFactory::class,
            Controller\ApiAnrQuestionsChoicesController::class => Controller\ApiAnrQuestionsChoicesControllerFactory::class,
            Controller\ApiAnrThreatsController::class => Controller\ApiAnrThreatsControllerFactory::class,
            Controller\ApiAnrThemesController::class => Controller\ApiAnrThemesControllerFactory::class,
            Controller\ApiAnrVulnerabilitiesController::class => Controller\ApiAnrVulnerabilitiesControllerFactory::class,
            Controller\ApiAnrRolfTagsController::class => Controller\ApiAnrRolfTagsControllerFactory::class,
            Controller\ApiAnrRolfRisksController::class => Controller\ApiAnrRolfRisksControllerFactory::class,
            Controller\ApiAnrInterviewsController::class => Controller\ApiAnrInterviewsControllerFactory::class,
            Controller\ApiAnrRecommandationsController::class => Controller\ApiAnrRecommandationsControllerFactory::class,
            Controller\ApiAnrRecommandationsHistoricsController::class => Controller\ApiAnrRecommandationsHistoricsControllerFactory::class,
            Controller\ApiAnrRecommandationsRisksController::class => Controller\ApiAnrRecommandationsRisksControllerFactory::class,
            Controller\ApiAnrRecommandationsRisksValidateController::class => Controller\ApiAnrRecommandationsRisksValidateControllerFactory::class,
            Controller\ApiAnrRecommandationsSetsController::class => Controller\ApiAnrRecommandationsSetsControllerFactory::class,
            Controller\ApiAnrRecordActorsController::class => Controller\ApiAnrRecordActorsControllerFactory::class,
            Controller\ApiAnrRecordDuplicateController::class => Controller\ApiAnrRecordDuplicateControllerFactory::class,
            Controller\ApiAnrRecordDataCategoriesController::class => Controller\ApiAnrRecordDataCategoriesControllerFactory::class,
            Controller\ApiAnrRecordInternationalTransfersController::class => Controller\ApiAnrRecordInternationalTransfersControllerFactory::class,
            Controller\ApiAnrRecordPersonalDataController::class => Controller\ApiAnrRecordPersonalDataControllerFactory::class,
            Controller\ApiAnrRecordProcessorsController::class => Controller\ApiAnrRecordProcessorsControllerFactory::class,
            Controller\ApiAnrRecordRecipientsController::class => Controller\ApiAnrRecordRecipientsControllerFactory::class,
            Controller\ApiAnrRecordsController::class => Controller\ApiAnrRecordsControllerFactory::class,
            Controller\ApiAnrRecordsExportController::class => Controller\ApiAnrRecordsExportControllerFactory::class,
            Controller\ApiAnrRecordsImportController::class => Controller\ApiAnrRecordsImportControllerFactory::class,
            Controller\ApiAnrTreatmentPlanController::class => Controller\ApiAnrTreatmentPlanControllerFactory::class,
            Controller\ApiSoaController::class => Controller\ApiSoaControllerFactory::class,
            Controller\ApiSoaCategoryController::class => Controller\ApiSoaCategoryControllerFactory::class,
            Controller\ApiAnrScalesController::class => Controller\ApiAnrScalesControllerFactory::class,
            Controller\ApiAnrScalesTypesController::class => Controller\ApiAnrScalesTypesControllerFactory::class,
            Controller\ApiAnrScalesCommentsController::class => Controller\ApiAnrScalesCommentsControllerFactory::class,
            Controller\ApiDashboardAnrCartoRisksController::class => Controller\ApiDashboardAnrCartoRisksControllerFactory::class,
            Controller\ApiAnrRisksController::class => Controller\ApiAnrRisksControllerFactory::class,
            Controller\ApiDashboardAnrRisksController::class => Controller\ApiDashboardAnrRisksControllerFactory::class,
            Controller\ApiAnrRisksOpController::class => Controller\ApiAnrRisksOpControllerFactory::class,
            Controller\ApiAnrLibraryController::class => Controller\ApiAnrLibraryControllerFactory::class,
            Controller\ApiAnrLibraryCategoryController::class => Controller\ApiAnrLibraryCategoryControllerFactory::class,
            Controller\ApiAnrInstancesController::class => Controller\ApiAnrInstancesControllerFactory::class,
            Controller\ApiAnrInstancesRisksController::class => Controller\ApiAnrInstancesRisksControllerFactory::class,
            Controller\ApiAnrInstancesRisksOpController::class => Controller\ApiAnrInstancesRisksOpControllerFactory::class,
            Controller\ApiAnrInstancesImportController::class => Controller\ApiAnrInstancesImportControllerFactory::class,
            Controller\ApiAnrInstancesExportController::class => Controller\ApiAnrInstancesExportControllerFactory::class,
            Controller\ApiAnrObjectsCategoriesController::class => Controller\ApiAnrObjectsCategoriesControllerFactory::class,
            Controller\ApiAnrObjectsExportController::class => Controller\ApiAnrObjectsExportControllerFactory::class,
            Controller\ApiAnrObjectsImportController::class => Controller\ApiAnrObjectsImportControllerFactory::class,
            Controller\ApiAnrDeliverableController::class => Controller\ApiAnrDeliverableControllerFactory::class,
            Controller\ApiAnrExportController::class => Controller\ApiAnrExportControllerFactory::class,
            Controller\ApiAnrInstancesConsequencesController::class => Controller\ApiAnrInstancesConsequencesControllerFactory::class,
            Controller\ApiModelVerifyLanguageController::class => Controller\ApiModelVerifyLanguageControllerFactory::class,
            Controller\ApiDeliveriesModelsController::class => Controller\ApiDeliveriesModelsControllerFactory::class,
        ],
    ],

    'view_manager' => [
        'display_not_found_reason' => true,
        'display_exceptions' => true,
        'doctype' => 'HTML5',
        'not_found_template' => 'error/404',
        'exception_template' => 'error/index',
        'strategies' => [
            'ViewJsonStrategy'
        ],
        'template_map' => [
            'monarc-fo/index/index' => __DIR__ . '/../view/layout/layout.phtml',
            'error/404' => __DIR__ . '/../view/layout/layout.phtml',
        ],
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
    ],

    'service_manager' => [
        'invokables' => [
            Entity\UserAnr::class => Entity\UserAnr::class,
            Entity\UserRole::class => Entity\UserRole::class,
        ],
        'factories' => [
            // TODO: refactor the strange relation between Table and ServiceModelTable, the same for entities.
            '\Monarc\FrontOffice\Model\Table\AmvTable' => '\Monarc\FrontOffice\Service\Model\Table\AmvServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\AnrObjectCategoryTable' => '\Monarc\FrontOffice\Service\Model\Table\AnrObjectCategoryServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\AnrTable' => '\Monarc\FrontOffice\Service\Model\Table\AnrServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\AssetTable' => '\Monarc\FrontOffice\Service\Model\Table\AssetServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\ClientTable' => '\Monarc\FrontOffice\Service\Model\Table\ClientServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\DeliveryTable' => '\Monarc\FrontOffice\Service\Model\Table\DeliveryServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\InstanceTable' => '\Monarc\FrontOffice\Service\Model\Table\InstanceServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\InstanceConsequenceTable' => '\Monarc\FrontOffice\Service\Model\Table\InstanceConsequenceServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\InstanceRiskTable' => '\Monarc\FrontOffice\Service\Model\Table\InstanceRiskServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\InstanceRiskOpTable' => '\Monarc\FrontOffice\Service\Model\Table\InstanceRiskOpServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\InterviewTable' => '\Monarc\FrontOffice\Service\Model\Table\InterviewServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\MeasureTable' => '\Monarc\FrontOffice\Service\Model\Table\MeasureServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\MeasureMeasureTable' => '\Monarc\FrontOffice\Service\Model\Table\MeasureMeasureServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\MonarcObjectTable' => '\Monarc\FrontOffice\Service\Model\Table\MonarcObjectServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\ObjectCategoryTable' => '\Monarc\FrontOffice\Service\Model\Table\ObjectCategoryServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\ObjectObjectTable' => '\Monarc\FrontOffice\Service\Model\Table\ObjectObjectServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\PasswordTokenTable' => '\Monarc\FrontOffice\Service\Model\Table\PasswordTokenServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\RolfRiskTable' => '\Monarc\FrontOffice\Service\Model\Table\RolfRiskServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\RolfTagTable' => '\Monarc\FrontOffice\Service\Model\Table\RolfTagServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\RecordActorTable' => '\Monarc\FrontOffice\Service\Model\Table\RecordActorServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\RecordDataCategoryTable' => '\Monarc\FrontOffice\Service\Model\Table\RecordDataCategoryServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\RecordInternationalTransferTable' => '\Monarc\FrontOffice\Service\Model\Table\RecordInternationalTransferServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\RecordPersonalDataTable' => '\Monarc\FrontOffice\Service\Model\Table\RecordPersonalDataServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\RecordProcessorTable' => '\Monarc\FrontOffice\Service\Model\Table\RecordProcessorServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\RecordRecipientTable' => '\Monarc\FrontOffice\Service\Model\Table\RecordRecipientServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\RecordTable' => '\Monarc\FrontOffice\Service\Model\Table\RecordServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\ReferentialTable' => '\Monarc\FrontOffice\Service\Model\Table\ReferentialServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\RecommandationTable' => '\Monarc\FrontOffice\Service\Model\Table\RecommandationServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\RecommandationHistoricTable' => '\Monarc\FrontOffice\Service\Model\Table\RecommandationHistoricServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\RecommandationRiskTable' => '\Monarc\FrontOffice\Service\Model\Table\RecommandationRiskServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\RecommandationSetTable' => '\Monarc\FrontOffice\Service\Model\Table\RecommandationSetServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\ScaleTable' => '\Monarc\FrontOffice\Service\Model\Table\ScaleServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\ScaleCommentTable' => '\Monarc\FrontOffice\Service\Model\Table\ScaleCommentServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\ScaleImpactTypeTable' => '\Monarc\FrontOffice\Service\Model\Table\ScaleImpactTypeServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\SnapshotTable' => '\Monarc\FrontOffice\Service\Model\Table\SnapshotServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\SoaTable' => '\Monarc\FrontOffice\Service\Model\Table\SoaServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\SoaCategoryTable' => '\Monarc\FrontOffice\Service\Model\Table\SoaCategoryServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\ThemeTable' => '\Monarc\FrontOffice\Service\Model\Table\ThemeServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\ThreatTable' => '\Monarc\FrontOffice\Service\Model\Table\ThreatServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\UserTable' => '\Monarc\FrontOffice\Service\Model\Table\UserServiceModelTable',
            Table\UserAnrTable::class => AutowireFactory::class,
            Table\UserRoleTable::class => AutowireFactory::class,
            '\Monarc\FrontOffice\Model\Table\VulnerabilityTable' => '\Monarc\FrontOffice\Service\Model\Table\VulnerabilityServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\QuestionTable' => '\Monarc\FrontOffice\Service\Model\Table\QuestionServiceModelTable',
            '\Monarc\FrontOffice\Model\Table\QuestionChoiceTable' => '\Monarc\FrontOffice\Service\Model\Table\QuestionChoiceServiceModelTable',

            //entities
            '\Monarc\FrontOffice\Model\Entity\Amv' => '\Monarc\FrontOffice\Service\Model\Entity\AmvServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\Anr' => '\Monarc\FrontOffice\Service\Model\Entity\AnrServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\AnrObjectCategory' => '\Monarc\FrontOffice\Service\Model\Entity\AnrObjectCategoryServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\Asset' => '\Monarc\FrontOffice\Service\Model\Entity\AssetServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\Client' => '\Monarc\FrontOffice\Service\Model\Entity\ClientServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\Delivery' => '\Monarc\FrontOffice\Service\Model\Entity\DeliveryServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\Instance' => '\Monarc\FrontOffice\Service\Model\Entity\InstanceServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\InstanceConsequence' => '\Monarc\FrontOffice\Service\Model\Entity\InstanceConsequenceServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\InstanceRisk' => '\Monarc\FrontOffice\Service\Model\Entity\InstanceRiskServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\InstanceRiskOp' => '\Monarc\FrontOffice\Service\Model\Entity\InstanceRiskOpServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\Interview' => '\Monarc\FrontOffice\Service\Model\Entity\InterviewServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\RecordActor' => '\Monarc\FrontOffice\Service\Model\Entity\RecordActorServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\RecordDataCategory' => '\Monarc\FrontOffice\Service\Model\Entity\RecordDataCategoryServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\RecordInternationalTransfer' => '\Monarc\FrontOffice\Service\Model\Entity\RecordInternationalTransferServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\RecordPersonalData' => '\Monarc\FrontOffice\Service\Model\Entity\RecordPersonalDataServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\RecordProcessor' => '\Monarc\FrontOffice\Service\Model\Entity\RecordProcessorServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\RecordRecipient' => '\Monarc\FrontOffice\Service\Model\Entity\RecordRecipientServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\Record' => '\Monarc\FrontOffice\Service\Model\Entity\RecordServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\Referential' => '\Monarc\FrontOffice\Service\Model\Entity\ReferentialServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\Measure' => '\Monarc\FrontOffice\Service\Model\Entity\MeasureServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\MeasureMeasure' => '\Monarc\FrontOffice\Service\Model\Entity\MeasureMeasureServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\MonarcObject' => '\Monarc\FrontOffice\Service\Model\Entity\MonarcObjectServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\ObjectCategory' => '\Monarc\FrontOffice\Service\Model\Entity\ObjectCategoryServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\ObjectObject' => '\Monarc\FrontOffice\Service\Model\Entity\ObjectObjectServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\PasswordToken' => '\Monarc\FrontOffice\Service\Model\Entity\PasswordTokenServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\RolfRisk' => '\Monarc\FrontOffice\Service\Model\Entity\RolfRiskServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\RolfTag' => '\Monarc\FrontOffice\Service\Model\Entity\RolfTagServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\Recommandation' => '\Monarc\FrontOffice\Service\Model\Entity\RecommandationServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\RecommandationHistoric' => '\Monarc\FrontOffice\Service\Model\Entity\RecommandationHistoricServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\RecommandationRisk' => '\Monarc\FrontOffice\Service\Model\Entity\RecommandationRiskServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\RecommandationSet' => '\Monarc\FrontOffice\Service\Model\Entity\RecommandationSetServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\Scale' => '\Monarc\FrontOffice\Service\Model\Entity\ScaleServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\ScaleComment' => '\Monarc\FrontOffice\Service\Model\Entity\ScaleCommentServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\ScaleImpactType' => '\Monarc\FrontOffice\Service\Model\Entity\ScaleImpactTypeServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\Snapshot' => '\Monarc\FrontOffice\Service\Model\Entity\SnapshotServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\Soa' => '\Monarc\FrontOffice\Service\Model\Entity\SoaServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\SoaCategory' => '\Monarc\FrontOffice\Service\Model\Entity\SoaCategoryServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\Theme' => '\Monarc\FrontOffice\Service\Model\Entity\ThemeServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\Threat' => '\Monarc\FrontOffice\Service\Model\Entity\ThreatServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\User' => '\Monarc\FrontOffice\Service\Model\Entity\UserServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\Vulnerability' => '\Monarc\FrontOffice\Service\Model\Entity\VulnerabilityServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\Question' => '\Monarc\FrontOffice\Service\Model\Entity\QuestionServiceModelEntity',
            '\Monarc\FrontOffice\Model\Entity\QuestionChoice' => '\Monarc\FrontOffice\Service\Model\Entity\QuestionChoiceServiceModelEntity',

            // TODO: replace to autowiring.
            '\Monarc\FrontOffice\Service\AnrService' => '\Monarc\FrontOffice\Service\AnrServiceFactory',
            '\Monarc\FrontOffice\Service\AnrCoreService' => '\Monarc\FrontOffice\Service\AnrCoreServiceFactory',
            '\Monarc\FrontOffice\Service\SnapshotService' => '\Monarc\FrontOffice\Service\SnapshotServiceFactory',
            '\Monarc\FrontOffice\Service\UserService' => '\Monarc\FrontOffice\Service\UserServiceFactory',
            '\Monarc\FrontOffice\Service\UserAnrService' => '\Monarc\FrontOffice\Service\UserAnrServiceFactory',
            Service\UserRoleService::class => AutowireFactory::class,
            '\Monarc\FrontOffice\Service\AnrAssetService' => '\Monarc\FrontOffice\Service\AnrAssetServiceFactory',
            '\Monarc\FrontOffice\Service\AnrAssetCommonService' => '\Monarc\FrontOffice\Service\AnrAssetCommonServiceFactory',
            '\Monarc\FrontOffice\Service\AnrAmvService' => '\Monarc\FrontOffice\Service\AnrAmvServiceFactory',
            '\Monarc\FrontOffice\Service\AnrInterviewService' => '\Monarc\FrontOffice\Service\AnrInterviewServiceFactory',
            '\Monarc\FrontOffice\Service\AnrMeasureService' => '\Monarc\FrontOffice\Service\AnrMeasureServiceFactory',
            '\Monarc\FrontOffice\Service\AnrMeasureMeasureService' => '\Monarc\FrontOffice\Service\AnrMeasureMeasureServiceFactory',
            '\Monarc\FrontOffice\Service\AnrRecordActorService' => '\Monarc\FrontOffice\Service\AnrRecordActorServiceFactory',
            '\Monarc\FrontOffice\Service\AnrRecordDataCategoryService' => '\Monarc\FrontOffice\Service\AnrRecordDataCategoryServiceFactory',
            '\Monarc\FrontOffice\Service\AnrRecordInternationalTransferService' => '\Monarc\FrontOffice\Service\AnrRecordInternationalTransferServiceFactory',
            '\Monarc\FrontOffice\Service\AnrRecordPersonalDataService' => '\Monarc\FrontOffice\Service\AnrRecordPersonalDataServiceFactory',
            '\Monarc\FrontOffice\Service\AnrRecordProcessorService' => '\Monarc\FrontOffice\Service\AnrRecordProcessorServiceFactory',
            '\Monarc\FrontOffice\Service\AnrRecordRecipientService' => '\Monarc\FrontOffice\Service\AnrRecordRecipientServiceFactory',
            '\Monarc\FrontOffice\Service\AnrRecordService' => '\Monarc\FrontOffice\Service\AnrRecordServiceFactory',
            '\Monarc\FrontOffice\Service\AnrReferentialService' => '\Monarc\FrontOffice\Service\AnrReferentialServiceFactory',
            '\Monarc\FrontOffice\Service\SoaService' => '\Monarc\FrontOffice\Service\SoaServiceFactory',
            '\Monarc\FrontOffice\Service\SoaCategoryService' => '\Monarc\FrontOffice\Service\SoaCategoryServiceFactory',
            '\Monarc\FrontOffice\Service\AnrQuestionService' => '\Monarc\FrontOffice\Service\AnrQuestionServiceFactory',
            '\Monarc\FrontOffice\Service\AnrQuestionChoiceService' => '\Monarc\FrontOffice\Service\AnrQuestionChoiceServiceFactory',
            '\Monarc\FrontOffice\Service\AnrThreatService' => '\Monarc\FrontOffice\Service\AnrThreatServiceFactory',
            '\Monarc\FrontOffice\Service\AnrThemeService' => '\Monarc\FrontOffice\Service\AnrThemeServiceFactory',
            '\Monarc\FrontOffice\Service\AnrVulnerabilityService' => '\Monarc\FrontOffice\Service\AnrVulnerabilityServiceFactory',
            '\Monarc\FrontOffice\Service\AnrRolfTagService' => '\Monarc\FrontOffice\Service\AnrRolfTagServiceFactory',
            '\Monarc\FrontOffice\Service\AnrRolfRiskService' => '\Monarc\FrontOffice\Service\AnrRolfRiskServiceFactory',
            '\Monarc\FrontOffice\Service\AmvService' => '\Monarc\FrontOffice\Service\AmvServiceFactory',
            '\Monarc\FrontOffice\Service\AssetService' => '\Monarc\FrontOffice\Service\AssetServiceFactory',
            '\Monarc\FrontOffice\Service\ClientService' => '\Monarc\FrontOffice\Service\ClientServiceFactory',
            '\Monarc\FrontOffice\Service\ObjectService' => '\Monarc\FrontOffice\Service\ObjectServiceFactory',
            '\Monarc\FrontOffice\Service\ObjectCategoryService' => '\Monarc\FrontOffice\Service\ObjectCategoryServiceFactory',
            '\Monarc\FrontOffice\Service\ObjectObjectService' => '\Monarc\FrontOffice\Service\ObjectObjectServiceFactory',
            '\Monarc\FrontOffice\Service\PasswordService' => '\Monarc\FrontOffice\Service\PasswordServiceFactory',
            '\Monarc\FrontOffice\Service\MailService' => '\Monarc\FrontOffice\Service\MailServiceFactory',
            '\Monarc\FrontOffice\Service\ModelService' => '\Monarc\FrontOffice\Service\ModelServiceFactory',
            '\Monarc\FrontOffice\Service\AnrLibraryService' => '\Monarc\FrontOffice\Service\AnrLibraryServiceFactory',
            '\Monarc\FrontOffice\Service\AnrRecommandationService' => '\Monarc\FrontOffice\Service\AnrRecommandationServiceFactory',
            '\Monarc\FrontOffice\Service\AnrRecommandationHistoricService' => '\Monarc\FrontOffice\Service\AnrRecommandationHistoricServiceFactory',
            '\Monarc\FrontOffice\Service\AnrRecommandationRiskService' => '\Monarc\FrontOffice\Service\AnrRecommandationRiskServiceFactory',
            '\Monarc\FrontOffice\Service\AnrRecommandationSetService' => '\Monarc\FrontOffice\Service\AnrRecommandationSetServiceFactory',
            '\Monarc\FrontOffice\Service\AnrScaleService' => '\Monarc\FrontOffice\Service\AnrScaleServiceFactory',
            '\Monarc\FrontOffice\Service\AnrScaleTypeService' => '\Monarc\FrontOffice\Service\AnrScaleTypeServiceFactory',
            '\Monarc\FrontOffice\Service\AnrScaleCommentService' => '\Monarc\FrontOffice\Service\AnrScaleCommentServiceFactory',
            '\Monarc\FrontOffice\Service\AnrCheckStartedService' => '\Monarc\FrontOffice\Service\AnrCheckStartedServiceFactory',
            '\Monarc\FrontOffice\Service\AnrCartoRiskService' => '\Monarc\FrontOffice\Service\AnrCartoRiskServiceFactory',
            '\Monarc\FrontOffice\Service\AnrRiskService' => '\Monarc\FrontOffice\Service\AnrRiskServiceFactory',
            '\Monarc\FrontOffice\Service\AnrObjectService' => '\Monarc\FrontOffice\Service\AnrObjectServiceFactory',
            '\Monarc\FrontOffice\Service\AnrInstanceConsequenceService' => '\Monarc\FrontOffice\Service\AnrInstanceConsequenceServiceFactory',
            '\Monarc\FrontOffice\Service\AnrInstanceRiskOpService' => '\Monarc\FrontOffice\Service\AnrInstanceRiskOpServiceFactory',
            '\Monarc\FrontOffice\Service\AnrInstanceRiskService' => '\Monarc\FrontOffice\Service\AnrInstanceRiskServiceFactory',
            '\Monarc\FrontOffice\Service\AnrInstanceService' => '\Monarc\FrontOffice\Service\AnrInstanceServiceFactory',
            '\Monarc\FrontOffice\Service\AnrRiskOpService' => '\Monarc\FrontOffice\Service\AnrRiskOpServiceFactory',
            '\Monarc\FrontOffice\Service\AnrObjectCategoryService' => '\Monarc\FrontOffice\Service\AnrObjectCategoryServiceFactory',
            '\Monarc\FrontOffice\Service\ObjectExportService' => '\Monarc\FrontOffice\Service\ObjectExportServiceFactory',
            '\Monarc\FrontOffice\Service\AssetExportService' => '\Monarc\FrontOffice\Service\AssetExportServiceFactory',
            '\Monarc\FrontOffice\Service\DeliverableGenerationService' => '\Monarc\FrontOffice\Service\DeliverableGenerationServiceFactory',

            //validators
            UniqueClientProxyAlias::class => UniqueClientProxyAlias::class,
        ],
    ],

    'doctrine' => [
        'driver' => [
            'Monarc_cli_driver' => [
                'class' => AnnotationDriver::class,
                'cache' => 'array',
                'paths' => [
                    __DIR__ . '/../src/Model/Entity',
                    __DIR__ . '/../../Core/src/Model/Entity',
                ],
            ],
            'orm_cli' => [
                'class' => MappingDriverChain::class,
                'drivers' => [
                    'Monarc\FrontOffice\Model\Entity' => 'Monarc_cli_driver',
                ],
            ],
        ],
    ],

    // Placeholder for console routes
    'console' => [
        'router' => [
            'routes' => [],
        ],
    ],
    'roles' => [
        // Super Admin : Gestion des droits des utilisateurs uniquement (Carnet d’adresses)
        'superadminfo' => [
            'monarc_api_doc_models',
            'monarc_api_admin_users',
            'monarc_api_admin_users_roles',
            'monarc_api_admin_users_rights',
            'monarc_api_user_password',
            'monarc_api_user_profile',
            'monarc_api_client_anr',
            'monarc_api_guides',
            'monarc_api_guides_items',
            'monarc_api_models',
            'monarc_api_referentials',
            'monarc_api_client',
            'monarc_api_user_profile',
            'monarc_api_anr_carto_risks',
            'monarc_api_global_client_anr/carto_risks',
        ],
        // Utilisateur : Accès RWD par analyse
        'userfo' => [
            'monarc_api_doc_models',
            'monarc_api_models',
            'monarc_api_referentials',
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
            'monarc_api_global_client_anr/soa',
            'monarc_api_global_client_anr/soacategory',
            'monarc_api_global_client_anr/risks',
            'monarc_api_global_client_anr/risks_op',
            'monarc_api_global_client_anr/dashboard',
            'monarc_api_global_client_anr/amvs',
            'monarc_api_global_client_anr/referentials',
            'monarc_api_client_anr',
            'monarc_api_global_client_anr/assets',
            'monarc_api_global_client_anr/measures',
            'monarc_api_global_client_anr/measuresmeasures',
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
            'monarc_api_referentials',
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
            'monarc_api_anr_recommandations_sets',
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
            'monarc_api_global_client_anr/recommandations_sets',
            'monarc_api_global_client_anr/treatment_plan',
            'monarc_api_global_client_anr/objects_categories',
            'monarc_api_global_client_anr/records',
            'monarc_api_global_client_anr/record_actors',
            'monarc_api_global_client_anr/record_data_categories',
            'monarc_api_global_client_anr/record_international_transfers',
            'monarc_api_global_client_anr/record_personal_data',
            'monarc_api_global_client_anr/record_processors',
            'monarc_api_global_client_anr/record_recipients',
            'monarc_api_global_client_anr/record_export',
            'monarc_api_global_client_anr/records_export',
            'monarc_api_global_client_anr/record_import',
            'monarc_api_global_client_anr/record_duplicate',
        ],
    ],
    'activeLanguages' => ['fr'],
];
