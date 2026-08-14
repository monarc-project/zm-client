<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Doctrine\ORM\EntityNotFoundException;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\AnrSupervisor;
use Monarc\FrontOffice\Entity\AnrSupervisorRole;
use Monarc\FrontOffice\Entity\InstanceRisk;
use Monarc\FrontOffice\Entity\InstanceRiskOp;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Entity\UserRole;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Table\AnrSupervisorTable;
use Monarc\FrontOffice\Table\InstanceRiskOpTable;
use Monarc\FrontOffice\Table\InstanceRiskTable;
use Monarc\FrontOffice\Table\UserTable;

class AnrSupervisorService
{
    private User $connectedUser;

    public function __construct(
        private AnrSupervisorTable $anrSupervisorTable,
        private InstanceRiskTable $instanceRiskTable,
        private InstanceRiskOpTable $instanceRiskOpTable,
        private UserTable $userTable,
        private ImportCacheHelper $importCacheHelper,
        ConnectedUserService $connectedUserService
    ) {
        /** @var User $connectedUser */
        $connectedUser = $connectedUserService->getConnectedUser();
        $this->connectedUser = $connectedUser;
    }

    /**
     * @return AnrSupervisor[]
     */
    public function getList(Anr $anr, ?string $filter = null, ?string $role = null, ?bool $isActive = null): array
    {
        if ($filter !== null || $role !== null || $isActive !== null) {
            return $this->anrSupervisorTable->findByAnrFiltered($anr, $filter, $role, $isActive);
        }

        return $this->anrSupervisorTable->findByAnrOrdered($anr);
    }

    public function get(Anr $anr, int $id): AnrSupervisor
    {
        /** @var AnrSupervisor $supervisor */
        $supervisor = $this->anrSupervisorTable->findByIdAndAnr($id, $anr);

        return $supervisor;
    }

    public function create(Anr $anr, array $data): AnrSupervisor
    {
        $linkedUser = $this->findLinkedUser($data['linkedUserId'] ?? null);
        if ($linkedUser !== null) {
            $this->assertCanManageLinkedUsers();
        }

        $supervisor = (new AnrSupervisor())
            ->setAnr($anr)
            ->setLinkedUser($linkedUser)
            ->setRolePosition($this->normalizeRolePosition($data['rolePosition'] ?? null))
            ->setIsActive(array_key_exists('isActive', $data)
                ? (bool)$data['isActive']
                : true)
            ->setCreator($this->connectedUser->getEmail());

        $this->applySupervisorIdentity($supervisor, $data, $linkedUser);
        $this->syncRoles($supervisor, (array)($data['roles'] ?? []));
        $this->anrSupervisorTable->save($supervisor);

        return $supervisor;
    }

    public function update(Anr $anr, int $id, array $data): AnrSupervisor
    {
        $supervisor = $this->get($anr, $id);
        $linkedUser = $supervisor->getLinkedUser();

        if (array_key_exists('linkedUserId', $data)) {
            if ((int)($data['linkedUserId'] ?? 0) !== (int)($linkedUser?->getId() ?? 0)) {
                $this->assertCanManageLinkedUsers();
            }
            $linkedUser = $this->findLinkedUser($data['linkedUserId']);
            $supervisor->setLinkedUser($linkedUser);
        }
        $this->applySupervisorIdentity($supervisor, $data, $linkedUser);
        if (array_key_exists('rolePosition', $data)) {
            $supervisor->setRolePosition($this->normalizeRolePosition($data['rolePosition']));
        }
        if (array_key_exists('isActive', $data)) {
            $supervisor->setIsActive((bool)$data['isActive']);
        }
        if (array_key_exists('roles', $data)) {
            $this->syncRoles($supervisor, (array)$data['roles']);
        }

        $supervisor->setUpdater($this->connectedUser->getEmail());
        $this->anrSupervisorTable->save($supervisor);

        return $supervisor;
    }

    public function delete(Anr $anr, int $id): void
    {
        $this->patchStatus($anr, $id, false);
    }

