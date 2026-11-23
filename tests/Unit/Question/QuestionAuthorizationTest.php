<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFrontOfficeTests\Unit\Question;

use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Laminas\Router\RouteMatch;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\CronTask\Service\CronTaskService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Entity\UserAnr;
use Monarc\FrontOffice\Middleware\AnrValidationMiddleware;
use Monarc\FrontOffice\Service\AnrSupervisorService;
use Monarc\FrontOffice\Table;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;

/** @coversNothing */
class QuestionAuthorizationTest extends TestCase
{
    /** @dataProvider accessCases */
    public function testQuestionRouteRetainsAnrReadAndWriteAuthorization(
        string $route,
        string $method,
        ?int $permission,
        int $status
    ): void {
        $anr = new Anr();
        (new \ReflectionProperty($anr, 'id'))->setValue($anr, 1);
        $user = new User([
            'firstname' => 'Question', 'lastname' => 'Editor', 'email' => 'question@example.invalid',
            'creator' => 'PHP84-03', 'role' => [],
        ]);
        $identity = $this->createMock(ConnectedUserService::class);
        $identity->method('getConnectedUser')->willReturn($user);
        $anrs = $this->createMock(Table\AnrTable::class);
        $anrs->method('findById')->willReturn($anr);
        $permissions = $this->createMock(Table\UserAnrTable::class);
        $permissions->method('findByAnrAndUser')->willReturn(
            $permission === null ? null : (new UserAnr())->setRwd($permission)
        );
        $middleware = new AnrValidationMiddleware(
            $anrs,
            $permissions,
            $this->createMock(Table\InstanceTable::class),
            $this->createMock(Table\InstanceRiskTable::class),
            $this->createMock(Table\InstanceRiskOpTable::class),
            $this->createMock(AnrSupervisorService::class),
            $this->createMock(CronTaskService::class),
            new ResponseFactory(),
            $identity
        );
        $match = new RouteMatch(['anrid' => 1]);
        $match->setMatchedRouteName('monarc_api_global_client_anr/' . $route);
        $request = (new ServerRequest([], [], '/api/client-anr/1/questions', $method))
            ->withAttribute(RouteMatch::class, $match);
        $handler = $this->createMock(RequestHandlerInterface::class);
        if ($status === 200) {
            $handler->expects(self::once())->method('handle')->with(self::callback(
                fn ($request): bool => $request->getAttribute('anr') === $anr
            ))->willReturn(new JsonResponse(['status' => 'ok']));
        } else {
            $handler->expects(self::never())->method('handle');
        }
        self::assertSame($status, $middleware->process($request, $handler)->getStatusCode());
    }

    public static function accessCases(): array
    {
        $rows = [];
        foreach (['questions'] as $route) {
            $rows[] = [$route, 'GET', 0, 200];
            $rows[] = [$route, 'PUT', 1, 200];
            $rows[] = [$route, 'PUT', 0, 403];
            $rows[] = [$route, 'GET', null, 403];
        }
        return $rows;
    }
}
