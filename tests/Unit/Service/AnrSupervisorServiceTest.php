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
        $permission->method('hasWriteAccess')->willReturn(true);
        $permissions = $this->createMock(UserAnrTable::class);
        $permissions->expects(self::once())
            ->method('findByAnrAndUser')
            ->with($anr, $user)
            ->willReturn($permission);

        self::assertTrue($this->service($user, $permissions)->canManageLinkedUsers($anr));
    }

    public function testReadOnlyUserCannotManageLinkedSupervisors(): void
    {
        $user = $this->user(UserRole::USER_FO);
        $anr = $this->createMock(Anr::class);
        $permission = $this->createMock(UserAnr::class);
        $permission->method('hasWriteAccess')->willReturn(false);
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