    public function patchStatus(Anr $anr, int $id, bool $isActive): AnrSupervisor
    {
        $supervisor = $this->get($anr, $id);
        $supervisor->setIsActive($isActive)->setUpdater($this->connectedUser->getEmail());
        $this->anrSupervisorTable->save($supervisor);

        return $supervisor;
    }

    public function validateNoDuplicateSupervisor(Anr $anr, array $data, ?int $excludeSupervisorId = null): void
    {
        $existingSupervisor = $excludeSupervisorId === null ? null : $this->get($anr, $excludeSupervisorId);
        if (array_key_exists('linkedUserId', $data)
            && (int)($data['linkedUserId'] ?? 0) !== (int)($existingSupervisor?->getLinkedUser()?->getId() ?? 0)
        ) {
            $this->assertCanManageLinkedUsers();
        }

        $linkedUser = array_key_exists('linkedUserId', $data)
            ? $this->findLinkedUser($data['linkedUserId'])
            : $existingSupervisor?->getLinkedUser();

        if ($linkedUser !== null
            && $this->anrSupervisorTable->findLinkedByAnrAndUser($anr, $linkedUser, $excludeSupervisorId) !== null
        ) {
            throw new Exception('This user is already linked to another supervisor in this analysis.', 412);
        }

        $name = $linkedUser !== null
            ? $this->buildLinkedUserName($linkedUser)
            : trim((string)($data['name'] ?? $existingSupervisor?->getName() ?? ''));
        $email = $linkedUser !== null
            ? $this->normalizeEmail($linkedUser->getEmail())
            : $this->normalizeEmail($data['email'] ?? $existingSupervisor?->getEmail() ?? null);

        if ($name === '' && $email === null) {
            return;
        }

        $duplicateSupervisor = $this->anrSupervisorTable->findOneByAnrAndNormalizedIdentity(
            $anr,
            $email,
            $name,
            $excludeSupervisorId
        );
        if ($duplicateSupervisor !== null) {
            throw new Exception('A supervisor with the same name or email already exists in this analysis.', 412);
        }
    }

    /**
     * @return array<int, array{id:int, firstname:string, lastname:string, email:string}>
     */
    public function getLinkableUsers(Anr $anr, string $filter = '', ?int $excludeSupervisorId = null): array
    {
        $this->assertCanManageLinkedUsers();

        $result = [];
        $linkedUserIds = $this->anrSupervisorTable->getLinkedUserIdsByAnr($anr, $excludeSupervisorId);
        /** @var User $user */
        foreach ($this->userTable->findBySearchString($filter) as $user) {
            if (in_array($user->getId(), $linkedUserIds, true)) {
                continue;
            }

            $result[] = [
                'id' => $user->getId(),
                'firstname' => $user->getFirstname(),
                'lastname' => $user->getLastname(),
                'email' => $user->getEmail(),
            ];
        }

        return $result;
    }

    public function findLinkedSupervisor(Anr $anr, User $user): ?AnrSupervisor
    {
        return $this->anrSupervisorTable->findLinkedActiveByAnrAndUser($anr, $user);
    }

    public function userHasLinkedRole(Anr $anr, User $user, string $role): bool
    {
        return $this->anrSupervisorTable->hasLinkedActiveRole($anr, $user, $role);
    }

    /**
     * @return AnrSupervisor[]
     */
    public function getLinkedSupervisorsByUser(User $user): array
    {
        return $this->anrSupervisorTable->findLinkedActiveByUser($user);
    }

