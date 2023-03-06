<?php

use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Interop\Container\ContainerInterface;
use Laminas\Di\Container\AutowireFactory;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Mvc\Middleware\PipeSpec;
use Laminas\ServiceManager\AbstractFactory\ReflectionBasedAbstractFactory;
use Laminas\ServiceManager\Proxy\LazyServiceFactory;
use Monarc\Core\Adapter\Authentication as AdapterAuthentication;
use Monarc\Core\Service\AnrMetadatasOnInstancesExportService;
use Monarc\Core\Service\ConfigService;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\OperationalRiskScalesExportService;
use Monarc\Core\Storage\Authentication as StorageAuthentication;
use Monarc\Core\Service\SoaScaleCommentExportService;
use Monarc\FrontOffice\Controller;
use Monarc\FrontOffice\Middleware\AnrValidationMiddleware;
use Monarc\FrontOffice\CronTask\Service\CronTaskService;
use Monarc\FrontOffice\CronTask\Table\CronTaskTable;
use Monarc\FrontOffice\Import;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\FrontOffice\Model\Entity;
use Monarc\FrontOffice\Model\Table as DeprecatedTable;
use Monarc\FrontOffice\Model\Table\Factory\ClientEntityManagerFactory;
use Monarc\FrontOffice\Model\Entity\User;
use Monarc\FrontOffice\Service;
use Monarc\FrontOffice\Service\Model\Entity as ModelFactory;
use Monarc\FrontOffice\Stats\Controller\StatsAnrsSettingsController;
use Monarc\FrontOffice\Stats\Controller\StatsController;
use Monarc\FrontOffice\Stats\Controller\StatsGeneralSettingsController;
use Monarc\FrontOffice\Stats\Provider\StatsApiProvider;
use Monarc\FrontOffice\Stats\Service\StatsAnrService;
use Monarc\FrontOffice\Stats\Service\StatsSettingsService;
use Monarc\FrontOffice\Stats\Validator\GetProcessedStatsQueryParamsValidator;
use Monarc\FrontOffice\Stats\Validator\GetProcessedStatsQueryParamsValidatorFactory;
use Monarc\FrontOffice\Stats\Validator\GetStatsQueryParamsValidator;
use Monarc\FrontOffice\Stats\Validator\GetStatsQueryParamsValidatorFactory;
use Monarc\FrontOffice\Table;
use Monarc\FrontOffice\Validator\InputValidator;

$env = getenv('APPLICATION_ENV') ?: 'production';
$appConfigDir = getenv('APP_CONF_DIR') ?? '';

