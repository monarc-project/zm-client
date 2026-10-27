<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Tests\Unit\Scenario;

use Laminas\Di\Container\AutowireFactory;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Router\Http\Segment;
use Monarc\FrontOffice\Scenario\Service\ScenarioTemplateModelRiskScenarioCreationService;
use Monarc\FrontOffice\Scenario\Table\ScenarioTemplateModelRiskScenarioDerivationTable;
use Monarc\FrontOffice\Scenario\Validator\ScenarioTemplateModelRiskScenarioCreationValidator;
use PHPUnit\Framework\TestCase;

/** @coversNothing */
final class ScenarioTemplateModelRiskScenarioCreationRouteTest extends TestCase
{
    public function testCreationUsesAFixedAuthorisedAnrRouteBeforeTheGenericTemplateRoute(): void
    {
        $config = require dirname(__DIR__, 3) . '/config/module.config.php';
        $route = $config['router']['routes']['scenario_v1_anr_template_risk_scenarios']['options'];
        $router = Segment::factory($route);
        $request = new Request();
        $request->setMethod('POST');
        $request->setUri('http://localhost/api/scenario/v1/anrs/78/template/risk-scenarios');

        $match = $router->match($request);

        self::assertNotNull($match);
        self::assertSame('78', $match->getParam('anrid'));
        self::assertContains('scenario_v1_anr_template_risk_scenarios', $config['permissions']);
        self::assertSame(
            AutowireFactory::class,
            $config['service_manager']['factories'][ScenarioTemplateModelRiskScenarioCreationService::class]
        );
        self::assertArrayHasKey(
            ScenarioTemplateModelRiskScenarioDerivationTable::class,
            $config['service_manager']['factories']
        );
        self::assertSame(
            AutowireFactory::class,
            $config['service_manager']['factories'][ScenarioTemplateModelRiskScenarioCreationValidator::class]
        );

        $module = file_get_contents(dirname(__DIR__, 3) . '/Module.php');
        self::assertIsString($module);
        self::assertStringContainsString("'scenario_v1_anr_template_risk_scenarios'", $module);
    }
}
