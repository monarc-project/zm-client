<?php

use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Laminas\Di\Container\AutowireFactory;
use Laminas\ServiceManager\AbstractFactory\ReflectionBasedAbstractFactory;
use Monarc\FrontOffice\Controller;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\FrontOffice\Model\Entity;
use Monarc\FrontOffice\Model\Factory\ClientEntityManagerFactory;
use Monarc\FrontOffice\Model\Table;
use Monarc\FrontOffice\Service;
use Monarc\FrontOffice\Stats\Controller\StatsController;
use Monarc\FrontOffice\Stats\Controller\StatsAnrsSettingsController;
use Monarc\FrontOffice\Stats\Controller\StatsGeneralSettingsController;
use Monarc\FrontOffice\Stats\Provider\StatsApiProvider;
use Monarc\FrontOffice\Stats\Service\StatsAnrService;
use Monarc\FrontOffice\Stats\Service\StatsSettingsService;
use Monarc\FrontOffice\Stats\Validator\GetProcessedStatsQueryParamsValidator;
use Monarc\FrontOffice\Stats\Validator\GetProcessedStatsQueryParamsValidatorFactory;
use Monarc\FrontOffice\Stats\Validator\GetStatsQueryParamsValidator;
use Monarc\FrontOffice\Stats\Validator\GetStatsQueryParamsValidatorFactory;
use Monarc\FrontOffice\Validator\InputValidator\User\CreateUserInputValidator;

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

            'monarc_api_admin_user_reset_password' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/api/users/:id/resetPassword',
                    'constraints' => [
                        'id' => '[0-9]+',
                    ],
                    'defaults' => [
                        'controller' => Controller\ApiAdminUsersController::class,
                        'action' => 'resetPassword'
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
                    ],
                    'soacategory' => [
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

            'monarc_api_stats' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/api/stats[/]',
                    'verb' => 'get',
                    'defaults' => [
                        'controller' => StatsController::class,
                    ],
                ],
            ],
            'monarc_api_stats_global' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/api/stats/',
                ],
                'may_terminate' => false,
                'child_routes' => [
                    'processed' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'processed[/]',
                            'verb' => 'get',
                            'defaults' => [
                                'controller' => StatsController::class,
                                'action' => 'getProcessedList'
                            ],
                        ],
                    ],
                    'anrs_settings' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'anrs-settings[/]',
                            'verb' => 'get,patch',
                            'defaults' => [
                                'controller' => StatsAnrsSettingsController::class,
                            ],
                        ],
                    ],
                    'general_settings' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'general-settings[/]',
                            'verb' => 'get,patch',
                            'defaults' => [
                                'controller' => StatsGeneralSettingsController::class,
                            ],
                        ],
                    ],
                    'validate-stats-availability' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'validate-stats-availability[/]',
                            'verb' => 'get',
                            'defaults' => [
                                'controller' => StatsController::class,
                                'action' => 'validateStatsAvailability'
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],

    'controllers' => [
        'factories' => [
            Controller\ApiAdminPasswordsController::class => AutowireFactory::class,
            Controller\ApiAdminUsersController::class => AutowireFactory::class,
            Controller\ApiAdminUsersRolesController::class => AutowireFactory::class,
            Controller\ApiAdminUsersRightsController::class => Controller\ApiAdminUsersRightsControllerFactory::class,
            Controller\ApiAnrController::class => Controller\ApiAnrControllerFactory::class,
            Controller\ApiGuidesController::class => Controller\ApiGuidesControllerFactory::class,
            Controller\ApiGuidesItemsController::class => Controller\ApiGuidesItemsControllerFactory::class,
            Controller\ApiSnapshotController::class => Controller\ApiSnapshotControllerFactory::class,
            Controller\ApiSnapshotRestoreController::class => Controller\ApiSnapshotRestoreControllerFactory::class,
            Controller\ApiConfigController::class => AutowireFactory::class,
            Controller\ApiClientsController::class => AutowireFactory::class,
            Controller\ApiModelsController::class => Controller\ApiModelsControllerFactory::class,
            Controller\ApiReferentialsController::class => Controller\ApiReferentialsControllerFactory::class,
            Controller\ApiDuplicateAnrController::class => Controller\ApiDuplicateAnrControllerFactory::class,
            Controller\ApiUserPasswordController::class => AutowireFactory::class,
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
            Controller\ApiAnrRisksController::class => AutowireFactory::class,
            Controller\ApiDashboardAnrRisksController::class => Controller\ApiDashboardAnrRisksControllerFactory::class,
            Controller\ApiAnrRisksOpController::class => Controller\ApiAnrRisksOpControllerFactory::class,
            Controller\ApiAnrLibraryController::class => Controller\ApiAnrLibraryControllerFactory::class,
            Controller\ApiAnrLibraryCategoryController::class => Controller\ApiAnrLibraryCategoryControllerFactory::class,
            Controller\ApiAnrInstancesController::class => Controller\ApiAnrInstancesControllerFactory::class,
            Controller\ApiAnrInstancesRisksController::class => Controller\ApiAnrInstancesRisksControllerFactory::class,
            Controller\ApiAnrInstancesRisksOpController::class => Controller\ApiAnrInstancesRisksOpControllerFactory::class,
            Controller\ApiAnrInstancesImportController::class => AutowireFactory::class,
            Controller\ApiAnrInstancesExportController::class => Controller\ApiAnrInstancesExportControllerFactory::class,
            Controller\ApiAnrObjectsCategoriesController::class => Controller\ApiAnrObjectsCategoriesControllerFactory::class,
            Controller\ApiAnrObjectsExportController::class => Controller\ApiAnrObjectsExportControllerFactory::class,
            Controller\ApiAnrObjectsImportController::class => Controller\ApiAnrObjectsImportControllerFactory::class,
            Controller\ApiAnrDeliverableController::class => AutowireFactory::class,
            Controller\ApiAnrExportController::class => AutowireFactory::class,
            Controller\ApiAnrInstancesConsequencesController::class => Controller\ApiAnrInstancesConsequencesControllerFactory::class,
            Controller\ApiModelVerifyLanguageController::class => Controller\ApiModelVerifyLanguageControllerFactory::class,
            Controller\ApiDeliveriesModelsController::class => Controller\ApiDeliveriesModelsControllerFactory::class,
            StatsController::class => AutowireFactory::class,
            StatsAnrsSettingsController::class => AutowireFactory::class,
            StatsGeneralSettingsController::class => AutowireFactory::class,
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
        ],
        'factories' => [
            DbCli::class => Service\Model\DbCliFactory::class,

            Table\AmvTable::class => AutowireFactory::class,
            Table\AnrObjectCategoryTable::class => AutowireFactory::class,
            Table\AnrTable::class => AutowireFactory::class,
            Table\AssetTable::class => AutowireFactory::class,
            Table\ClientTable::class => AutowireFactory::class,
            Table\InstanceTable::class => AutowireFactory::class,
            Table\DeliveryTable::class => AutowireFactory::class,
            Table\InstanceConsequenceTable::class => AutowireFactory::class,
            Table\InstanceRiskTable::class => AutowireFactory::class,
            Table\InstanceRiskOpTable::class => AutowireFactory::class,
            Table\InterviewTable::class => AutowireFactory::class,
            Table\MeasureTable::class => AutowireFactory::class,
            Table\MeasureMeasureTable::class => AutowireFactory::class,
            Table\MonarcObjectTable::class => AutowireFactory::class,
            Table\ObjectCategoryTable::class => AutowireFactory::class,
            Table\ObjectObjectTable::class => AutowireFactory::class,
            Table\PasswordTokenTable::class => AutowireFactory::class,
            Table\RolfRiskTable::class => AutowireFactory::class,
            Table\RolfTagTable::class => AutowireFactory::class,
            Table\RecordActorTable::class => AutowireFactory::class,
            Table\RecordDataCategoryTable::class => AutowireFactory::class,
            Table\RecordInternationalTransferTable::class => AutowireFactory::class,
            Table\RecordPersonalDataTable::class => AutowireFactory::class,
            Table\RecordProcessorTable::class => AutowireFactory::class,
            Table\RecordRecipientTable::class => AutowireFactory::class,
            Table\RecordTable::class => AutowireFactory::class,
            Table\ReferentialTable::class => AutowireFactory::class,
            Table\RecommandationTable::class => AutowireFactory::class,
            Table\RecommendationHistoricTable::class => AutowireFactory::class,
            Table\RecommandationRiskTable::class => AutowireFactory::class,
            Table\RecommandationSetTable::class => AutowireFactory::class,
            Table\ScaleTable::class => AutowireFactory::class,
            Table\ScaleCommentTable::class => AutowireFactory::class,
            Table\ScaleImpactTypeTable::class => AutowireFactory::class,
            Table\SnapshotTable::class => AutowireFactory::class,
            Table\SoaTable::class => AutowireFactory::class,
            Table\SoaCategoryTable::class => AutowireFactory::class,
            Table\ThemeTable::class => AutowireFactory::class,
            Table\ThreatTable::class => AutowireFactory::class,
            Table\UserTable::class => AutowireFactory::class,
            Table\UserAnrTable::class => AutowireFactory::class,
            Table\VulnerabilityTable::class => AutowireFactory::class,
            Table\QuestionTable::class => AutowireFactory::class,
            Table\QuestionChoiceTable::class => AutowireFactory::class,

            //entities
            // TODO: the goal is to remove all of the mapping and create new entity in the code.
            'Monarc\FrontOffice\Model\Entity\Amv' => 'Monarc\FrontOffice\Service\Model\Entity\AmvServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\Anr' => 'Monarc\FrontOffice\Service\Model\Entity\AnrServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\AnrObjectCategory' => 'Monarc\FrontOffice\Service\Model\Entity\AnrObjectCategoryServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\Asset' => 'Monarc\FrontOffice\Service\Model\Entity\AssetServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\Delivery' => 'Monarc\FrontOffice\Service\Model\Entity\DeliveryServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\Instance' => 'Monarc\FrontOffice\Service\Model\Entity\InstanceServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\InstanceConsequence' => 'Monarc\FrontOffice\Service\Model\Entity\InstanceConsequenceServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\InstanceRisk' => 'Monarc\FrontOffice\Service\Model\Entity\InstanceRiskServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\InstanceRiskOp' => 'Monarc\FrontOffice\Service\Model\Entity\InstanceRiskOpServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\Interview' => 'Monarc\FrontOffice\Service\Model\Entity\InterviewServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\RecordActor' => 'Monarc\FrontOffice\Service\Model\Entity\RecordActorServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\RecordDataCategory' => 'Monarc\FrontOffice\Service\Model\Entity\RecordDataCategoryServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\RecordInternationalTransfer' => 'Monarc\FrontOffice\Service\Model\Entity\RecordInternationalTransferServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\RecordPersonalData' => 'Monarc\FrontOffice\Service\Model\Entity\RecordPersonalDataServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\RecordProcessor' => 'Monarc\FrontOffice\Service\Model\Entity\RecordProcessorServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\RecordRecipient' => 'Monarc\FrontOffice\Service\Model\Entity\RecordRecipientServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\Record' => 'Monarc\FrontOffice\Service\Model\Entity\RecordServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\Referential' => 'Monarc\FrontOffice\Service\Model\Entity\ReferentialServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\Measure' => 'Monarc\FrontOffice\Service\Model\Entity\MeasureServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\MeasureMeasure' => 'Monarc\FrontOffice\Service\Model\Entity\MeasureMeasureServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\MonarcObject' => 'Monarc\FrontOffice\Service\Model\Entity\MonarcObjectServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\ObjectCategory' => 'Monarc\FrontOffice\Service\Model\Entity\ObjectCategoryServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\ObjectObject' => 'Monarc\FrontOffice\Service\Model\Entity\ObjectObjectServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\PasswordToken' => 'Monarc\FrontOffice\Service\Model\Entity\PasswordTokenServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\RolfRisk' => 'Monarc\FrontOffice\Service\Model\Entity\RolfRiskServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\RolfTag' => 'Monarc\FrontOffice\Service\Model\Entity\RolfTagServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\Recommandation' => 'Monarc\FrontOffice\Service\Model\Entity\RecommandationServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\RecommandationHistoric' => 'Monarc\FrontOffice\Service\Model\Entity\RecommandationHistoricServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\RecommandationRisk' => 'Monarc\FrontOffice\Service\Model\Entity\RecommandationRiskServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\RecommandationSet' => 'Monarc\FrontOffice\Service\Model\Entity\RecommandationSetServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\Scale' => 'Monarc\FrontOffice\Service\Model\Entity\ScaleServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\ScaleComment' => 'Monarc\FrontOffice\Service\Model\Entity\ScaleCommentServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\ScaleImpactType' => 'Monarc\FrontOffice\Service\Model\Entity\ScaleImpactTypeServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\Snapshot' => 'Monarc\FrontOffice\Service\Model\Entity\SnapshotServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\Soa' => 'Monarc\FrontOffice\Service\Model\Entity\SoaServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\SoaCategory' => 'Monarc\FrontOffice\Service\Model\Entity\SoaCategoryServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\Theme' => 'Monarc\FrontOffice\Service\Model\Entity\ThemeServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\Threat' => 'Monarc\FrontOffice\Service\Model\Entity\ThreatServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\Vulnerability' => 'Monarc\FrontOffice\Service\Model\Entity\VulnerabilityServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\Question' => 'Monarc\FrontOffice\Service\Model\Entity\QuestionServiceModelEntity',
            'Monarc\FrontOffice\Model\Entity\QuestionChoice' => 'Monarc\FrontOffice\Service\Model\Entity\QuestionChoiceServiceModelEntity',

            // TODO: replace to autowiring.
            'Monarc\FrontOffice\Service\AnrService' => 'Monarc\FrontOffice\Service\AnrServiceFactory',
            'Monarc\FrontOffice\Service\AnrCoreService' => 'Monarc\FrontOffice\Service\AnrCoreServiceFactory',
            'Monarc\FrontOffice\Service\SnapshotService' => 'Monarc\FrontOffice\Service\SnapshotServiceFactory',
            Service\UserService::class => ReflectionBasedAbstractFactory::class,
            'Monarc\FrontOffice\Service\UserAnrService' => 'Monarc\FrontOffice\Service\UserAnrServiceFactory',
            Service\UserRoleService::class => AutowireFactory::class,
            'Monarc\FrontOffice\Service\AnrAssetService' => 'Monarc\FrontOffice\Service\AnrAssetServiceFactory',
            'Monarc\FrontOffice\Service\AnrAssetCommonService' => 'Monarc\FrontOffice\Service\AnrAssetCommonServiceFactory',
            'Monarc\FrontOffice\Service\AnrAmvService' => 'Monarc\FrontOffice\Service\AnrAmvServiceFactory',
            'Monarc\FrontOffice\Service\AnrInterviewService' => 'Monarc\FrontOffice\Service\AnrInterviewServiceFactory',
            'Monarc\FrontOffice\Service\AnrMeasureService' => 'Monarc\FrontOffice\Service\AnrMeasureServiceFactory',
            'Monarc\FrontOffice\Service\AnrMeasureMeasureService' => 'Monarc\FrontOffice\Service\AnrMeasureMeasureServiceFactory',
            'Monarc\FrontOffice\Service\AnrRecordActorService' => 'Monarc\FrontOffice\Service\AnrRecordActorServiceFactory',
            'Monarc\FrontOffice\Service\AnrRecordDataCategoryService' => 'Monarc\FrontOffice\Service\AnrRecordDataCategoryServiceFactory',
            'Monarc\FrontOffice\Service\AnrRecordInternationalTransferService' => 'Monarc\FrontOffice\Service\AnrRecordInternationalTransferServiceFactory',
            'Monarc\FrontOffice\Service\AnrRecordPersonalDataService' => 'Monarc\FrontOffice\Service\AnrRecordPersonalDataServiceFactory',
            'Monarc\FrontOffice\Service\AnrRecordProcessorService' => 'Monarc\FrontOffice\Service\AnrRecordProcessorServiceFactory',
            'Monarc\FrontOffice\Service\AnrRecordRecipientService' => 'Monarc\FrontOffice\Service\AnrRecordRecipientServiceFactory',
            'Monarc\FrontOffice\Service\AnrRecordService' => 'Monarc\FrontOffice\Service\AnrRecordServiceFactory',
            'Monarc\FrontOffice\Service\AnrReferentialService' => 'Monarc\FrontOffice\Service\AnrReferentialServiceFactory',
            'Monarc\FrontOffice\Service\SoaService' => 'Monarc\FrontOffice\Service\SoaServiceFactory',
            'Monarc\FrontOffice\Service\SoaCategoryService' => 'Monarc\FrontOffice\Service\SoaCategoryServiceFactory',
            'Monarc\FrontOffice\Service\AnrQuestionService' => 'Monarc\FrontOffice\Service\AnrQuestionServiceFactory',
            'Monarc\FrontOffice\Service\AnrQuestionChoiceService' => 'Monarc\FrontOffice\Service\AnrQuestionChoiceServiceFactory',
            'Monarc\FrontOffice\Service\AnrThreatService' => 'Monarc\FrontOffice\Service\AnrThreatServiceFactory',
            'Monarc\FrontOffice\Service\AnrThemeService' => 'Monarc\FrontOffice\Service\AnrThemeServiceFactory',
            'Monarc\FrontOffice\Service\AnrVulnerabilityService' => 'Monarc\FrontOffice\Service\AnrVulnerabilityServiceFactory',
            'Monarc\FrontOffice\Service\AnrRolfTagService' => 'Monarc\FrontOffice\Service\AnrRolfTagServiceFactory',
            'Monarc\FrontOffice\Service\AnrRolfRiskService' => 'Monarc\FrontOffice\Service\AnrRolfRiskServiceFactory',
            'Monarc\FrontOffice\Service\AmvService' => 'Monarc\FrontOffice\Service\AmvServiceFactory',
            Service\ClientService::class => AutowireFactory::class,
            'Monarc\FrontOffice\Service\ObjectObjectService' => 'Monarc\FrontOffice\Service\ObjectObjectServiceFactory',
            'Monarc\FrontOffice\Service\ModelService' => 'Monarc\FrontOffice\Service\ModelServiceFactory',
            'Monarc\FrontOffice\Service\AnrLibraryService' => 'Monarc\FrontOffice\Service\AnrLibraryServiceFactory',
            'Monarc\FrontOffice\Service\AnrRecommandationService' => 'Monarc\FrontOffice\Service\AnrRecommandationServiceFactory',
            'Monarc\FrontOffice\Service\AnrRecommandationHistoricService' => 'Monarc\FrontOffice\Service\AnrRecommandationHistoricServiceFactory',
            'Monarc\FrontOffice\Service\AnrRecommandationRiskService' => 'Monarc\FrontOffice\Service\AnrRecommandationRiskServiceFactory',
            'Monarc\FrontOffice\Service\AnrRecommandationSetService' => 'Monarc\FrontOffice\Service\AnrRecommandationSetServiceFactory',
            'Monarc\FrontOffice\Service\AnrScaleService' => 'Monarc\FrontOffice\Service\AnrScaleServiceFactory',
            'Monarc\FrontOffice\Service\AnrScaleTypeService' => 'Monarc\FrontOffice\Service\AnrScaleTypeServiceFactory',
            'Monarc\FrontOffice\Service\AnrScaleCommentService' => 'Monarc\FrontOffice\Service\AnrScaleCommentServiceFactory',
            'Monarc\FrontOffice\Service\AnrCheckStartedService' => 'Monarc\FrontOffice\Service\AnrCheckStartedServiceFactory',
            'Monarc\FrontOffice\Service\AnrCartoRiskService' => 'Monarc\FrontOffice\Service\AnrCartoRiskServiceFactory',
            'Monarc\FrontOffice\Service\AnrRiskService' => 'Monarc\FrontOffice\Service\AnrRiskServiceFactory',
            'Monarc\FrontOffice\Service\AnrObjectService' => 'Monarc\FrontOffice\Service\AnrObjectServiceFactory',
            'Monarc\FrontOffice\Service\AnrInstanceConsequenceService' => 'Monarc\FrontOffice\Service\AnrInstanceConsequenceServiceFactory',
            'Monarc\FrontOffice\Service\AnrInstanceRiskOpService' => 'Monarc\FrontOffice\Service\AnrInstanceRiskOpServiceFactory',
            'Monarc\FrontOffice\Service\AnrInstanceRiskService' => 'Monarc\FrontOffice\Service\AnrInstanceRiskServiceFactory',
            'Monarc\FrontOffice\Service\AnrInstanceService' => 'Monarc\FrontOffice\Service\AnrInstanceServiceFactory',
            'Monarc\FrontOffice\Service\AnrRiskOpService' => 'Monarc\FrontOffice\Service\AnrRiskOpServiceFactory',
            'Monarc\FrontOffice\Service\AnrObjectCategoryService' => 'Monarc\FrontOffice\Service\AnrObjectCategoryServiceFactory',
            'Monarc\FrontOffice\Service\AssetExportService' => 'Monarc\FrontOffice\Service\AssetExportServiceFactory',
            'Monarc\FrontOffice\Service\DeliverableGenerationService' => 'Monarc\FrontOffice\Service\DeliverableGenerationServiceFactory',
            Service\ObjectExportService::class => AutowireFactory::class,
            Service\ObjectImportService::class => AutowireFactory::class,
            Service\AssetImportService::class => AutowireFactory::class,
            Service\InstanceImportService::class => AutowireFactory::class,
            StatsAnrService::class => ReflectionBasedAbstractFactory::class,
            StatsSettingsService::class => AutowireFactory::class,

            // Providers
            StatsApiProvider::class => ReflectionBasedAbstractFactory::class,

            // Validators
            CreateUserInputValidator::class => ReflectionBasedAbstractFactory::class,
            GetStatsQueryParamsValidator::class => GetStatsQueryParamsValidatorFactory::class,
            GetProcessedStatsQueryParamsValidator::class => GetProcessedStatsQueryParamsValidatorFactory::class,
        ],
    ],

    'doctrine' => [
        'driver' => [
            'Monarc_cli_driver' => [
                'class' => AnnotationDriver::class,
                'cache' => 'array',
                'paths' => [
                    __DIR__ . '/../src/Model/Entity',
                    __DIR__ . '/../../core/src/Model/Entity',
                    __DIR__ . '/../../frontoffice/src/Model/Entity',
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
        Entity\UserRole::SUPER_ADMIN_FO => [
            'monarc_api_doc_models',
            'monarc_api_admin_users',
            'monarc_api_admin_users_roles',
            'monarc_api_admin_users_rights',
            'monarc_api_admin_user_reset_password',
            'monarc_api_user_password',
            'monarc_api_user_profile',
            'monarc_api_client_anr',
            'monarc_api_guides',
            'monarc_api_guides_items',
            'monarc_api_models',
            'monarc_api_referentials',
            'monarc_api_client',
            'monarc_api_anr_carto_risks',
            'monarc_api_global_client_anr/carto_risks',
            'monarc_api_stats',
        ],
        // Utilisateur : Accès RWD par analyse
        Entity\UserRole::USER_FO => [
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
            'monarc_api_stats',
            'monarc_api_stats_global/processed',
            'monarc_api_stats_global/general_settings',
            'monarc_api_stats_global/validate-stats-availability',
        ],
        Entity\UserRole::USER_ROLE_CEO => [
            'monarc_api_admin_users_roles',
            'monarc_api_admin_user_reset_password',
            'monarc_api_user_password',
            'monarc_api_user_profile',
            'monarc_api_client_anr',
            'monarc_api_stats',
            'monarc_api_stats_global/processed',
            'monarc_api_stats_global/anrs_settings',
            'monarc_api_stats_global/general_settings',
            'monarc_api_stats_global/validate-stats-availability',
        ],
    ],
    'activeLanguages' => ['fr'],
];
