<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use DateTime;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\Core\Service\UserService as CoreUserService;
use Monarc\FrontOffice\Model\Entity\User;
use Monarc\FrontOffice\Model\Entity\UserRole;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\SnapshotTable;
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
    protected $forbiddenFields = ['password'];

    /** @var UserAnrService */
    private $userAnrService;

    /** @var AnrTable */
    private $anrTable;

    /** @var SnapshotTable */
    private $snapshotTable;

    public function __construct(
        UserTable $userTable,
        array $config,
        UserAnrService $userAnrService,
        AnrTable $anrTable,
        SnapshotTable $snapshotTable
    ) {
        parent::__construct($userTable, $config);

        $this->userAnrService = $userAnrService;
        $this->anrTable = $anrTable;
        $this->snapshotTable = $snapshotTable;
    }

    // TODO: remove me.
    protected function filterGetFields(&$entity, $forbiddenFields = [])
    {
        if (empty($forbiddenFields)) {
            $forbiddenFields = $this->forbiddenFields;
        }

        foreach ($entity as $id => $user) {
            foreach($entity[$id] as $key => $value){
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

        //retrieve anr access for each users
//        $userAnrTable = $this->get('userAnrTable');
//        $usersAnrs = $userAnrTable->fetchAllObject();
//        foreach ($users as $key => $user) {
//            foreach ($usersAnrs as $userAnr) {
//                if ($user['id'] == $userAnr->user->id) {
//                    $anr = [
//                        'id' => $userAnr->anr->id,
//                        'label1' => $userAnr->anr->label1,
//                        'label2' => $userAnr->anr->label2,
//                        'label3' => $userAnr->anr->label3,
//                        'label4' => $userAnr->anr->label4,
//                        'rwd' => $userAnr->rwd,
//                    ];
//                    $users[$key]['anrs'][] = $anr;
//                }
//            }
//        }

        $this->filterGetFields($users);

        return $users;
    }

    /**
     * Retrieves a complete user profile, including ANRs and permissions
     * @param int $id User's ID
     * @return array User information
     */
    public function getCompleteUser($id)
    {
        /** @var User $user */
        $user = $this->userTable->findById($id);

        //retrieve anr that are not snapshots
        $snapshots = $this->snapshotTable->fetchAll();
        $anrsSnapshots = [];
        foreach ($snapshots as $snapshot) {
            $anrsSnapshots[$snapshot['anr']->id] = $snapshot['anr']->id;
        }

        $anrs = $this->anrTable->getEntityByFields(['id' => ['op' => 'NOT IN', 'value' => $anrsSnapshots]]);
        $anrsData = [];
        foreach ($anrs as $a) {
            $anrsData[$a->get('id')] = [
                'id' => $a->get('id'),
                'label1' => $a->get('label1'),
                'label2' => $a->get('label2'),
                'label3' => $a->get('label3'),
                'label4' => $a->get('label4'),
                'rwd' => -1,
            ];
        }

        // TODO: should be: $user->getAnrs();, but the relation needs to be fixed.
        foreach ($user->getAnrs() as $userAnr) {
            if (isset($anrsData[$userAnr->get('anr')->get('id')])) {
                $anrsData[$userAnr->get('anr')->get('id')]['rwd'] = $userAnr->get('rwd');
            }
        }

        // TODO: replace with normalization layer.
        return [
            'id' => $user->getId(),
            'status' => $user->getStatus(),
            'firstname' => $user->getFirstname(),
            'lastname' => $user->getLastname(),
            'email' => $user->getEmail(),
            'language' => $user->getLanguage(),
            'role' => $user->getRoles(),
            'anrs' => array_values($anrsData),
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
            $anrs = [];
            foreach ($data['anrs'] as $anr) {
                $anrs[] = [
                    'anr' => $this->anrTable->findById($anr['id']),
                    'rwd' => $this->anrTable->findById($anr['rwd']),
                ];
            }
            $data['anrs'] = $anrs;
        }

        $user = new User($data);
        $this->userTable->saveEntity($user);

//        //give anr access to user
//        if (isset($data['anrs'])) {
//            /** @var SnapshotTable $snapshotCliTable */
//            $snapshotCliTable = $this->get('snapshotCliTable');
//            $snapshots = $snapshotCliTable->fetchAll();
//
//            $anrsSnapshots = [0];
//            foreach ($snapshots as $snapshot) {
//                $anrsSnapshots[$snapshot['anr']->id] = $snapshot['anr']->id;
//            }
//            foreach ($data['anrs'] as $anr) {
//                if (!isset($anrsSnapshots[$anr['id']])) {
//                    $dataAnr = [
//                        'user' => $user->getId(),
//                        'anr' => $anr['id'],
//                        'rwd' => $anr['rwd'],
//                    ];
//                    /** @var UserAnrService $userAnrService */
//                    $userAnrService = $this->get('userAnrService');
//                    $userAnrService->create($dataAnr);
//                }
//            }
//        }

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
            $this->validatePassword($data);
        }

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
     * @inheritdoc
     */
    public function delete($userId)
    {
        $user = $this->userTable->findById($userId);
        if ($user->isSystemUser()) {
            throw new Exception('You can not remove the "System" user', 412);
        }

        $this->userTable->deleteEntity($user);
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

        //retrieve user status (admin or not)
//        /** @var UserRoleTable $userRoleTable */
//        $isAdmin = false;
//        $userRoleTable = $this->get('userRoleTable');
//        $userRoles = $userRoleTable->getEntityByFields(['user' => $id]);
//        foreach ($userRoles as $userRole) {
//            if ($userRole->role == \Monarc\FrontOffice\Model\Entity\UserRole::SUPER_ADMIN_FO) {
//                $isAdmin = true;
//                break;
//            }
//
//        if ($isAdmin) {
//            /** @var \Monarc\FrontOffice\Model\Table\UserTable $userTable */
//            $userTable = $this->get('table');
//            $user = $userTable->getEntity($id);
//
//            //retrieve number of admin users
//            $nbActivateAdminUser = 0;
//            $adminUsersRoles = $userRoleTable->getEntityByFields(['role' => UserRole::SUPER_ADMIN_FO, 'user'=>['op'=>'!=','value'=>$id]]);
//            foreach ($adminUsersRoles as $adminUsersRole) {
//                if (($adminUsersRole->user->status) && (is_null($adminUsersRole->user->dateEnd))) {
//                    $nbActivateAdminUser++;
//                }
//            }
//
//            //verify if status, dateEnd and role can be changed or user be deactivated
//            if (
//                ((($user->status) && (isset($data['status'])) && (!$data['status'])) //change status 1 -> 0
//                    ||
//                    ((is_null($user->dateEnd)) && (isset($data['dateEnd']))) //change dateEnd null -> date
//                    ||
//                    ((isset($data['role'])) && (!in_array('superadminfo', $data['role']))) //delete superadminfo role
//                    ||
//                    (is_null($data))) //delete superadminfo role
//                &&
//                $nbActivateAdminUser <= 1 //verify if this is not the last superadminfo and verify date_end
//            ) {
//                throw new Exception('You can not deactivate, delete or change role of the last admin', 412);
//            }
//        }
    }

    /**
     * Updates the access permissions to ANRs for the specified user ID
     * @param int $id The user ID
     * @param array $data An array of ANRs in the 'anrs' key that the user may access
     */
    public function updateUserAnr(User $user, $data)
    {
        if (isset($data['anrs'])) {

            //retieve current user anrs
            $userAnrs = $user->getAnrs();
            $currentUserAnrs = [];
            foreach ($userAnrs as $userAnr) {
                $currentUserAnrs[$userAnr->anr->id] = [
                    'id' => $userAnr->id,
                    'rwd' => $userAnr->rwd
                ];
            }

            //retrieve new anrs for user that are not snapshots
            $futureUserAnrs = [];
            foreach ($data['anrs'] as $userAnr) {
                $futureUserAnrs[$userAnr['id']] = (int)$userAnr['rwd'];
            }

            $snapshots = $this->snapshotTable->fetchAll();
            foreach ($snapshots as $snapshot) {
                unset($futureUserAnrs[$snapshot['anr']->id]);
            }

            //add new anr access to user
            foreach ($futureUserAnrs as $key => $futureUserAnr) {
                if (!isset($currentUserAnrs[$key])) {
                    $this->userAnrService->create([
                        'user' => $user->getId(),
                        'anr' => $key,
                        'rwd' => $futureUserAnr,
                    ]);
                } elseif ($currentUserAnrs[$key]['rwd'] !== $futureUserAnrs[$key]) {
                    $this->userAnrService->patch($currentUserAnrs[$key]['id'], ['rwd' => $futureUserAnrs[$key]]);
                }
            }

            //delete old anrs access to user
            foreach ($currentUserAnrs as $key => $currentUserAnr) {
                if (!isset($futureUserAnrs[$key])) {
                    $this->userAnrService->delete($currentUserAnr['id']);
                }
            }
        }
    }
}
