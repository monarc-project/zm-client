<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use DateTime;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\PasswordService;
use Monarc\Core\Service\UserService as CoreUserService;
use Monarc\FrontOffice\Model\Entity\User;
use Monarc\FrontOffice\Model\Entity\UserAnr;
use Monarc\FrontOffice\Model\Entity\UserRole;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\UserTable;

/**
 * This class is the service that handles the users. This is a simple CRUD service that inherits from its
 * Monarc\Core parent.
 * @package Monarc\Core\Service
 */
class UserService extends CoreUserService
{
    /**
     * The list of fields deleted during a GET
     * @var array
     */
    protected $forbiddenFields = ['password', 'recoveryCodes'];

    /** @var AnrTable */
    private $anrTable;

    /** @var PasswordService */
    private $passwordService;

    /** @var ConnectedUserService */
    private $connectedUserService;

    public function __construct(
        UserTable $userTable,
        array $config,
        AnrTable $anrTable,
        ConnectedUserService $connectedUserService,
        PasswordService $passwordService
    ) {
        parent::__construct($userTable, $config);

        $this->anrTable = $anrTable;
        $this->connectedUserService = $connectedUserService;
        $this->passwordService = $passwordService;
    }

    // TODO: remove me.
    protected function filterGetFields(&$entity, $forbiddenFields = [])
    {
        if (empty($forbiddenFields)) {
            $forbiddenFields = $this->forbiddenFields;
        }

        foreach ($entity as $id => $user) {
            foreach ($entity[$id] as $key => $value) {
                if (in_array($key, $forbiddenFields)) {
                    unset($entity[$id][$key]);
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null)
    {
        $users = $this->userTable->fetchAllFiltered(
            ['id', 'status', 'firstname', 'lastname', 'email', 'language', 'roles'],
            $page,
            $limit,
            $this->parseFrontendOrder($order),
            $this->parseFrontendFilter($filter, $this->filterColumns),
            $filterAnd
        );

        $this->filterGetFields($users);

        return $users;
    }

    public function getCompleteUser(int $userId): array
    {
        /** @var User $user */
        $user = $this->userTable->findById($userId);

        // TODO: replace with normalization layer.
        return [
            'id' => $user->getId(),
            'status' => $user->getStatus(),
            'firstname' => $user->getFirstname(),
            'lastname' => $user->getLastname(),
            'email' => $user->getEmail(),
            'language' => $user->getLanguage(),
            'role' => $user->getRoles(),
            'anrs' => $this->anrTable->fetchAnrsExcludeSnapshotsWithUserRights($userId),
        ];
    }


    /**
     * @inheritdoc
     */
    public function create(array $data): UserSuperClass
    {
        if (empty($data['language'])) {
            $data['language'] = $this->defaultLanguageIndex;
        }
        if (empty($data['creator'])) {
            $data['creator'] = $this->userTable->getConnectedUser()->getFirstname() . ' '
                . $this->userTable->getConnectedUser()->getLastname();
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
        $this->userTable->saveEntity($user);

        return $user;
    }

    public function update(int $userId, array $data): UserSuperClass
    {
        /** @var User $user */
        $user = $this->getUpdatedUser($userId, $data);

        $this->verifySystemUserUpdate($user, $data);

        $this->updateUserAnr($user, $data);

        if (isset($data['dateEnd'])) {
            $data['dateEnd'] = new DateTime($data['dateEnd']);
        }

        if (isset($data['dateStart'])) {
            $data['dateStart'] = new DateTime($data['dateStart']);
        }

        $this->userTable->saveEntity($user);

        return $user;
    }

    /**
     * @inheritdoc
     */
    public function patch($userId, $data): UserSuperClass
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

        if (isset($data['dateEnd'])) {
            $data['dateEnd'] = new DateTime($data['dateEnd']);
        }

        if (isset($data['dateStart'])) {
            $data['dateStart'] = new DateTime($data['dateStart']);
        }

        $this->userTable->saveEntity($user);

        return $user;
    }

    /**
     * Checks whether or not a specific action is authorized or not for the specified user id
     */
    private function verifySystemUserUpdate(User $user, array $data = [])
    {
        /*
         * We just need to validate if the System created user instead.
        */
        if ($user->isSystemUser()) {
            if (!empty($data['role']) && !in_array(UserRole::SUPER_ADMIN_FO, $data['role'], true)) {
                throw new Exception('You can not remove admin role from the "System" user', 412);
            }

            if (isset($data['status']) && $data['status'] === User::STATUS_INACTIVE) {
                throw new Exception('You can not deactivate the "System" user', 412);
            }
        }
    }

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

            $this->userTable->saveEntity($user);
        }
    }
}
