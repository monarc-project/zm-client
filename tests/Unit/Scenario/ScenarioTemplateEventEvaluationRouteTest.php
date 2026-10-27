<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Tests\Unit\Scenario;

use Laminas\Http\PhpEnvironment\Request;
use Laminas\Router\Http\Segment;
use PHPUnit\Framework\TestCase;

/** @coversNothing */
final class ScenarioTemplateEventEvaluationRouteTest extends TestCase
{
    public function testEvaluationUsesAFixedAuthorisedAnrRoute(): void
    {
        $config = require dirname(__DIR__, 3) . '/config/module.config.php';
        $route = $config['router']['routes']['scenario_v1_anr_template_event_evaluations']['options'];
        $router = Segment::factory($route);
        $request = new Request();
        $request->setMethod('GET');
        $request->setUri('http://localhost/api/scenario/v1/anrs/78/template/event-evaluations');

        $match = $router->match($request);

        self::assertNotNull($match);
        self::assertSame('78', $match->getParam('anrid'));
        self::assertContains('scenario_v1_anr_template_event_evaluations', $config['permissions']);

        $module = file_get_contents(dirname(__DIR__, 3) . '/Module.php');
        self::assertIsString($module);
        self::assertStringContainsString("'scenario_v1_anr_template_event_evaluations'", $module);
    }
}
