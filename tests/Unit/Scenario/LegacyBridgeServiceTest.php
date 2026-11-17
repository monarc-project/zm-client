<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Tests\Unit\Scenario;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Laminas\Di\Injector;
use Laminas\Di\InjectorInterface;
use Laminas\ServiceManager\ServiceManager;
use Monarc\Core\Scenario\Feature\ScenarioCapability;
use Monarc\Core\Scenario\Service\ScenarioAuthenticationService as AuthenticationContract;
use Monarc\FrontOffice\Scenario\Service\LegacyBridgeService;
use Monarc\FrontOffice\Scenario\Service\ScenarioAuthenticationService;
use Monarc\FrontOffice\Scenario\Table\LegacyBridgeTable;
use Monarc\FrontOffice\Table\UserTokenTable;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Monarc\FrontOffice\Scenario\Service\LegacyBridgeService
 * @covers \Monarc\FrontOffice\Scenario\Service\ScenarioAuthenticationService
 */
final class LegacyBridgeServiceTest extends TestCase
{
    /** @dataProvider ttlCases */
    public function testReflectionFactoryPreservesConfiguredSessionAndLegacyTokenLifetimes(
        array $config,
        int $sessionMinutes,
        int $authMinutes
    ): void {
        $db = $this->createMock(Connection::class);
        $db->method('fetchAssociative')->willReturnCallback(static function (string $sql): array {
            if (str_contains($sql, 'scenario_legacy_handoffs')) {
                return [
                    'id' => 1, 'consumed_at' => null, 'expires_at' => '2099-01-01 00:00:00',
                    'audience' => 'scenario-ui', 'legacy_token_digest' => str_repeat('b', 64),
                    'nonce_digest' => hash('sha256', str_repeat('c', 16)), 'user_id' => 9,
                    'anr_id' => 7, 'office' => 'frontoffice',
                ];
            }

            return [
                'user_id' => 9, 'anr_id' => 7, 'office' => 'frontoffice',
                'expires_at' => '2099-01-01 00:00:00', 'legacy_token_id' => 4,
            ];
        });
        $db->method('fetchOne')->willReturn(4);
        $db->method('update')->willReturnCallback(
            static function (string $table, array $values) use ($authMinutes): int {
                if ($table === 'user_tokens') {
                    self::assertEqualsWithDelta(time() + $authMinutes * 60, strtotime($values['date_end'] . ' UTC'), 2);
                }

                return 1;
            }
        );
        $db->expects(self::once())->method('insert')->with(
            'scenario_sessions',
            self::callback(static function (array $row) use ($sessionMinutes): bool {
                self::assertEqualsWithDelta(time() + $sessionMinutes * 60, strtotime($row['expires_at'] . ' UTC'), 2);

                return true;
            })
        );
        $config['scenario']['legacyBridgeServiceToken'] = str_repeat('a', 32);
        $services = $this->services($db, $config);
        $bridge = $services->get(LegacyBridgeService::class);
        $bridge->consume(['code' => str_repeat('a', 64), 'nonce' => str_repeat('c', 16), 'audience' => 'scenario-ui']);
        self::assertSame('9', $bridge->resolveInternalSession(str_repeat('d', 64), str_repeat('a', 32))['subject']);
    }

    public function testInternalCallerCredentialComesFromMergedConfiguration(): void
    {
        $db = $this->createMock(Connection::class);
        $db->expects(self::never())->method('fetchAssociative');
        $db->expects(self::never())->method('update');
        $bridge = $this->services($db, [
            'scenario' => ['legacyBridgeServiceToken' => str_repeat('a', 32)],
        ])->get(LegacyBridgeService::class);
        $this->expectException(\RuntimeException::class);
        $bridge->resolveInternalSession(str_repeat('d', 64), str_repeat('b', 32));
    }

    /** @dataProvider enabledCases */
    public function testAutowiringResolvesTheOfficeAuthenticationContractAndPreservesLogout(bool $enabled): void
    {
        $db = $this->createMock(Connection::class);
        $db->expects($enabled ? self::once() : self::never())->method('executeStatement')
            ->with(self::stringContains('UPDATE scenario_sessions'), [9])->willReturn(1);
        $services = $this->services($db, [], $enabled);
        $auth = $services->get(AuthenticationContract::class);
        self::assertInstanceOf(ScenarioAuthenticationService::class, $auth);
        self::assertSame($auth, $services->get(ScenarioAuthenticationService::class));
        $auth->revokeUserSessions(9);
    }

    public static function ttlCases(): array
    {
        return [
            'defaults' => [[], 480, 20],
            'custom values' => [['scenario' => ['sessionTtl' => '90'], 'monarc' => ['ttl' => '45']], 90, 45],
            'minimum values' => [['scenario' => ['sessionTtl' => 0], 'monarc' => ['ttl' => -1]], 1, 1],
            'invalid session lifetime' => [['scenario' => ['sessionTtl' => 'invalid']], 480, 20],
        ];
    }

    public static function enabledCases(): array
    {
        return [[true], [false]];
    }

    private function services(Connection $db, array $config, bool $enabled = true): ServiceManager
    {
        $module = require dirname(__DIR__, 3) . '/config/module.config.php';
        $em = $this->createMock(EntityManager::class);
        $em->method('getConnection')->willReturn($db);
        $services = [
            'config' => $config,
            LegacyBridgeTable::class => new LegacyBridgeTable($em),
            UserTokenTable::class => $this->createMock(UserTokenTable::class),
            ScenarioCapability::class => new ScenarioCapability(['enabled' => $enabled]),
        ];
        $services[\Monarc\FrontOffice\Scenario\Service\ScenarioLaunchAccessService::class] =
            new \Monarc\FrontOffice\Scenario\Service\ScenarioLaunchAccessService(
                $this->createMock(\Monarc\FrontOffice\Table\AnrTable::class),
                $this->createMock(\Monarc\FrontOffice\Table\UserAnrTable::class),
                $this->createMock(\Monarc\FrontOffice\Service\AnrSupervisorService::class)
            );
        $manager = new ServiceManager([
            'services' => $services,
            'aliases' => $module['service_manager']['aliases'],
            'factories' => array_intersect_key($module['service_manager']['factories'], array_flip([
                LegacyBridgeService::class, ScenarioAuthenticationService::class,
            ])),
        ]);
        $manager->setService(InjectorInterface::class, new Injector(null, $manager));

        return $manager;
    }
}
