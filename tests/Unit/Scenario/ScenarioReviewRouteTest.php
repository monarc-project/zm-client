<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Tests\Unit\Scenario;

use Laminas\Http\PhpEnvironment\Request;
use Laminas\Router\Http\Segment;
use PHPUnit\Framework\TestCase;

final class ScenarioReviewRouteTest extends TestCase
{
    public function testReviewTransitionUsesOperationInsteadOfTheReservedActionParameter(): void
    {
        $config = require dirname(__DIR__, 3) . '/config/module.config.php';
        $options = $config['router']['routes']['scenario_v1_anr_reviews']['options'];
        $router = Segment::factory($options);
        $request = new Request();
        $request->setMethod('PATCH');
        $request->setUri(
            'http://localhost/api/scenario/v1/anrs/78/reviews/cases/4f686db0-6904-4cb2-a442-00e79b7ffe13/assign'
        );

        $match = $router->match($request);

        self::assertNotNull($match);
        self::assertSame('assign', $match->getParam('operation'));
        self::assertFalse($match->getParam('action', false));
    }

    public function testTheRemovedExplicitStartTransitionDoesNotMatchTheRoute(): void
    {
        $config = require dirname(__DIR__, 3) . '/config/module.config.php';
        $router = Segment::factory($config['router']['routes']['scenario_v1_anr_reviews']['options']);
        $request = new Request();
        $request->setMethod('PATCH');
        $request->setUri(
            'http://localhost/api/scenario/v1/anrs/78/reviews/cases/4f686db0-6904-4cb2-a442-00e79b7ffe13/start'
        );

        self::assertNull($router->match($request));
    }
}