    public function getOrCreateSupervisor(
        Anr $anr,
        ?string $name,
        ?string $email = null,
        array $roles = [AnrSupervisorRole::ROLE_RISK_OWNER],
        bool $saveInDb = true
    ): ?AnrSupervisor {
        $normalizedName = trim((string)$name);
        $normalizedEmail = $this->normalizeEmail($email);
        if ($normalizedName === '' && $normalizedEmail === null) {
            return null;
        }

        $supervisor = $this->findSupervisorByIdentity($anr, $normalizedEmail, $normalizedName);
        if ($supervisor === null) {
            return $this->createSupervisorEntity(
                $anr,
                $normalizedName,
                $normalizedEmail,
                $roles,
                saveInDb: $saveInDb
            );
        }

        $isUpdated = false;
        foreach ($roles as $role) {
            if (!in_array($role, AnrSupervisorRole::getAvailableRoles(), true)) {
                throw new Exception(sprintf('Unsupported supervisor role "%s".', $role), 412);
            }
            if (!$supervisor->hasRole($role)) {
                $this->syncRoles($supervisor, array_merge($supervisor->getRolesArray(), [$role]));
                $isUpdated = true;
            }
        }
        if (!$supervisor->isActive()) {
            $supervisor->setIsActive(true);
            $isUpdated = true;
        }
        if ($isUpdated) {
            $supervisor->setUpdater($this->connectedUser->getEmail());
            $this->anrSupervisorTable->save($supervisor, $saveInDb);
        }
        $this->cacheSupervisor($anr, $supervisor);

        return $supervisor;
    }

    public function createSupervisorEntity(
        Anr $anr,
        ?string $name,
        ?string $email = null,
        array $roles = [AnrSupervisorRole::ROLE_RISK_OWNER],
        ?string $rolePosition = null,
        ?User $linkedUser = null,
        bool $isActive = true,
        bool $saveInDb = true
    ): ?AnrSupervisor {
        $normalizedName = trim((string)$name);
        $normalizedEmail = $this->normalizeEmail($email);
        if ($normalizedName === '' && $normalizedEmail === null) {
            return null;
        }

        $supervisor = (new AnrSupervisor())
            ->setAnr($anr)
            ->setName($normalizedName !== '' ? $normalizedName : (string)$normalizedEmail)
            ->setEmail($normalizedEmail)
            ->setLinkedUser($linkedUser)
            ->setRolePosition($this->normalizeRolePosition($rolePosition))
            ->setIsActive($isActive)
            ->setCreator($this->connectedUser->getEmail());
        $this->syncRoles($supervisor, $roles);
        $this->anrSupervisorTable->save($supervisor, $saveInDb);
        $this->cacheSupervisor($anr, $supervisor);

        return $supervisor;
    }

    public function assignRiskOwnerSupervisorById(
        Anr $anr,
        mixed $supervisorId,
        InstanceRisk|InstanceRiskOp $instanceRisk
    ): void {
        if ($supervisorId === null || $supervisorId === '' || (int)$supervisorId === 0) {
            $instanceRisk->setRiskOwnerSupervisor(null);

            return;
        }

        $supervisor = $this->get($anr, (int)$supervisorId);
        if (!$supervisor->hasRole(AnrSupervisorRole::ROLE_RISK_OWNER)) {
            $this->update($anr, $supervisor->getId(), [
                'roles' => array_merge($supervisor->getRolesArray(), [AnrSupervisorRole::ROLE_RISK_OWNER]),
            ]);
            $supervisor = $this->get($anr, (int)$supervisorId);
        }

        $instanceRisk->setRiskOwnerSupervisor($supervisor);
    }

    public function getResidualRiskApproverSupervisor(
        Anr $anr,
        mixed $supervisorId
    ): ?AnrSupervisor {
        if ($supervisorId === null || $supervisorId === '' || (int)$supervisorId === 0) {
            return null;
        }

        $supervisor = $this->get($anr, (int)$supervisorId);
        if (!$supervisor->isActive()
            || !$supervisor->hasRole(AnrSupervisorRole::ROLE_RESIDUAL_RISK_APPROVER)
        ) {
            throw new Exception('Residual risk approver must be an active supervisor with approver role.', 412);
        }

        return $supervisor;
    }

    public function assignRiskOwnerSupervisorData(
        Anr $anr,
        array $supervisorData,
        InstanceRisk|InstanceRiskOp $instanceRisk,
        bool $saveInDb = true
    ): void {
        $name = trim((string)($supervisorData['name'] ?? ''));
        $email = trim((string)($supervisorData['email'] ?? ''));
        $instanceRisk->setRiskOwnerSupervisor($this->getOrCreateSupervisor(
            $anr,
            $name !== '' ? $name : null,
            $email !== '' ? $email : null,
            [AnrSupervisorRole::ROLE_RISK_OWNER],
            $saveInDb
        ));
    }

