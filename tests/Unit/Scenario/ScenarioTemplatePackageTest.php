<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Tests\Unit\Scenario;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Router\Http\TreeRouteStack;
use Monarc\Core\Exception\ActionForbiddenException;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Entity\UserAnr;
use Monarc\FrontOffice\Scenario\Service\ScenarioTemplatePackageService;
use Monarc\FrontOffice\Scenario\Table\ScenarioTemplateSnapshotTable;
use Monarc\FrontOffice\Service\AnrSupervisorService;
use Monarc\FrontOffice\Table\UserAnrTable;
use PHPUnit\Framework\TestCase;

/** @covers \Monarc\FrontOffice\Scenario\Service\ScenarioTemplatePackageService */
final class ScenarioTemplatePackageTest extends TestCase
{
    public function testOnlyTheAuthorisedAnrSnapshotIsReadAndLegacyEvidenceIsNotRefreshed(): void
    {
        $db = $this->createMock(Connection::class);
        $db->expects(self::once())->method('fetchAssociative')
            ->with(self::stringContains('WHERE anr_id = ?'), [7])->willReturn([
                'id' => 3, 'anr_id' => 7, 'uuid' => 'snapshot',
                'catalog_template_uuid' => 'template', 'catalog_template_version' => 1,
                'catalog_release_checksum' => 'checksum', 'catalog_provenance' => 'original',
                'payload_json' => json_encode(['knowledgeBasePackage' => [[
                    'intendedUse' => 'suggested', 'source' => ['label' => 'Published label'],
                ]]]),
            ]);
        $db->expects(self::exactly(2))->method('fetchAllAssociative')->willReturn([]);
        $db->expects(self::never())->method('insert');
        $db->expects(self::never())->method('update');
        $result = $this->service($db, true)->read($this->anr());
        self::assertSame('snapshot', $result['compatibility']);
        self::assertSame('Published label', $result['links'][0]['source']['label']);
        self::assertArrayNotHasKey('intendedUse', $result['links'][0]);
        self::assertSame('original', $result['template']['provenance']);
    }

    public function testCrossAnrUserIsDeniedBeforeAnySnapshotIsRead(): void
    {
        $db = $this->createMock(Connection::class);
        $db->expects(self::never())->method('fetchAssociative');
        $this->expectException(ActionForbiddenException::class);
        $this->service($db, false)->read($this->anr());
    }

    public function testNoTemplateHasAnHonestEmptyState(): void
    {
        $db = $this->createMock(Connection::class);
        $db->method('fetchAssociative')->willReturn(false);
        self::assertSame(
            ['compatibility' => 'no_template', 'template' => null, 'links' => []],
            $this->service($db, true)->read($this->anr())
        );
    }

    /** @coversNothing */
    public function testPackageIsAFixedAuthorisedRouteNotAGenericTemplateRecord(): void
    {
        $config = require dirname(__DIR__, 3) . '/config/module.config.php';
        $router = new TreeRouteStack();
        $router->addRoutes($config['router']['routes']);
        $request = new Request();
        $request->setUri('http://localhost/api/scenario/v1/anrs/7/template/package');
        $match = $router->match($request);
        self::assertNotNull($match);
        self::assertSame('scenario_v1_anr_template_package', $match->getMatchedRouteName());
        self::assertSame('7', $match->getParam('anrid'));
        self::assertContains('scenario_v1_anr_template_package', $config['permissions']);
        $module = file_get_contents(dirname(__DIR__, 3) . '/Module.php');
        self::assertStringContainsString("'scenario_v1_anr_template_package'", $module);
        $middleware = $config['router']['routes']['scenario_v1_anr_template_package']['options']['defaults']['middleware'];
        self::assertStringContainsString('AnrValidationMiddleware', serialize($middleware));
    }

    private function service(Connection $db, bool $authorised): ScenarioTemplatePackageService
    {
        $em = $this->createMock(EntityManager::class);
        $em->method('getConnection')->willReturn($db);
        $user = $this->createMock(User::class);
        $users = $this->createMock(ConnectedUserService::class);
        $users->method('getConnectedUser')->willReturn($user);
        $links = $this->createMock(UserAnrTable::class);
        $links->method('findByAnrAndUser')->willReturn($authorised ? $this->createMock(UserAnr::class) : null);
        $supervisors = $this->createMock(AnrSupervisorService::class);
        $supervisors->method('findLinkedSupervisor')->willReturn(null);

        return new ScenarioTemplatePackageService(new ScenarioTemplateSnapshotTable($em), $users, $links, $supervisors);
    }

    private function anr(): Anr
    {
        $anr = $this->createMock(Anr::class);
        $anr->method('getId')->willReturn(7);

        return $anr;
    }
}