$dataPath = './data';
if (!empty($appConfigDir)) {
    $dataPath = $appConfigDir . '/data';
}

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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrThreatsController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrThemesController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrVulnerabilitiesController::class,
                                ),
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
                                'controller' => Controller\ApiAnrObjectsController::class,
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
                    'operational_scales' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'operational-scales[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiOperationalRisksScalesController::class,
                            ],
                        ],
                    ],
                    'operational_scales_comment' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'operational-scales/:scaleid/comments[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                                'scaleid' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiOperationalRisksScalesCommentsController::class,
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
                    'risk_owners' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'risk-owners[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrRiskOwnersController::class,
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
                                'controller' => Import\Controller\ApiAnrInstancesImportController::class,
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
                    'soa_scale_comment' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'soa-scale-comment[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiSoaScaleCommentController::class,
                            ],
                        ],
                    ],
                    'anr_metadatas_on_instances' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'metadatas-on-instances[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiAnrMetadatasOnInstancesController::class,
                            ],
                        ],
                    ],
                    'instance_metadata' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'instances/:instanceid/instances-metadata[/:id]',
                            'constraints' => [
                                'instanceid' => '[0-9]+',
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiInstanceMetadataController::class,
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
            'monarc_api_user_activate_2fa' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/api/user/activate2FA/:id',
                    'constraints' => [
                        'id' => '[0-9]+',
                    ],
                    'defaults' => [
                        'controller' => Controller\ApiUserTwoFAController::class,
                    ],
                ],
            ],
            'monarc_api_user_recovery_codes' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/api/user/recoveryCodes/:id',
                    'constraints' => [
                        'id' => '[0-9]+',
                    ],
                    'defaults' => [
                        'controller' => Controller\ApiUserRecoveryCodesController::class,
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
            Controller\ApiAnrController::class => Controller\ApiAnrControllerFactory::class,
            Controller\ApiGuidesController::class => Controller\ApiGuidesControllerFactory::class,
            Controller\ApiGuidesItemsController::class => Controller\ApiGuidesItemsControllerFactory::class,
            Controller\ApiSnapshotController::class => Controller\ApiSnapshotControllerFactory::class,
            Controller\ApiSnapshotRestoreController::class => Controller\ApiSnapshotRestoreControllerFactory::class,
            Controller\ApiConfigController::class => AutowireFactory::class,
            Controller\ApiClientsController::class => AutowireFactory::class,
            Controller\ApiModelsController::class => Controller\ApiModelsControllerFactory::class,
            Controller\ApiReferentialsController::class => AutowireFactory::class,
            Controller\ApiDuplicateAnrController::class => Controller\ApiDuplicateAnrControllerFactory::class,
            Controller\ApiUserPasswordController::class => AutowireFactory::class,
            Controller\ApiUserTwoFAController::class => AutowireFactory::class,
            Controller\ApiUserRecoveryCodesController::class => AutowireFactory::class,
            Controller\ApiUserProfileController::class => AutowireFactory::class,
            Controller\ApiAnrAssetsController::class => Controller\ApiAnrAssetsControllerFactory::class,
            Controller\ApiAnrAmvsController::class => Controller\ApiAnrAmvsControllerFactory::class,
            Controller\ApiAnrReferentialsController::class => Controller\ApiAnrReferentialsControllerFactory::class,
            Controller\ApiAnrMeasuresController::class => Controller\ApiAnrMeasuresControllerFactory::class,
            Controller\ApiAnrMeasuresMeasuresController::class
                => Controller\ApiAnrMeasuresMeasuresControllerFactory::class,
            Controller\ApiAnrObjectsController::class => Controller\ApiAnrObjectsControllerFactory::class,
            Controller\ApiAnrObjectsObjectsController::class => AutowireFactory::class,
            Controller\ApiAnrObjectsDuplicationController::class
                => Controller\ApiAnrObjectsDuplicationControllerFactory::class,
            Controller\ApiAnrQuestionsController::class => Controller\ApiAnrQuestionsControllerFactory::class,
            Controller\ApiAnrQuestionsChoicesController::class
                => Controller\ApiAnrQuestionsChoicesControllerFactory::class,
            Controller\ApiAnrThreatsController::class => Controller\ApiAnrThreatsControllerFactory::class,
            Controller\ApiAnrThemesController::class => AutowireFactory::class,
            Controller\ApiAnrVulnerabilitiesController::class => AutowireFactory::class,
            Controller\ApiAnrRolfTagsController::class => Controller\ApiAnrRolfTagsControllerFactory::class,
            Controller\ApiAnrRolfRisksController::class => Controller\ApiAnrRolfRisksControllerFactory::class,
            Controller\ApiAnrInterviewsController::class => Controller\ApiAnrInterviewsControllerFactory::class,
            Controller\ApiAnrRecommandationsController::class
                => Controller\ApiAnrRecommandationsControllerFactory::class,
            Controller\ApiAnrRecommandationsHistoricsController::class
                => Controller\ApiAnrRecommandationsHistoricsControllerFactory::class,
            Controller\ApiAnrRecommandationsRisksController::class
                => Controller\ApiAnrRecommandationsRisksControllerFactory::class,
            Controller\ApiAnrRecommandationsRisksValidateController::class
                => Controller\ApiAnrRecommandationsRisksValidateControllerFactory::class,
            Controller\ApiAnrRecommandationsSetsController::class
                => Controller\ApiAnrRecommandationsSetsControllerFactory::class,
            Controller\ApiAnrRecordActorsController::class => Controller\ApiAnrRecordActorsControllerFactory::class,
            Controller\ApiAnrRecordDuplicateController::class
                => Controller\ApiAnrRecordDuplicateControllerFactory::class,
            Controller\ApiAnrRecordDataCategoriesController::class
                => Controller\ApiAnrRecordDataCategoriesControllerFactory::class,
            Controller\ApiAnrRecordInternationalTransfersController::class
                => Controller\ApiAnrRecordInternationalTransfersControllerFactory::class,
            Controller\ApiAnrRecordPersonalDataController::class
                => Controller\ApiAnrRecordPersonalDataControllerFactory::class,
            Controller\ApiAnrRecordProcessorsController::class
                => Controller\ApiAnrRecordProcessorsControllerFactory::class,
            Controller\ApiAnrRecordRecipientsController::class
                => Controller\ApiAnrRecordRecipientsControllerFactory::class,
            Controller\ApiAnrRecordsController::class => Controller\ApiAnrRecordsControllerFactory::class,
            Controller\ApiAnrRecordsExportController::class => Controller\ApiAnrRecordsExportControllerFactory::class,
            Controller\ApiAnrRecordsImportController::class => AutowireFactory::class,
            Controller\ApiAnrTreatmentPlanController::class => Controller\ApiAnrTreatmentPlanControllerFactory::class,
            Controller\ApiSoaController::class => AutowireFactory::class,
            Controller\ApiSoaCategoryController::class => Controller\ApiSoaCategoryControllerFactory::class,
            Controller\ApiAnrScalesController::class => Controller\ApiAnrScalesControllerFactory::class,
            Controller\ApiAnrScalesTypesController::class => Controller\ApiAnrScalesTypesControllerFactory::class,
            Controller\ApiAnrScalesCommentsController::class => Controller\ApiAnrScalesCommentsControllerFactory::class,
            Controller\ApiDashboardAnrCartoRisksController::class
                => Controller\ApiDashboardAnrCartoRisksControllerFactory::class,
            Controller\ApiAnrRisksController::class => AutowireFactory::class,
            Controller\ApiAnrRiskOwnersController::class => AutowireFactory::class,
            Controller\ApiDashboardAnrRisksController::class => AutowireFactory::class,
            Controller\ApiAnrRisksOpController::class => AutowireFactory::class,
            Controller\ApiAnrLibraryController::class => Controller\ApiAnrLibraryControllerFactory::class,
            Controller\ApiAnrInstancesController::class => AutowireFactory::class,
            Controller\ApiAnrInstancesRisksController::class => Controller\ApiAnrInstancesRisksControllerFactory::class,
            Controller\ApiAnrInstancesRisksOpController::class => AutowireFactory::class,
            Import\Controller\ApiAnrInstancesImportController::class => AutowireFactory::class,
            Controller\ApiAnrInstancesExportController::class
                => Controller\ApiAnrInstancesExportControllerFactory::class,
            Controller\ApiAnrObjectsCategoriesController::class
                => Controller\ApiAnrObjectsCategoriesControllerFactory::class,
            Controller\ApiAnrObjectsExportController::class => Controller\ApiAnrObjectsExportControllerFactory::class,
            Controller\ApiAnrObjectsImportController::class => AutowireFactory::class,
            Controller\ApiAnrDeliverableController::class => AutowireFactory::class,
            Controller\ApiAnrExportController::class => AutowireFactory::class,
            Controller\ApiAnrInstancesConsequencesController::class => AutowireFactory::class,
            Controller\ApiModelVerifyLanguageController::class => AutowireFactory::class,
            Controller\ApiDeliveriesModelsController::class => Controller\ApiDeliveriesModelsControllerFactory::class,
            StatsController::class => AutowireFactory::class,
            StatsAnrsSettingsController::class => AutowireFactory::class,
            StatsGeneralSettingsController::class => AutowireFactory::class,
            Controller\ApiOperationalRisksScalesController::class => AutowireFactory::class,
            Controller\ApiOperationalRisksScalesCommentsController::class => AutowireFactory::class,
            Controller\ApiAnrMetadatasOnInstancesController::class => AutowireFactory::class,
            Controller\ApiInstanceMetadataController::class => AutowireFactory::class,
            Controller\ApiSoaScaleCommentController::class => AutowireFactory::class,
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
            AnrValidationMiddleware::class => AutowireFactory::class,

            DbCli::class => Service\Model\DbCliFactory::class,

            DeprecatedTable\AnrObjectCategoryTable::class => AutowireFactory::class,
            DeprecatedTable\AnrTable::class => AutowireFactory::class,
            DeprecatedTable\InstanceTable::class => AutowireFactory::class,
            DeprecatedTable\DeliveryTable::class => AutowireFactory::class,
            DeprecatedTable\InstanceConsequenceTable::class => AutowireFactory::class,
            DeprecatedTable\InstanceRiskTable::class => AutowireFactory::class,
            DeprecatedTable\InstanceRiskOpTable::class => AutowireFactory::class,
            DeprecatedTable\InterviewTable::class => AutowireFactory::class,
            DeprecatedTable\MeasureTable::class => AutowireFactory::class,
            DeprecatedTable\MeasureMeasureTable::class => AutowireFactory::class,
            DeprecatedTable\MonarcObjectTable::class => AutowireFactory::class,
            DeprecatedTable\ObjectCategoryTable::class => AutowireFactory::class,
            DeprecatedTable\ObjectObjectTable::class => AutowireFactory::class,
            DeprecatedTable\RolfRiskTable::class => AutowireFactory::class,
            DeprecatedTable\RolfTagTable::class => AutowireFactory::class,
            DeprecatedTable\RecordActorTable::class => AutowireFactory::class,
            DeprecatedTable\RecordDataCategoryTable::class => AutowireFactory::class,
            DeprecatedTable\RecordInternationalTransferTable::class => AutowireFactory::class,
            DeprecatedTable\RecordPersonalDataTable::class => AutowireFactory::class,
            DeprecatedTable\RecordProcessorTable::class => AutowireFactory::class,
            DeprecatedTable\RecordRecipientTable::class => AutowireFactory::class,
            DeprecatedTable\RecordTable::class => AutowireFactory::class,
            DeprecatedTable\ReferentialTable::class => AutowireFactory::class,
            DeprecatedTable\RecommandationTable::class => AutowireFactory::class,
            DeprecatedTable\RecommendationHistoricTable::class => AutowireFactory::class,
            DeprecatedTable\RecommandationRiskTable::class => AutowireFactory::class,
            DeprecatedTable\RecommandationSetTable::class => AutowireFactory::class,
            DeprecatedTable\ScaleTable::class => AutowireFactory::class,
            DeprecatedTable\ScaleCommentTable::class => AutowireFactory::class,
            DeprecatedTable\ScaleImpactTypeTable::class => AutowireFactory::class,
            DeprecatedTable\SnapshotTable::class => AutowireFactory::class,
            DeprecatedTable\SoaTable::class => AutowireFactory::class,
            DeprecatedTable\SoaCategoryTable::class => AutowireFactory::class,
            DeprecatedTable\QuestionTable::class => AutowireFactory::class,
            DeprecatedTable\QuestionChoiceTable::class => AutowireFactory::class,
            Table\AmvTable::class => ClientEntityManagerFactory::class,
            Table\AssetTable::class => ClientEntityManagerFactory::class,
            Table\ClientTable::class => ClientEntityManagerFactory::class,
            Table\ThemeTable::class => ClientEntityManagerFactory::class,
            Table\ThreatTable::class => ClientEntityManagerFactory::class,
            Table\UserTable::class => ClientEntityManagerFactory::class,
            Table\UserAnrTable::class => ClientEntityManagerFactory::class,
            Table\VulnerabilityTable::class => ClientEntityManagerFactory::class,
            Table\OperationalRiskScaleTable::class => ClientEntityManagerFactory::class,
            Table\OperationalRiskScaleTypeTable::class => ClientEntityManagerFactory::class,
            Table\OperationalRiskScaleCommentTable::class => ClientEntityManagerFactory::class,
            Table\OperationalInstanceRiskScaleTable::class => ClientEntityManagerFactory::class,
            Table\TranslationTable::class => ClientEntityManagerFactory::class,
            Table\InstanceRiskOwnerTable::class => ClientEntityManagerFactory::class,
            DeprecatedTable\AnrMetadatasOnInstancesTable::class => ClientEntityManagerFactory::class,
            DeprecatedTable\InstanceMetadataTable::class => ClientEntityManagerFactory::class,
            DeprecatedTable\SoaScaleCommentTable::class => ClientEntityManagerFactory::class,
            DeprecatedTable\ClientModelTable::class => ClientEntityManagerFactory::class,
            CronTaskTable::class => ClientEntityManagerFactory::class,

            //entities
            // TODO: the goal is to remove all of the mapping and create new entity in the code.
            Entity\Anr::class => ModelFactory\AnrServiceModelEntity::class,
            Entity\Delivery::class => ModelFactory\DeliveryServiceModelEntity::class,
            Entity\InstanceRisk::class => ModelFactory\InstanceRiskServiceModelEntity::class,
            Entity\InstanceRiskOp::class => ModelFactory\InstanceRiskOpServiceModelEntity::class,
            Entity\Interview::class => ModelFactory\InterviewServiceModelEntity::class,
            Entity\RecordActor::class => ModelFactory\RecordActorServiceModelEntity::class,
            Entity\RecordDataCategory::class => ModelFactory\RecordDataCategoryServiceModelEntity::class,
            Entity\RecordInternationalTransfer::class
                => ModelFactory\RecordInternationalTransferServiceModelEntity::class,
            Entity\RecordPersonalData::class => ModelFactory\RecordPersonalDataServiceModelEntity::class,
            Entity\RecordProcessor::class => ModelFactory\RecordProcessorServiceModelEntity::class,
            Entity\RecordRecipient::class => ModelFactory\RecordRecipientServiceModelEntity::class,
            Entity\Record::class => ModelFactory\RecordServiceModelEntity::class,
            Entity\Referential::class => ModelFactory\ReferentialServiceModelEntity::class,
            Entity\Measure::class => ModelFactory\MeasureServiceModelEntity::class,
            Entity\MeasureMeasure::class => ModelFactory\MeasureMeasureServiceModelEntity::class,
            Entity\RolfRisk::class => ModelFactory\RolfRiskServiceModelEntity::class,
            Entity\RolfTag::class => ModelFactory\RolfTagServiceModelEntity::class,
            Entity\Recommandation::class => ModelFactory\RecommandationServiceModelEntity::class,
            Entity\RecommandationHistoric::class => ModelFactory\RecommandationHistoricServiceModelEntity::class,
            Entity\RecommandationRisk::class => ModelFactory\RecommandationRiskServiceModelEntity::class,
            Entity\RecommandationSet::class => ModelFactory\RecommandationSetServiceModelEntity::class,
            Entity\Scale::class => ModelFactory\ScaleServiceModelEntity::class,
            Entity\ScaleComment::class => ModelFactory\ScaleCommentServiceModelEntity::class,
            Entity\ScaleImpactType::class => ModelFactory\ScaleImpactTypeServiceModelEntity::class,
            Entity\Snapshot::class => ModelFactory\SnapshotServiceModelEntity::class,
            Entity\Soa::class => ModelFactory\SoaServiceModelEntity::class,
            Entity\SoaCategory::class => ModelFactory\SoaCategoryServiceModelEntity::class,
            Entity\Question::class => ModelFactory\QuestionServiceModelEntity::class,
            Entity\QuestionChoice::class => ModelFactory\QuestionChoiceServiceModelEntity::class,

            // TODO: replace to autowiring.
            Service\AnrService::class => Service\AnrServiceFactory::class,
            Service\AnrCoreService::class => Service\AnrCoreServiceFactory::class,
            Service\SnapshotService::class => Service\SnapshotServiceFactory::class,
            Service\UserService::class => ReflectionBasedAbstractFactory::class,
            Service\UserRoleService::class => AutowireFactory::class,
            Service\AnrAssetService::class => AutowireFactory::class,
            Service\AnrAssetCommonService::class => Service\AnrAssetCommonServiceFactory::class,
            Service\AnrAmvService::class => AutowireFactory::class,
            Service\AnrInterviewService::class => Service\AnrInterviewServiceFactory::class,
            Service\AnrMeasureService::class => Service\AnrMeasureServiceFactory::class,
            Service\AnrMeasureMeasureService::class => Service\AnrMeasureMeasureServiceFactory::class,
            Service\AnrRecordActorService::class => Service\AnrRecordActorServiceFactory::class,
            Service\AnrRecordDataCategoryService::class => Service\AnrRecordDataCategoryServiceFactory::class,
            Service\AnrRecordInternationalTransferService::class
                => Service\AnrRecordInternationalTransferServiceFactory::class,
            Service\AnrRecordPersonalDataService::class => Service\AnrRecordPersonalDataServiceFactory::class,
            Service\AnrRecordProcessorService::class => Service\AnrRecordProcessorServiceFactory::class,
            Service\AnrRecordRecipientService::class => Service\AnrRecordRecipientServiceFactory::class,
            Service\AnrRecordService::class => Service\AnrRecordServiceFactory::class,
            Service\AnrReferentialService::class => Service\AnrReferentialServiceFactory::class,
            Service\SoaService::class => Service\SoaServiceFactory::class,
            Service\SoaCategoryService::class => Service\SoaCategoryServiceFactory::class,
            Service\AnrQuestionService::class => Service\AnrQuestionServiceFactory::class,
            Service\AnrQuestionChoiceService::class => Service\AnrQuestionChoiceServiceFactory::class,
            Service\AnrThreatService::class => AutowireFactory::class,
            Service\AnrThemeService::class => AutowireFactory::class,
            Service\AnrVulnerabilityService::class => AutowireFactory::class,
            Service\AnrRolfTagService::class => Service\AnrRolfTagServiceFactory::class,
            Service\AnrRolfRiskService::class => Service\AnrRolfRiskServiceFactory::class,
            Service\ClientService::class => AutowireFactory::class,
            Service\AnrRecommandationService::class => Service\AnrRecommandationServiceFactory::class,
            Service\AnrRecommandationHistoricService::class => Service\AnrRecommandationHistoricServiceFactory::class,
            Service\AnrRecommandationRiskService::class => Service\AnrRecommandationRiskServiceFactory::class,
            Service\AnrRecommandationSetService::class => Service\AnrRecommandationSetServiceFactory::class,
            Service\AnrScaleService::class => Service\AnrScaleServiceFactory::class,
            Service\AnrScaleTypeService::class => Service\AnrScaleTypeServiceFactory::class,
            Service\AnrScaleCommentService::class => Service\AnrScaleCommentServiceFactory::class,
            Service\AnrCheckStartedService::class => AutowireFactory::class,
            Service\AnrCartoRiskService::class => Service\AnrCartoRiskServiceFactory::class,
            Service\AnrObjectService::class => AutowireFactory::class,
            Service\AnrObjectObjectService::class => AutowireFactory::class,
            Service\AnrInstanceConsequenceService::class => AutowireFactory::class,
            Service\AnrInstanceRiskOpService::class => AutowireFactory::class,
            Service\AnrInstanceRiskService::class => Service\AnrInstanceRiskServiceFactory::class,
            Service\AnrInstanceService::class => AutowireFactory::class,
            Service\AnrObjectCategoryService::class => Service\AnrObjectCategoryServiceFactory::class,
            Service\AssetExportService::class => Service\AssetExportServiceFactory::class,
            Service\DeliverableGenerationService::class => Service\DeliverableGenerationServiceFactory::class,
            Service\ObjectExportService::class => AutowireFactory::class,
            Import\Service\ObjectImportService::class => AutowireFactory::class,
            Import\Service\AssetImportService::class => AutowireFactory::class,
            Import\Service\InstanceImportService::class => AutowireFactory::class,
            StatsAnrService::class => ReflectionBasedAbstractFactory::class,
            StatsSettingsService::class => AutowireFactory::class,
            Service\OperationalRiskScaleService::class => AutowireFactory::class,
            Service\InstanceRiskOwnerService::class => AutowireFactory::class,
            Service\OperationalRiskScaleCommentService::class => AutowireFactory::class,
            OperationalRiskScalesExportService::class => static function (ContainerInterface $container, $serviceName) {
                return new OperationalRiskScalesExportService(
                    $container->get(Table\OperationalRiskScaleTable::class),
                    $container->get(Table\TranslationTable::class),
                    $container->get(ConfigService::class),
                );
            },
            AnrMetadatasOnInstancesExportService::class => static function (
                ContainerInterface $container,
                $serviceName
            ) {
                return new AnrMetadatasOnInstancesExportService(
                    // TODO: the table AnrMetadatasOnInstancesTable is renamed.
                    $container->get(Table\AnrMetadatasOnInstancesTable::class),
                    $container->get(Table\TranslationTable::class),
                    $container->get(ConfigService::class),
                );
            },
            Service\AnrMetadatasOnInstancesService::class => AutowireFactory::class,
            Service\InstanceMetadataService::class => AutowireFactory::class,
            Service\SoaScaleCommentService::class => AutowireFactory::class,
            SoaScaleCommentExportService::class => static function (
                ContainerInterface $container,
                $serviceName
            ) {
                return new SoaScaleCommentExportService(
                    $container->get(DeprecatedTable\SoaScaleCommentTable::class),
                    $container->get(Table\TranslationTable::class),
                    $container->get(ConfigService::class),
                );
            },
            CronTaskService::class => AutowireFactory::class,

            // Helpers
            Import\Helper\ImportCacheHelper::class => AutowireFactory::class,

            /* Authentication */
            StorageAuthentication::class => static function (ContainerInterface $container, $serviceName) {
                return new StorageAuthentication(
                    $container->get(Table\UserTokenTable::class),
                    $container->get('config'),
                );
            },
            AdapterAuthentication::class => static function (ContainerInterface $container, $serviceName) {
                return new AdapterAuthentication(
                    $container->get(Table\UserTable::class),
                    $container->get(ConfigService::class)
                );
            },
            ConnectedUserService::class => static function (ContainerInterface $container, $serviceName) {
                return new ConnectedUserService(
                    $container->get(Request::class),
                    $container->get(Table\UserTokenTable::class)
                );
            },

            // Providers
            StatsApiProvider::class => ReflectionBasedAbstractFactory::class,

            // Validators
            InputValidator\User\PostUserDataInputValidator::class => ReflectionBasedAbstractFactory::class,
            GetStatsQueryParamsValidator::class => GetStatsQueryParamsValidatorFactory::class,
            GetProcessedStatsQueryParamsValidator::class => GetProcessedStatsQueryParamsValidatorFactory::class,

            // Commands
            Import\Command\ImportAnalysesCommand::class => static function (
                ContainerInterface $container,
                $serviceName
            ) {
                /** @var ConnectedUserService $connectedUserService */
                $connectedUserService = $container->get(ConnectedUserService::class);
                $connectedUserService->setConnectedUser(new User([
                    'firstname' => 'System',
                    'lastname' => 'System',
                    'email' => 'System',
                    'language' => 1,
                    'mospApiKey' => '',
                    'creator' => 'System',
                    'role' => [Entity\UserRole::USER_ROLE_SYSTEM],
                ]));

                return new Import\Command\ImportAnalysesCommand(
                    $container->get(CronTaskService::class),
                    $container->get(Import\Service\InstanceImportService::class),
                    $container->get(DeprecatedTable\AnrTable::class),
                    $container->get(Service\SnapshotService::class)
                );
            },
        ],
        'lazy_services' => [
            'class_map' => [
                Table\UserTokenTable::class => Table\UserTokenTable::class,
                Service\AnrInstanceService::class => Service\AnrInstanceService::class
            ],
            'proxies_target_dir' => $dataPath . '/LazyServices/Proxy',
            'write_proxy_files' => $env === 'production',
        ],
        'delegators' => [
            Table\UserTokenTable::class => [
                LazyServiceFactory::class,
            ],
            Service\AnrInstanceService::class => [
                LazyServiceFactory::class,
            ],
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
        // Super Admin : Management of users (and guides, models, referentials, etc.)
        Entity\UserRole::SUPER_ADMIN_FO => [
            'monarc_api_doc_models',
            'monarc_api_admin_users',
            'monarc_api_admin_users_roles',
            'monarc_api_admin_users_rights',
            'monarc_api_admin_user_reset_password',
            'monarc_api_user_password',
            'monarc_api_user_activate_2fa',
            'monarc_api_user_recovery_codes',
            'monarc_api_user_profile',
            'monarc_api_client_anr',
            'monarc_api_guides',
            'monarc_api_guides_items',
            'monarc_api_models',
            'monarc_api_referentials',
            'monarc_api_client',
            'monarc_api_global_client_anr/carto_risks',
            'monarc_api_global_client_anr/risk_owners',
            'monarc_api_stats',
            'monarc_api_stats_global/processed',
            'monarc_api_stats_global/general_settings',
            'monarc_api_stats_global/validate-stats-availability',
        ],
        // User : RWD access per analysis
        Entity\UserRole::USER_FO => [
            'monarc_api_doc_models',
            'monarc_api_models',
            'monarc_api_referentials',
            'monarc_api_admin_users_roles',
            'monarc_api_global_client_anr/anr_metadatas_on_instances',
            'monarc_api_global_client_anr/instance_metadata',
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
            'monarc_api_global_client_anr/questions',
            'monarc_api_global_client_anr/questions_choices',
            'monarc_api_global_client_anr/soa',
            'monarc_api_global_client_anr/soa_scale_comment',
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
            'monarc_api_user_activate_2fa',
            'monarc_api_user_recovery_codes',
            'monarc_api_model_verify_language',
            'monarc_api_global_client_anr/risk_owners',
            'monarc_api_global_client_anr/carto_risks',
            'monarc_api_global_client_anr/scales',
            'monarc_api_global_client_anr/scales_types',
            'monarc_api_global_client_anr/scales_comments',
            'monarc_api_global_client_anr/operational_scales',
            'monarc_api_global_client_anr/operational_scales_comment',
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
            'monarc_api_user_activate_2fa',
            'monarc_api_user_recovery_codes',
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