    public function assignRiskOwnerSupervisorName(
        Anr $anr,
        ?string $ownerName,
        InstanceRisk|InstanceRiskOp $instanceRisk,
        bool $saveInDb = true
    ): void {
        $normalizedOwnerName = trim((string)$ownerName);
        $instanceRisk->setRiskOwnerSupervisor($this->getOrCreateSupervisor(
            $anr,
            $normalizedOwnerName !== '' ? $normalizedOwnerName : null,
            null,
            [AnrSupervisorRole::ROLE_RISK_OWNER],
            $saveInDb
        ));
    }

    public function processForImport(Anr $anr, array $supervisorsData): void
    {
        foreach ($supervisorsData as $supervisorData) {
            $name = trim((string)($supervisorData['name'] ?? ''));
            $email = $this->normalizeEmail($supervisorData['email'] ?? null);
            if ($name === '' && $email === null) {
                continue;
            }

            $supervisor = $this->findSupervisorByIdentity($anr, $email, $name);
            if ($supervisor === null) {
                $supervisor = (new AnrSupervisor())
                    ->setAnr($anr)
                    ->setName($name !== '' ? $name : (string)$email)
                    ->setEmail($email)
                    ->setRolePosition($this->normalizeRolePosition($supervisorData['rolePosition'] ?? null))
                    ->setIsActive((bool)($supervisorData['isActive'] ?? true))
                    ->setCreator($this->connectedUser->getEmail());
            } else {
                $supervisor->setName($name !== '' ? $name : $supervisor->getName())
                    ->setEmail($email ?? $supervisor->getEmail())
                    ->setRolePosition($this->normalizeRolePosition($supervisorData['rolePosition'] ?? null))
                    ->setIsActive((bool)($supervisorData['isActive'] ?? true))
                    ->setUpdater($this->connectedUser->getEmail());
            }

            $this->syncRoles($supervisor, array_merge(
                $supervisor->getRolesArray(),
                $this->filterRoles((array)($supervisorData['roles'] ?? []))
            ));
            $this->anrSupervisorTable->save($supervisor, false);
            $this->cacheSupervisor($anr, $supervisor);
        }
    }

    public function prepareSupervisorReference(?AnrSupervisor $supervisor): ?array
    {
        if ($supervisor === null) {
            return null;
        }

        $linkedUser = $supervisor->getLinkedUser();

        return [
            'id' => $supervisor->getId(),
            'name' => $supervisor->getName(),
            'email' => $supervisor->getEmail(),
            'rolePosition' => $supervisor->getRolePosition(),
            'linkedUserId' => $linkedUser?->getId(),
            'linkedUser' => $linkedUser === null ? null : [
                'id' => $linkedUser->getId(),
                'firstname' => $linkedUser->getFirstname(),
                'lastname' => $linkedUser->getLastname(),
                'email' => $linkedUser->getEmail(),
            ],
            'roles' => $supervisor->getRolesArray(),
            'isActive' => $supervisor->isActive(),
        ];
    }

    public function hasAssignedRisks(AnrSupervisor $supervisor): bool
    {
        $supervisorId = $supervisor->getId();

        return $this->instanceRiskTable->hasAssignmentsForSupervisorId($supervisor->getAnr(), $supervisorId)
            || $this->instanceRiskOpTable->hasAssignmentsForSupervisorId($supervisor->getAnr(), $supervisorId);
    }

    private function syncRoles(AnrSupervisor $supervisor, array $roles): void
    {
        $roles = array_values(array_unique($roles));
        foreach ($supervisor->getRoles()->toArray() as $roleEntity) {
            if (!in_array($roleEntity->getRole(), $roles, true)) {
                $supervisor->removeRole($roleEntity);
            }
        }
        $existingRoles = $supervisor->getRolesArray();
        foreach ($roles as $role) {
            if (in_array($role, $existingRoles, true)) {
                continue;
            }
            $supervisor->addRole(
                (new AnrSupervisorRole())
                    ->setRole($role)
                    ->setCreator($this->connectedUser->getEmail())
            );
        }
    }

