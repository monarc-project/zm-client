<?php declare(strict_types=1);

namespace Monarc\FrontOfficeTest\Unit\Service;

use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Entity\UserAnr;
use Monarc\FrontOffice\Entity\UserRole;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Service\AnrSupervisorService;
use Monarc\FrontOffice\Table\AnrSupervisorTable;
use Monarc\FrontOffice\Table\InstanceRiskOpTable;
use Monarc\FrontOffice\Table\InstanceRiskTable;
use Monarc\FrontOffice\Table\UserAnrTable;
use Monarc\FrontOffice\Table\UserTable;
use PHPUnit\Framework\TestCase;

final class AnrSupervisorServiceTest extends TestCase
{
    public function testUserWithWriteAccessCanManageLinkedSupervisorsForThatAnalysis(): void
    {
        $user = $this->user(UserRole::USER_FO);
        $anr = $this->createMock(Anr::class);
        $permission = $this->createMock(UserAnr::class);
        $permission->method('getRwd')->willReturn(UserAnr::FULL_PERMISSIONS_RWD);
        $permissions = $this->createMock(UserAnrTable::class);
        $permissions->expects(self::once())
            ->method('findByAnrAndUser')
            ->with($anr, $user)
            ->willReturn($permission);

        self::assertTrue($this->service($user, $permissions)->canManageLinkedUsers($anr));
    }

    public function testNonUserFoRoleWithRwdAccessCanManageSupervisors(): void
    {
        $user = $this->user(UserRole::USER_ROLE_CEO);
        $anr = $this->createMock(Anr::class);
        $permission = $this->createMock(UserAnr::class);
        $permission->method('getRwd')->willReturn(UserAnr::FULL_PERMISSIONS_RWD);
        $permissions = $this->createMock(UserAnrTable::class);
        $permissions->expects(self::once())
            ->method('findByAnrAndUser')
            ->with($anr, $user)
            ->willReturn($permission);

        self::assertTrue($this->service($user, $permissions)->canManageSupervisors($anr));
    }

    public function testSupervisorPermissionUsesDelegatedUserResolvedAfterServiceConstruction(): void
    {
        $user = $this->user(UserRole::USER_ROLE_CEO);
        $anr = $this->createMock(Anr::class);
        $permission = $this->createMock(UserAnr::class);
        $permission->method('getRwd')->willReturn(UserAnr::FULL_PERMISSIONS_RWD);
        $permissions = $this->createMock(UserAnrTable::class);
        $permissions->expects(self::once())
            ->method('findByAnrAndUser')
            ->with($anr, $user)
            ->willReturn($permission);
        $connectedUser = $this->createMock(ConnectedUserService::class);
        $connectedUser->expects(self::exactly(2))
            ->method('getConnectedUser')
            ->willReturnOnConsecutiveCalls(null, $user);

        $service = new AnrSupervisorService(
            $this->createMock(AnrSupervisorTable::class),
            $this->createMock(InstanceRiskTable::class),
            $this->createMock(InstanceRiskOpTable::class),
            $this->createMock(UserTable::class),
            $permissions,
            new ImportCacheHelper(),
            $connectedUser
        );

        self::assertTrue($service->canManageSupervisors($anr));
    }

    public function testReadOnlyUserCannotManageLinkedSupervisors(): void
    {
        $user = $this->user(UserRole::USER_FO);
        $anr = $this->createMock(Anr::class);
        $permission = $this->createMock(UserAnr::class);
        $permission->method('getRwd')->willReturn(0);
        $permissions = $this->createMock(UserAnrTable::class);
        $permissions->expects(self::once())
            ->method('findByAnrAndUser')
            ->with($anr, $user)
            ->willReturn($permission);

        self::assertFalse($this->service($user, $permissions)->canManageLinkedUsers($anr));
    }

    public function testFrontOfficeSuperAdminCanManageLinkedSupervisorsWithoutAnrAssignment(): void
    {
        $user = $this->user(UserRole::SUPER_ADMIN_FO);
        $permissions = $this->createMock(UserAnrTable::class);
        $permissions->expects(self::never())->method('findByAnrAndUser');

        self::assertTrue($this->service($user, $permissions)->canManageLinkedUsers($this->createMock(Anr::class)));
    }

    public function testLinkableUsersOnlyIncludesFrontOfficeUsers(): void
    {
        $connectedUser = $this->user(UserRole::USER_FO);
        $anr = $this->createMock(Anr::class);
        $permissions = $this->createMock(UserAnrTable::class);
        $permissions->method('findByAnrAndUser')->willReturn(
            $this->createConfiguredMock(UserAnr::class, ['getRwd' => UserAnr::FULL_PERMISSIONS_RWD])
        );
        $supervisorTable = $this->createMock(AnrSupervisorTable::class);
        $supervisorTable->expects(self::once())->method('getLinkedUserIdsByAnr')->with($anr)->willReturn([]);
        $frontOfficeUser = $this->createConfiguredMock(User::class, [
            'getId' => 10,
            'getFirstname' => 'Front',
            'getLastname' => 'Office',
            'getEmail' => 'front@example.test',
        ]);
        $frontOfficeUser->method('hasRole')->willReturnCallback(
            static fn (string $role): bool => $role === UserRole::USER_FO
        );
        $ceo = $this->createConfiguredMock(User::class, [
            'getId' => 11,
            'getFirstname' => 'Chief',
            'getLastname' => 'Executive',
            'getEmail' => 'ceo@example.test',
        ]);
        $ceo->method('hasRole')->willReturn(false);
        $userTable = $this->createMock(UserTable::class);
        $userTable->expects(self::once())->method('findBySearchString')->with('')->willReturn([$frontOfficeUser, $ceo]);
        $connectedUserService = $this->createMock(ConnectedUserService::class);
        $connectedUserService->method('getConnectedUser')->willReturn($connectedUser);

        $service = new AnrSupervisorService(
            $supervisorTable,
            $this->createMock(InstanceRiskTable::class),
            $this->createMock(InstanceRiskOpTable::class),
            $userTable,
            $permissions,
            new ImportCacheHelper(),
            $connectedUserService
        );

        self::assertSame([[
            'id' => 10,
            'firstname' => 'Front',
            'lastname' => 'Office',
            'email' => 'front@example.test',
        ]], $service->getLinkableUsers($anr));
    }

    private function service(User $user, UserAnrTable $permissions): AnrSupervisorService
    {
        $connectedUser = $this->createMock(ConnectedUserService::class);
        $connectedUser->method('getConnectedUser')->willReturn($user);

        return new AnrSupervisorService(
            $this->createMock(AnrSupervisorTable::class),
            $this->createMock(InstanceRiskTable::class),
            $this->createMock(InstanceRiskOpTable::class),
            $this->createMock(UserTable::class),
            $permissions,
            new ImportCacheHelper(),
            $connectedUser
        );
    }

    private function user(string $role): User
    {
        return new User([
            'firstname' => 'Test',
            'lastname' => 'User',
            'email' => 'test.user@example.test',
            'creator' => 'test',
            'role' => [$role],
        ]);
    }
}
