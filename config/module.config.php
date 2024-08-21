<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Interop\Container\ContainerInterface;
use Laminas\Di\Container\AutowireFactory;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Mvc\Middleware\PipeSpec;
use Laminas\ServiceManager\AbstractFactory\ReflectionBasedAbstractFactory;
use Laminas\ServiceManager\Proxy\LazyServiceFactory;
use Monarc\Core\Adapter\Authentication as AdapterAuthentication;
use Monarc\Core\Service\ConfigService;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\Helper\ScalesCacheHelper;
use Monarc\Core\Storage\Authentication as StorageAuthentication;
use Monarc\Core\Table\Factory\ClientEntityManagerFactory;
use Monarc\Core\Validator\InputValidator as CoreInputValidator;
use Monarc\FrontOffice\Controller;
use Monarc\FrontOffice\CronTask;
use Monarc\FrontOffice\Export;
use Monarc\FrontOffice\Import;
use Monarc\FrontOffice\Middleware\AnrValidationMiddleware;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Model\Table as DeprecatedTable;
use Monarc\FrontOffice\Service;
use Monarc\FrontOffice\Service\Model\Entity as ModelFactory;
use Monarc\FrontOffice\Stats;
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
                        'action' => 'resetPassword',
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
                    'route' => '/api/referentials',
                    'defaults' => [
                        'controller' => Controller\ApiCoreReferentialsController::class,
                    ],
                ],
            ],

            'monarc_api_duplicate_client_anr' => [
                'type' => 'literal',
                'options' => [
                    'route' => '/api/client-duplicate-anr',
                    'defaults' => [
                        'controller' => PipeSpec::class,
                        'middleware' => new PipeSpec(
                            AnrValidationMiddleware::class,
                            Controller\ApiDuplicateAnrController::class,
                        ),
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
                        'controller' => PipeSpec::class,
                        'middleware' => new PipeSpec(
                            AnrValidationMiddleware::class,
                            Controller\ApiAnrController::class,
                        ),
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
                        'controller' => PipeSpec::class,
                        'middleware' => new PipeSpec(Controller\ApiGuidesController::class),
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
                        'controller' => PipeSpec::class,
                        'middleware' => new PipeSpec(Controller\ApiGuidesItemsController::class),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Export\Controller\ApiAnrExportController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrAssetsController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrAmvsController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrReferentialsController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrMeasuresController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrMeasuresLinksController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrRolfTagsController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrRolfRisksController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrObjectsController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrObjectsController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Export\Controller\ApiAnrObjectsExportController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Import\Controller\ApiAnrObjectsImportController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrInterviewsController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrScalesController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiOperationalRisksScalesController::class,
                                ),
                            ],
                        ],
                    ],
                    'operational_scales_comment' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'operational-scales/:scaleid/comments[/:id]',
                            'constraints' => [
                                'scaleid' => '[0-9]+',
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiOperationalRisksScalesCommentsController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrScalesTypesController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrScalesCommentsController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrQuestionsController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrQuestionsChoicesController::class,
                                ),
                            ],
                        ],
                    ],
                    'recommendations' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'recommendations[/:id]',
                            'constraints' => [
                                'id' => '[a-f0-9-]*',
                            ],
                            'defaults' => [
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrRecommendationsController::class,
                                ),
                            ],
                        ],
                    ],
                    'recommendations_history' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'recommendations-history[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrRecommendationsHistoryController::class,
                                ),
                            ],
                        ],
                    ],
                    'recommendations_risks' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'recommendations-risks[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrRecommendationsRisksController::class,
                                ),
                            ],
                        ],
                    ],
                    'recommendations_risks_validate' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'recommendations-risks[/:id]/validate',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrRecommendationsRisksValidateController::class,
                                ),
                            ],
                        ],
                    ],
                    'recommendations_sets' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'recommendations-sets[/:id]',
                            'constraints' => [
                                'id' => '[a-f0-9-]*',
                            ],
                            'defaults' => [
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrRecommendationsSetsController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrRecordsController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrRecordActorsController::class,
                                ),
                            ],
                        ],
                    ],
                    'record_data_categories' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'record-data-categories',
                            'defaults' => [
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrRecordDataCategoriesController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrRecordInternationalTransfersController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrRecordPersonalDataController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrRecordProcessorsController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrRecordRecipientsController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrRecordsExportController::class,
                                ),
                            ],
                        ],
                    ],
                    'records_export' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'records/export',
                            'defaults' => [
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrRecordsExportController::class,
                                ),
                            ],
                        ],
                    ],
                    'record_import' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'records/import',
                            'defaults' => [
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrRecordsImportController::class,
                                ),
                            ],
                        ],
                    ],
                    'record_duplicate' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'records/duplicate',
                            'defaults' => [
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrRecordDuplicateController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiDashboardAnrCartoRisksController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrRiskOwnersController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrRisksController::class,
                                ),
                            ],
                        ],
                    ],
                    'dashboard' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'risks-dashboard[/:id]',
                            'defaults' => [
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiDashboardAnrRisksController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrRisksOpController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrLibraryController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrTreatmentPlanController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiSoaController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiSoaCategoryController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrInstancesController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Export\Controller\ApiAnrInstancesExportController::class,
                                ),
                            ],
                        ],
                    ],
                    'instance_import' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'instances/import',
                            'constraints' => [],
                            'defaults' => [
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Import\Controller\ApiAnrInstancesImportController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrInstancesRisksController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrInstancesRisksOpController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrInstancesConsequencesController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiSoaScaleCommentController::class,
                                ),
                            ],
                        ],
                    ],
                    'anr_instance_metadata_field' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'anr-instances-metadata-fields[/:id]',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrInstancesMetadataFieldsController::class,
                                ),
                            ],
                        ],
                    ],
                    'instance_metadata' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'instances/:instanceid/metadata[/:id]',
                            'constraints' => [
                                'instanceid' => '[0-9]+',
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiInstanceMetadataController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrObjectsCategoriesController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiSnapshotController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiSnapshotRestoreController::class,
                                ),
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
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrDeliverableController::class,
                                ),
                            ],
                        ],
                    ],
                    'objects_objects' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'objects-objects[/:id]',
                            'defaults' => [
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrObjectsObjectsController::class,
                                ),
                            ],
                        ],
                    ],
                    'objects_duplication' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'objects-duplication',
                            'defaults' => [
                                'controller' => PipeSpec::class,
                                'middleware' => new PipeSpec(
                                    AnrValidationMiddleware::class,
                                    Controller\ApiAnrObjectsDuplicationController::class,
                                ),
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
                        'controller' => PipeSpec::class,
                        'middleware' => new PipeSpec(Controller\ApiDeliveriesModelsController::class),
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
                        'controller' => PipeSpec::class,
                        'middleware' => new PipeSpec(Controller\ApiUserTwoFAController::class),
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
                        'controller' => PipeSpec::class,
                        'middleware' => new PipeSpec(Controller\ApiUserRecoveryCodesController::class),
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
                        'controller' => Stats\Controller\StatsController::class,
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
                                'controller' => Stats\Controller\StatsController::class,
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
                                'controller' => Stats\Controller\StatsAnrsSettingsController::class,
                            ],
                        ],
                    ],
                    'general_settings' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'general-settings[/]',
                            'verb' => 'get,patch',
                            'defaults' => [
                                'controller' => Stats\Controller\StatsGeneralSettingsController::class,
                            ],
                        ],
                    ],
                    'validate-stats-availability' => [
                        'type' => 'segment',
                        'options' => [
                            'route' => 'validate-stats-availability[/]',
                            'verb' => 'get',
                            'defaults' => [
                                'controller' => Stats\Controller\StatsController::class,
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
            Controller\ApiGuidesController::class => AutowireFactory::class,
            Controller\ApiGuidesItemsController::class => AutowireFactory::class,
            Controller\ApiModelsController::class => AutowireFactory::class,
            Controller\ApiDuplicateAnrController::class => AutowireFactory::class,
            Controller\ApiAnrReferentialsController::class => AutowireFactory::class,
            Controller\ApiAnrMeasuresController::class => AutowireFactory::class,
            Controller\ApiAnrMeasuresLinksController::class => AutowireFactory::class,
            Controller\ApiAnrQuestionsController::class => AutowireFactory::class,
            Controller\ApiAnrQuestionsChoicesController::class => AutowireFactory::class,
            Controller\ApiAnrRolfTagsController::class => AutowireFactory::class,
            Controller\ApiAnrRolfRisksController::class => AutowireFactory::class,
            Controller\ApiAnrInterviewsController::class => AutowireFactory::class,
            Controller\ApiAnrRecordActorsController::class => AutowireFactory::class,
            Controller\ApiAnrRecordDuplicateController::class => AutowireFactory::class,
            Controller\ApiAnrRecordDataCategoriesController::class => AutowireFactory::class,
            Controller\ApiAnrRecordInternationalTransfersController::class => AutowireFactory::class,
            Controller\ApiAnrRecordPersonalDataController::class => AutowireFactory::class,
            Controller\ApiAnrRecordProcessorsController::class => AutowireFactory::class,
            Controller\ApiAnrRecordRecipientsController::class => AutowireFactory::class,
            Controller\ApiAnrRecordsController::class => AutowireFactory::class,
            Controller\ApiAnrRecordsExportController::class => AutowireFactory::class,
            Controller\ApiSoaCategoryController::class => AutowireFactory::class,
            Controller\ApiDashboardAnrCartoRisksController::class => AutowireFactory::class,
            Controller\ApiDeliveriesModelsController::class => AutowireFactory::class,
            Controller\ApiAnrTreatmentPlanController::class => AutowireFactory::class,
            Controller\ApiAnrRecommendationsController::class => AutowireFactory::class,
            Controller\ApiAnrRecommendationsHistoryController::class => AutowireFactory::class,
            Controller\ApiAnrRecommendationsRisksController::class => AutowireFactory::class,
            Controller\ApiAnrRecommendationsRisksValidateController::class => AutowireFactory::class,
            Controller\ApiAnrRecommendationsSetsController::class => AutowireFactory::class,
            Controller\ApiSnapshotRestoreController::class => AutowireFactory::class,
            Controller\ApiAdminPasswordsController::class => AutowireFactory::class,
            Controller\ApiAdminUsersController::class => AutowireFactory::class,
            Controller\ApiAdminUsersRolesController::class => AutowireFactory::class,
            Controller\ApiAnrController::class => AutowireFactory::class,
            Controller\ApiConfigController::class => AutowireFactory::class,
            Controller\ApiClientsController::class => AutowireFactory::class,
            Controller\ApiCoreReferentialsController::class => AutowireFactory::class,
            Controller\ApiUserPasswordController::class => AutowireFactory::class,
            Controller\ApiUserTwoFAController::class => AutowireFactory::class,
            Controller\ApiUserRecoveryCodesController::class => AutowireFactory::class,
            Controller\ApiUserProfileController::class => AutowireFactory::class,
            Controller\ApiAnrAssetsController::class => AutowireFactory::class,
            Controller\ApiAnrAmvsController::class => AutowireFactory::class,
            Controller\ApiAnrObjectsController::class => AutowireFactory::class,
            Controller\ApiAnrObjectsObjectsController::class => AutowireFactory::class,
            Controller\ApiAnrObjectsDuplicationController::class => AutowireFactory::class,
            Controller\ApiAnrThreatsController::class => AutowireFactory::class,
            Controller\ApiAnrThemesController::class => AutowireFactory::class,
            Controller\ApiAnrVulnerabilitiesController::class => AutowireFactory::class,
            Controller\ApiAnrRecordsImportController::class => AutowireFactory::class,
            Controller\ApiSoaController::class => AutowireFactory::class,
            Controller\ApiAnrScalesController::class => AutowireFactory::class,
            Controller\ApiAnrScalesTypesController::class => AutowireFactory::class,
            Controller\ApiAnrScalesCommentsController::class => AutowireFactory::class,
            Controller\ApiAnrRisksController::class => AutowireFactory::class,
            Controller\ApiAnrRiskOwnersController::class => AutowireFactory::class,
            Controller\ApiDashboardAnrRisksController::class => AutowireFactory::class,
            Controller\ApiAnrRisksOpController::class => AutowireFactory::class,
            Controller\ApiAnrLibraryController::class => AutowireFactory::class,
            Controller\ApiAnrInstancesController::class => AutowireFactory::class,
            Controller\ApiAnrInstancesRisksController::class => AutowireFactory::class,
            Controller\ApiAnrInstancesRisksOpController::class => AutowireFactory::class,
            Controller\ApiSnapshotController::class => AutowireFactory::class,
            Controller\ApiAnrObjectsCategoriesController::class => AutowireFactory::class,
            Controller\ApiAnrDeliverableController::class => AutowireFactory::class,
            Controller\ApiAnrInstancesConsequencesController::class => AutowireFactory::class,
            Controller\ApiModelVerifyLanguageController::class => AutowireFactory::class,
            Controller\ApiOperationalRisksScalesController::class => AutowireFactory::class,
            Controller\ApiOperationalRisksScalesCommentsController::class => AutowireFactory::class,
            Controller\ApiAnrInstancesMetadataFieldsController::class => AutowireFactory::class,
            Controller\ApiInstanceMetadataController::class => AutowireFactory::class,
            Controller\ApiSoaScaleCommentController::class => AutowireFactory::class,
            Export\Controller\ApiAnrExportController::class => AutowireFactory::class,
            Export\Controller\ApiAnrInstancesExportController::class => AutowireFactory::class,
            Export\Controller\ApiAnrObjectsExportController::class => AutowireFactory::class,
            Import\Controller\ApiAnrInstancesImportController::class => AutowireFactory::class,
            Import\Controller\ApiAnrObjectsImportController::class => AutowireFactory::class,
            Stats\Controller\StatsController::class => AutowireFactory::class,
            Stats\Controller\StatsAnrsSettingsController::class => AutowireFactory::class,
            Stats\Controller\StatsGeneralSettingsController::class => AutowireFactory::class,
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

            DeprecatedTable\InterviewTable::class => AutowireFactory::class,
            DeprecatedTable\RecordActorTable::class => AutowireFactory::class,
            DeprecatedTable\RecordDataCategoryTable::class => AutowireFactory::class,
            DeprecatedTable\RecordInternationalTransferTable::class => AutowireFactory::class,
            DeprecatedTable\RecordPersonalDataTable::class => AutowireFactory::class,
            DeprecatedTable\RecordProcessorTable::class => AutowireFactory::class,
            DeprecatedTable\RecordRecipientTable::class => AutowireFactory::class,
            DeprecatedTable\RecordTable::class => AutowireFactory::class,
            DeprecatedTable\QuestionTable::class => AutowireFactory::class,
            DeprecatedTable\QuestionChoiceTable::class => AutowireFactory::class,
            Table\AnrTable::class => ClientEntityManagerFactory::class,
            Table\AnrInstanceMetadataFieldTable::class => ClientEntityManagerFactory::class,
            Table\AmvTable::class => ClientEntityManagerFactory::class,
            Table\AssetTable::class => ClientEntityManagerFactory::class,
            Table\DeliveryTable::class => ClientEntityManagerFactory::class,
            Table\InstanceTable::class => ClientEntityManagerFactory::class,
            Table\InstanceRiskTable::class => ClientEntityManagerFactory::class,
            Table\InstanceRiskOpTable::class => ClientEntityManagerFactory::class,
            Table\InstanceMetadataTable::class => ClientEntityManagerFactory::class,
            Table\InstanceRiskOwnerTable::class => ClientEntityManagerFactory::class,
            Table\InstanceConsequenceTable::class => ClientEntityManagerFactory::class,
            Table\ScaleTable::class => ClientEntityManagerFactory::class,
            Table\ScaleCommentTable::class => ClientEntityManagerFactory::class,
            Table\ScaleImpactTypeTable::class => ClientEntityManagerFactory::class,
            Table\ClientTable::class => ClientEntityManagerFactory::class,
            Table\MonarcObjectTable::class => ClientEntityManagerFactory::class,
            Table\MeasureTable::class => ClientEntityManagerFactory::class,
            Table\ObjectCategoryTable::class => ClientEntityManagerFactory::class,
            Table\ObjectObjectTable::class => ClientEntityManagerFactory::class,
            Table\OperationalRiskScaleTable::class => ClientEntityManagerFactory::class,
            Table\OperationalRiskScaleTypeTable::class => ClientEntityManagerFactory::class,
            Table\OperationalRiskScaleCommentTable::class => ClientEntityManagerFactory::class,
            Table\OperationalInstanceRiskScaleTable::class => ClientEntityManagerFactory::class,
            Table\RecommendationTable::class => ClientEntityManagerFactory::class,
            Table\RecommendationHistoryTable::class => ClientEntityManagerFactory::class,
            Table\RecommendationRiskTable::class => ClientEntityManagerFactory::class,
            Table\RecommendationSetTable::class => ClientEntityManagerFactory::class,
            Table\RolfRiskTable::class => ClientEntityManagerFactory::class,
            Table\RolfTagTable::class => ClientEntityManagerFactory::class,
            Table\ReferentialTable::class => ClientEntityManagerFactory::class,
            Table\SoaCategoryTable::class => ClientEntityManagerFactory::class,
            Table\SoaTable::class => ClientEntityManagerFactory::class,
            Table\SnapshotTable::class => ClientEntityManagerFactory::class,
            Table\SoaScaleCommentTable::class => ClientEntityManagerFactory::class,
            Table\ThemeTable::class => ClientEntityManagerFactory::class,
            Table\ThreatTable::class => ClientEntityManagerFactory::class,
            Table\UserTable::class => ClientEntityManagerFactory::class,
            Table\UserAnrTable::class => ClientEntityManagerFactory::class,
            Table\UserTokenTable::class => ClientEntityManagerFactory::class,
            Table\VulnerabilityTable::class => ClientEntityManagerFactory::class,
            CronTask\Table\CronTaskTable::class => ClientEntityManagerFactory::class,

            // TODO: the goal is to remove all of the mapping and create new entity in the code.
            Entity\Interview::class => ModelFactory\InterviewServiceModelEntity::class,
            Entity\RecordActor::class => ModelFactory\RecordActorServiceModelEntity::class,
            Entity\RecordDataCategory::class => ModelFactory\RecordDataCategoryServiceModelEntity::class,
            Entity\RecordInternationalTransfer::class
                => ModelFactory\RecordInternationalTransferServiceModelEntity::class,
            Entity\RecordPersonalData::class => ModelFactory\RecordPersonalDataServiceModelEntity::class,
            Entity\RecordProcessor::class => ModelFactory\RecordProcessorServiceModelEntity::class,
            Entity\RecordRecipient::class => ModelFactory\RecordRecipientServiceModelEntity::class,
            Entity\Record::class => ModelFactory\RecordServiceModelEntity::class,
            Entity\Question::class => ModelFactory\QuestionServiceModelEntity::class,
            Entity\QuestionChoice::class => ModelFactory\QuestionChoiceServiceModelEntity::class,

            // TODO: replace to autowiring.
            Service\AnrInterviewService::class => Service\AnrInterviewServiceFactory::class,
            Service\AnrRecordActorService::class => Service\AnrRecordActorServiceFactory::class,
            Service\AnrRecordDataCategoryService::class => Service\AnrRecordDataCategoryServiceFactory::class,
            Service\AnrRecordInternationalTransferService::class
                => Service\AnrRecordInternationalTransferServiceFactory::class,
            Service\AnrRecordPersonalDataService::class => Service\AnrRecordPersonalDataServiceFactory::class,
            Service\AnrRecordProcessorService::class => Service\AnrRecordProcessorServiceFactory::class,
            Service\AnrRecordRecipientService::class => Service\AnrRecordRecipientServiceFactory::class,
            Service\AnrRecordService::class => Service\AnrRecordServiceFactory::class,
            Service\AnrQuestionService::class => Service\AnrQuestionServiceFactory::class,
            Service\AnrQuestionChoiceService::class => Service\AnrQuestionChoiceServiceFactory::class,
            Service\AnrMeasureService::class => AutowireFactory::class,
            Service\AnrMeasureLinkService::class => AutowireFactory::class,
            Service\AnrReferentialService::class => AutowireFactory::class,
            Service\SoaCategoryService::class => AutowireFactory::class,
            Service\AnrRolfTagService::class => AutowireFactory::class,
            Service\AnrRolfRiskService::class => AutowireFactory::class,
            Service\AnrRecommendationService::class => AutowireFactory::class,
            Service\AnrRecommendationHistoryService::class => AutowireFactory::class,
            Service\AnrRecommendationRiskService::class => AutowireFactory::class,
            Service\AnrRecommendationSetService::class => AutowireFactory::class,
            Service\AnrCartoRiskService::class => AutowireFactory::class,
            Service\DeliverableGenerationService::class => AutowireFactory::class,
            Service\SnapshotService::class => AutowireFactory::class,
            Service\AnrService::class => AutowireFactory::class,
            Service\UserService::class => ReflectionBasedAbstractFactory::class,
            Service\UserRoleService::class => AutowireFactory::class,
            Service\AnrAssetService::class => AutowireFactory::class,
            Service\AnrAmvService::class => AutowireFactory::class,
            Service\AnrThreatService::class => AutowireFactory::class,
            Service\AnrThemeService::class => AutowireFactory::class,
            Service\AnrVulnerabilityService::class => AutowireFactory::class,
            Service\ClientService::class => AutowireFactory::class,
            Service\AnrScaleService::class => AutowireFactory::class,
            Service\AnrScaleImpactTypeService::class => AutowireFactory::class,
            Service\AnrScaleCommentService::class => AutowireFactory::class,
            Service\AnrObjectService::class => AutowireFactory::class,
            Service\AnrObjectObjectService::class => AutowireFactory::class,
            Service\AnrInstanceConsequenceService::class => AutowireFactory::class,
            Service\AnrInstanceRiskOpService::class => AutowireFactory::class,
            Service\AnrInstanceRiskService::class => AutowireFactory::class,
            Service\AnrInstanceService::class => AutowireFactory::class,
            Service\OperationalRiskScaleService::class => AutowireFactory::class,
            Service\InstanceRiskOwnerService::class => AutowireFactory::class,
            Service\OperationalRiskScaleCommentService::class => AutowireFactory::class,
            Service\AnrInstanceMetadataFieldService::class => AutowireFactory::class,
            Service\InstanceMetadataService::class => AutowireFactory::class,
            Service\SoaService::class => AutowireFactory::class,
            Service\SoaScaleCommentService::class => AutowireFactory::class,
            Stats\Service\StatsAnrService::class => ReflectionBasedAbstractFactory::class,
            Stats\Service\StatsSettingsService::class => AutowireFactory::class,
            CronTask\Service\CronTaskService::class => AutowireFactory::class,
            /* Export services. */
            Export\Service\AnrExportService::class => AutowireFactory::class,
            Export\Service\ObjectExportService::class => AutowireFactory::class,
            Export\Service\InstanceExportService::class => AutowireFactory::class,
            /* Import services. */
            Import\Service\ObjectImportService::class => AutowireFactory::class,
            Import\Service\InstanceImportService::class => AutowireFactory::class,

            // Helpers
            Import\Helper\ImportCacheHelper::class => AutowireFactory::class,
            ScalesCacheHelper::class => static function (ContainerInterface $container) {
                return new ScalesCacheHelper(
                    $container->get(Table\ScaleTable::class),
                    $container->get(Table\ScaleImpactTypeTable::class),
                    $container->get(Table\OperationalRiskScaleTable::class),
                );
            },

            /* Authentication */
            StorageAuthentication::class => static function (ContainerInterface $container) {
                return new StorageAuthentication(
                    $container->get(Table\UserTokenTable::class),
                    $container->get('config')
                );
            },
            AdapterAuthentication::class => static function (ContainerInterface $container) {
                return new AdapterAuthentication(
                    $container->get(Table\UserTable::class),
                    $container->get(ConfigService::class)
                );
            },
            ConnectedUserService::class => static function (ContainerInterface $container) {
                return new ConnectedUserService(
                    $container->get(Request::class),
                    $container->get(Table\UserTokenTable::class)
                );
            },

            // Providers
            Stats\Provider\StatsApiProvider::class => ReflectionBasedAbstractFactory::class,

            // Validators
            InputValidator\User\PostUserDataInputValidator::class => ReflectionBasedAbstractFactory::class,
            Stats\Validator\GetStatsQueryParamsValidator::class => ReflectionBasedAbstractFactory::class,
            Stats\Validator\GetProcessedStatsQueryParamsValidator::class => ReflectionBasedAbstractFactory::class,
            InputValidator\Anr\CreateAnrDataInputValidator::class => ReflectionBasedAbstractFactory::class,
            InputValidator\Object\PostObjectDataInputValidator::class => ReflectionBasedAbstractFactory::class,
            InputValidator\Object\DuplicateObjectDataInputValidator::class => ReflectionBasedAbstractFactory::class,
            InputValidator\Recommendation\PostRecommendationDataInputValidator::class => static function (
                Containerinterface $container
            ) {
                return new InputValidator\Recommendation\PostRecommendationDataInputValidator(
                    $container->get('config'),
                    $container->get(CoreInputValidator\InputValidationTranslator::class),
                    $container->get(Table\RecommendationTable::class)
                );
            },
            InputValidator\Recommendation\PatchRecommendationDataInputValidator::class => static function (
                Containerinterface $container
            ) {
                return new InputValidator\Recommendation\PatchRecommendationDataInputValidator(
                    $container->get('config'),
                    $container->get(CoreInputValidator\InputValidationTranslator::class),
                    $container->get(Table\RecommendationTable::class)
                );
            },
            InputValidator\RecommendationSet\PostRecommendationSetDataInputValidator::class =>
                ReflectionBasedAbstractFactory::class,
            InputValidator\RecommendationRisk\ValidateRecommendationRiskDataInputValidator::class =>
                ReflectionBasedAbstractFactory::class,
            InputValidator\RecommendationRisk\PostRecommendationRiskDataInputValidator::class =>
                ReflectionBasedAbstractFactory::class,
            InputValidator\RecommendationRisk\PatchRecommendationRiskDataInputValidator::class =>
                ReflectionBasedAbstractFactory::class,
            InputValidator\InstanceRisk\PostSpecificInstanceRiskDataInputValidator::class =>
                ReflectionBasedAbstractFactory::class,
            InputValidator\InstanceRisk\UpdateInstanceRiskDataInputValidator::class =>
                ReflectionBasedAbstractFactory::class,
            InputValidator\InstanceRiskOp\PostSpecificInstanceRiskOpDataInputValidator::class =>
                ReflectionBasedAbstractFactory::class,
            InputValidator\InstanceRiskOp\UpdateInstanceRiskOpDataInputValidator::class =>
                ReflectionBasedAbstractFactory::class,
            InputValidator\Threat\PostThreatDataInputValidator::class => static function (Containerinterface $container)
            {
                return new InputValidator\Threat\PostThreatDataInputValidator(
                    $container->get('config'),
                    $container->get(CoreInputValidator\InputValidationTranslator::class),
                    $container->get(Table\ThreatTable::class)
                );
            },
            CoreInputValidator\Asset\PostAssetDataInputValidator::class => static function (
                Containerinterface $container
            ) {
                return new CoreInputValidator\Asset\PostAssetDataInputValidator(
                    $container->get('config'),
                    $container->get(CoreInputValidator\InputValidationTranslator::class),
                    $container->get(Table\AssetTable::class)
                );
            },
            CoreInputValidator\Vulnerability\PostVulnerabilityDataInputValidator::class => static function (
                Containerinterface $container
            ) {
                return new CoreInputValidator\Vulnerability\PostVulnerabilityDataInputValidator(
                    $container->get('config'),
                    $container->get(CoreInputValidator\InputValidationTranslator::class),
                    $container->get(Table\VulnerabilityTable::class)
                );
            },

            // Commands
            Import\Command\ImportAnalysesCommand::class => static function (ContainerInterface $container) {
                /** @var ConnectedUserService $connectedUserService */
                $connectedUserService = $container->get(ConnectedUserService::class);
                $connectedUserService->setConnectedUser(new Entity\User([
                    'firstname' => 'System',
                    'lastname' => 'System',
                    'email' => 'System',
                    'language' => 1,
                    'mospApiKey' => '',
                    'creator' => 'System',
                    'role' => [Entity\UserRole::USER_ROLE_SYSTEM],
                ]));

                return new Import\Command\ImportAnalysesCommand(
                    $container->get(CronTask\Service\CronTaskService::class),
                    $container->get(Import\Service\InstanceImportService::class),
                    $container->get(Table\AnrTable::class),
                    $container->get(Service\SnapshotService::class)
                );
            },
        ],
        'lazy_services' => [
            'class_map' => [
                Table\UserTokenTable::class => Table\UserTokenTable::class,
                Service\AnrInstanceService::class => Service\AnrInstanceService::class,
                Import\Processor\ObjectCategoryImportProcessor::class =>
                    Import\Processor\ObjectCategoryImportProcessor::class,
                Import\Processor\InformationRiskImportProcessor::class =>
                    Import\Processor\InformationRiskImportProcessor::class,
                Import\Processor\OperationalRiskImportProcessor::class =>
                    Import\Processor\OperationalRiskImportProcessor::class,
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
            Import\Processor\ObjectCategoryImportProcessor::class => [
                LazyServiceFactory::class,
            ],
            Import\Processor\InformationRiskImportProcessor::class => [
                LazyServiceFactory::class,
            ],
            Import\Processor\OperationalRiskImportProcessor::class => [
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
                    'Monarc\FrontOffice\Entity' => 'Monarc_cli_driver',
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
            'monarc_api_global_client_anr/anr_instance_metadata_field',
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
            'monarc_api_anr_recommendations',
            'monarc_api_anr_recommendations_history',
            'monarc_api_anr_recommendations_risks',
            'monarc_api_anr_recommendations_risks_validate',
            'monarc_api_anr_recommendations_measures',
            'monarc_api_anr_recommendations_sets',
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
            'monarc_api_global_client_anr/recommendations',
            'monarc_api_global_client_anr/recommendations_history',
            'monarc_api_global_client_anr/recommendations_risks',
            'monarc_api_global_client_anr/recommendations_risks_validate',
            'monarc_api_global_client_anr/recommendations_measures',
            'monarc_api_global_client_anr/recommendations_sets',
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
