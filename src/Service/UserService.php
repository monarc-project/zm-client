<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Exception\UserNotLoggedInException;
use Monarc\Core\Model\Entity\UserRoleSuperClass;
use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\PasswordService;
use Monarc\Core\Service\UserService as CoreUserService;
use Monarc\FrontOffice\Model\Entity\User;
use Monarc\FrontOffice\Model\Entity\UserAnr;
use Monarc\FrontOffice\Table\AnrTable;
use Monarc\FrontOffice\Table\UserTable;

class UserService extends CoreUserService
{
    private AnrTable $anrTable;

    private PasswordService $passwordService;

    private User $connectedUser;

    public function __construct(
        UserTable $userTable,
        array $config,
        AnrTable $anrTable,
        ConnectedUserService $connectedUserService,
        PasswordService $passwordService
    ) {
        parent::__construct($userTable, $connectedUserService, $config);

        $this->anrTable = $anrTable;
        $this->passwordService = $passwordService;
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getCompleteUser(int $userId): array
    {
        /** @var UserSuperClass $user */
        $user = $this->userTable->findById($userId);

        // TODO: replace with normalization layer.
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

    /**
     * @throws EntityNotFoundException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws UserNotLoggedInException
     */
    public function create(array $data): UserSuperClass
    {
        if (empty($data['language'])) {
            $data['language'] = $this->defaultLanguageIndex;
        }
        if (empty($data['creator'])) {
            $data['creator'] = $this->connectedUser->getEmail();
        }

        if (isset($data['anrs'])) {
            foreach ($data['anrs'] as $anr) {
                $anrEntity = $this->anrTable->findById($anr['id']);

                $data['userAnrs'][] = (new UserAnr())
                    ->setAnr($anrEntity)
                    ->setRwd($anr['rwd'])
                    ->setCreator($data['creator']);
            }
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
     *
     * @throws Exception
     */
    private function verifySystemUserUpdate(User $user, array $data = [])
    {
        if ($user->isSystemUser()) {
            if (!empty($data['role']) && !\in_array(UserRoleSuperClass::SUPER_ADMIN_FO, $data['role'], true)) {
                throw new Exception('You can not remove admin role from the "System" user', 412);
            }

            if (isset($data['status']) && $data['status'] === User::STATUS_INACTIVE) {
                throw new Exception('You can not deactivate the "System" user', 412);
            }
        }
    }

    /**
     * @throws EntityNotFoundException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws UserNotLoggedInException
     */
    public function updateUserAnr(User $user, array $data): void
    {
        $connectedUserName = $this->connectedUserService->getConnectedUser()->getFirstname()
            . ' ' . $this->connectedUserService->getConnectedUser()->getLastname();
        if (isset($data['anrs'])) {
            $assignedAnrIds = array_map('intval', array_column($data['anrs'], 'id'));
            foreach ($user->getUserAnrs() as $userAnr) {
                $assignedAnrKey = array_search($userAnr->getAnr()->getId(), $assignedAnrIds, true);
                if ($assignedAnrKey === false) {
                    $user->removeUserAnr($userAnr);
                } else {
                    $userAnr
                        ->setRwd($data['anrs'][$assignedAnrKey]['rwd'])
                        ->setUpdater($connectedUserName);
                    unset($data['anrs'][$assignedAnrKey]);
                }
            }
            foreach ($data['anrs'] as $anr) {
                $user->addUserAnr(
                    (new UserAnr())
                        ->setAnr($this->anrTable->findById($anr['id']))
                        ->setRwd($anr['rwd'])
                        ->setCreator($connectedUserName)
                );
            }

            $this->userTable->save($user);
        }
    }
}
