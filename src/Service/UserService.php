<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\PasswordService;
use Monarc\Core\Service\UserService as CoreUserService;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\User;
use Monarc\FrontOffice\Model\Entity\UserAnr;
use Monarc\FrontOffice\Model\Entity\UserRole;
use Monarc\FrontOffice\Table\AnrTable;
use Monarc\FrontOffice\Table\UserTable;

class UserService extends CoreUserService
{
    public function __construct(
        UserTable $userTable,
        ConnectedUserService $connectedUserService,
        array $config,
        private AnrTable $anrTable,
        private PasswordService $passwordService
    ) {
        parent::__construct($userTable, $connectedUserService, $config);
    }

    public function getCompleteUser(int $userId): array
    {
        /** @var User $user */
        $user = $this->userTable->findById($userId);

        return [
            'id' => $user->getId(),
            'status' => $user->getStatus(),
            'firstname' => $user->getFirstname(),
            'lastname' => $user->getLastname(),
            'email' => $user->getEmail(),
            'language' => $user->getLanguage(),
            'role' => $user->getRolesArray(),
            'anrs' => $this->anrTable->fetchAnrsExcludeSnapshotsWithUserRights($userId),
        ];
    }

    public function create(array $data): UserSuperClass
    {
        if (empty($data['language'])) {
            $data['language'] = $this->defaultLanguageIndex;
        }
        if (empty($data['creator'])) {
            $data['creator'] = $this->connectedUser->getEmail();
        }

        foreach ($data['anrs'] ?? [] as $anr) {
            /** @var Anr $anrEntity */
            $anrEntity = $this->anrTable->findById((int)$anr['id']);

            $data['userAnrs'][] = (new UserAnr())
                ->setAnr($anrEntity)
                ->setRwd($anr['rwd'])
                ->setCreator($data['creator']);
        }

        $user = new User($data);
        $this->userTable->save($user);

        return $user;
    }

    public function update(int $userId, array $data): UserSuperClass
    {
        /** @var User $user */
        $user = $this->getUpdatedUser($userId, $data);

        $this->verifySystemUserUpdate($user, $data);

        $this->updateUserAnr($user, $data);

        $this->userTable->save($user);

        return $user;
    }

    public function patch(int $userId, array $data): UserSuperClass
    {
        if (isset($data['password'])) {
            $this->passwordService->changePasswordWithoutOldPassword($data['id'], $data['password']);
        }

        /** @var User $user */
        $user = $this->getUpdatedUser($userId, $data);

        $this->verifySystemUserUpdate($user, $data);

        if (isset($data['password'])) {
            $user->setPassword($data['password']);
        }

        $this->updateUserAnr($user, $data);

        $this->userTable->save($user);

        return $user;
    }

    /**
     * Checks whether a specific action is authorized or not for the specified user id.
     */
    private function verifySystemUserUpdate(User $user, array $data = [])
    {
        if ($user->isSystemUser()) {
            if (!empty($data['role']) && !\in_array(UserRole::SUPER_ADMIN_FO, $data['role'], true)) {
                throw new Exception('You can not remove admin role from the "System" user', 412);
            }

            if (isset($data['status']) && $data['status'] === UserSuperClass::STATUS_INACTIVE) {
                throw new Exception('You can not deactivate the "System" user', 412);
            }
        }
    }

    public function updateUserAnr(User $user, array $data): void
    {
        if (!empty($data['anrs'])) {
            $assignedAnrIds = array_map('\intval', array_column($data['anrs'], 'id'));
            foreach ($user->getUserAnrs() as $userAnr) {
                $assignedAnrKey = array_search($userAnr->getAnr()->getId(), $assignedAnrIds, true);
                if ($assignedAnrKey !== false) {
                    $userAnr->setRwd((int)$data['anrs'][$assignedAnrKey]['rwd'])
                        ->setUpdater($this->connectedUser->getEmail());
                    unset($data['anrs'][$assignedAnrKey]);
                } else {
                    $user->removeUserAnr($userAnr);
                }
            }
            foreach ($data['anrs'] as $anrData) {
                /** @var Anr $anr */
                $anr = $this->anrTable->findById($anrData['id']);
                $user->addUserAnr(
                    (new UserAnr())->setAnr($anr)->setRwd($anrData['rwd'])->setCreator($this->connectedUser->getEmail())
                );
            }

            $this->userTable->save($user);
        }
    }
}