    private function applySupervisorIdentity(AnrSupervisor $supervisor, array $data, ?User $linkedUser): void
    {
        if ($linkedUser !== null) {
            $supervisor->setName($this->buildLinkedUserName($linkedUser))
                ->setEmail($this->normalizeEmail($linkedUser->getEmail()));

            return;
        }

        if (array_key_exists('name', $data)) {
            $supervisor->setName((string)$data['name']);
        }
        if (array_key_exists('email', $data)) {
            $supervisor->setEmail($data['email']);
        }
    }

    private function normalizeEmail(mixed $email): ?string
    {
        $email = trim((string)$email);
        if ($email === '') {
            return null;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Supervisor email is invalid.', 412);
        }

        return mb_strtolower($email);
    }

    private function buildLinkedUserName(User $linkedUser): string
    {
        $fullName = trim(sprintf('%s %s', (string)$linkedUser->getFirstname(), (string)$linkedUser->getLastname()));
        if ($fullName !== '') {
            return $fullName;
        }

        return (string)$linkedUser->getEmail();
    }

    /**
     * @return string[]
     */
    private function filterRoles(array $roles): array
    {
        $normalizedRoles = [];
        foreach ($roles as $role) {
            $role = trim((string)$role);
            if (in_array($role, AnrSupervisorRole::getAvailableRoles(), true)) {
                $normalizedRoles[] = $role;
            }
        }

        return $normalizedRoles;
    }

    private function findSupervisorByIdentity(Anr $anr, ?string $email, ?string $name): ?AnrSupervisor
    {
        $cacheKey = $this->getSupervisorCacheKey($anr, $email, $name);
        if ($cacheKey !== null) {
            /** @var ?AnrSupervisor $cachedSupervisor */
            $cachedSupervisor = $this->importCacheHelper->getItemFromArrayCache('supervisors_by_identity', $cacheKey);
            if ($cachedSupervisor !== null) {
                return $cachedSupervisor;
            }
        }

        $supervisor = $this->anrSupervisorTable->findOneByAnrAndNormalizedIdentity($anr, $email, $name);
        if ($supervisor !== null) {
            $this->cacheSupervisor($anr, $supervisor);
        }

        return $supervisor;
    }

    private function cacheSupervisor(Anr $anr, AnrSupervisor $supervisor): void
    {
        $emailCacheKey = $this->getSupervisorCacheKey($anr, $supervisor->getEmail(), null);
        if ($emailCacheKey !== null) {
            $this->importCacheHelper->addItemToArrayCache('supervisors_by_identity', $supervisor, $emailCacheKey);
        }

        $nameCacheKey = $this->getSupervisorCacheKey($anr, null, $supervisor->getName());
        if ($nameCacheKey !== null) {
            $this->importCacheHelper->addItemToArrayCache('supervisors_by_identity', $supervisor, $nameCacheKey);
        }
    }

    private function getSupervisorCacheKey(Anr $anr, ?string $email, ?string $name): ?string
    {
        $normalizedEmail = trim((string)$email);
        if ($normalizedEmail !== '') {
            return $anr->getId() . '|email|' . mb_strtolower($normalizedEmail);
        }

        $normalizedName = trim((string)$name);
        if ($normalizedName === '') {
            return null;
        }

        return $anr->getId() . '|name|' . mb_strtolower($normalizedName);
    }

    private function normalizeRolePosition(mixed $rolePosition): ?string
    {
        $rolePosition = trim((string)$rolePosition);

        return $rolePosition === '' ? null : $rolePosition;
    }

    private function findLinkedUser(mixed $linkedUserId): ?User
    {
        if (empty($linkedUserId)) {
            return null;
        }

        try {
            /** @var User $user */
            $user = $this->userTable->findById((int)$linkedUserId);
        } catch (EntityNotFoundException) {
            throw new Exception('Linked user was not found.', 412);
        }

        return $user;
    }

    private function assertCanManageLinkedUsers(): void
    {
        if (!$this->connectedUser->hasRole(UserRole::SUPER_ADMIN_FO)) {
            throw new Exception('Only an administrator can link a supervisor to a system user.', 412);
        }
    }
}
