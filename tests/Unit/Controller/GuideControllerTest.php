<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFrontOfficeTests\Unit\Controller;

use Laminas\Diactoros\ServerRequest;
use Laminas\Router\RouteMatch;
use Monarc\Core\InputFormatter\Guide\GetGuidesInputFormatter;
use Monarc\Core\InputFormatter\Guide\GetGuideItemsInputFormatter;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Tests\Fixture\GuideTestEnvironment;
use Monarc\FrontOffice\Controller\ApiGuidesController;
use Monarc\FrontOffice\Controller\ApiGuidesItemsController;
use PHPUnit\Framework\TestCase;

/** @coversNothing */
class GuideControllerTest extends TestCase
{
    private GuideTestEnvironment $env;
    private int $guide;
    private int $item;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../../zm-core/tests/Fixture/GuideTestEnvironment.php';
        $connectedUserService = $this->createMock(ConnectedUserService::class);
        $connectedUserService->method('getConnectedUser')->willReturn(GuideTestEnvironment::user());
        $this->env = new GuideTestEnvironment($connectedUserService);
        $this->guide = $this->env->guideService->create([
            'type' => 1,
            'isWithItems' => 1,
            'description3' => 'Context guide',
        ])->getId();
        $this->item = $this->env->itemService->create([
            'guide' => $this->guide,
            'description3' => 'Guide item',
        ])->getId();
    }

    protected function tearDown(): void
    {
        $this->env->manager->close();
    }

    public function testReadEndpointsKeepTheAngularGuidePayloads(): void
    {
        $response = $this->request(false, 'GET');
        self::assertSame(1, $response['count']);
        self::assertSame(1, $response['guides'][0]['type_id']);
        self::assertSame('Risk analysis context', $response['guides'][0]['type']);
        self::assertSame('Context guide', $this->request(false, 'GET', $this->guide)['description3']);
        $response = $this->request(true, 'GET', null, ['guide' => $this->guide, 'order' => 'position']);
        self::assertSame(1, $response['count']);
        self::assertSame('Guide item', $response['guides-items'][0]['description3']);
        self::assertSame($this->guide, $response['guides-items'][0]['guide']['id']);
        self::assertSame($this->item, $this->request(true, 'GET', $this->item)['id']);
    }

    /** @dataProvider writeCases */
    public function testWriteRequestsReturn405WithoutChangingGuides(bool $items, string $method, bool $single): void
    {
        $id = $single ? ($items ? $this->item : $this->guide) : null;
        $response = $this->controller($items)->handle($this->serverRequest($items, $method, $id)->withParsedBody([
            'type' => 2,
            'isWithItems' => 0,
            'guide' => $this->guide,
            'description3' => 'Changed',
        ]));
        self::assertSame(405, $response->getStatusCode());
        self::assertSame(1, $this->env->guides->countAll());
        self::assertSame(1, $this->env->items->countAll());
        self::assertSame('Context guide', $this->env->guideService->getGuideData($this->guide)['description3']);
        self::assertSame('Guide item', $this->env->itemService->getGuideItemData($this->item)['description3']);
    }

    public static function writeCases(): array
    {
        $cases = [];
        foreach ([false, true] as $items) {
            foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
                foreach ([false, true] as $single) {
                    $cases[] = [$items, $method, $single];
                }
            }
        }
        return $cases;
    }

    public function testReadRoutesRemainAuthorizedForFrontOfficeRoles(): void
    {
        $config = require __DIR__ . '/../../../config/module.config.php';
        $router = \Laminas\Router\Http\TreeRouteStack::factory($config['router']);
        $routes = ['/api/guides' => 'monarc_api_guides', '/api/guides-items' => 'monarc_api_guides_items'];
        foreach ($routes as $uri => $name) {
            $request = (new \Laminas\Http\Request())->setUri($uri);
            self::assertSame($name, $router->match($request)->getMatchedRouteName());
            foreach (['superadminfo', 'userfo'] as $role) {
                self::assertContains($name, $config['roles'][$role]);
            }
        }
    }

    private function controller(bool $items): object
    {
        return $items
            ? new ApiGuidesItemsController(new GetGuideItemsInputFormatter(), $this->env->itemService)
            : new ApiGuidesController(new GetGuidesInputFormatter(), $this->env->guideService);
    }

    private function serverRequest(bool $items, string $method, ?int $id): ServerRequest
    {
        return (new ServerRequest([], [], $items ? '/api/guides-items' : '/api/guides', $method))
            ->withAttribute(RouteMatch::class, new RouteMatch($id === null ? [] : ['id' => $id]));
    }

    private function request(bool $items, string $method, ?int $id = null, array $query = []): array
    {
        $response = $this->controller($items)->handle(
            $this->serverRequest($items, $method, $id)->withQueryParams($query)
        );
        return json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
