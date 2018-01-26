<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcFO\Model\Table\UserAnrTable;
use MonarcFO\Model\Table\UserRoleTable;
use MonarcFO\Model\Table\UserTable;

/**
 * This class is the service that handles the users. This is a simple CRUD service that inherits from its
 * MonarcCore parent.
 * @package MonarcCore\Service
 */
class UserService extends \MonarcCore\Service\UserService
{
    protected $userAnrTable;
    protected $userRoleTable;
    protected $userAnrService;
    protected $userRoleService;
    protected $anrTable;
    protected $snapshotCliTable;

    /**
     * The list of fields deleted during a GET
     * @var array
     */
    protected $forbiddenFields = ['password'];


    protected function filterGetFields(&$entity, $forbiddenFields = false)
    {
        $forbiddenFields = (!$forbiddenFields) ? $this->forbiddenFields : $forbiddenFields;
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
        //retrieve user's list
        /** @var UserTable $table */
        $table = $this->get('table');
        $users = $table->fetchAllFiltered(
            array_keys($this->get('entity')->getJsonArray()),
            $page,
            $limit,
            $this->parseFrontendOrder($order),
            $this->parseFrontendFilter($filter, $this->filterColumns),
            $filterAnd
        );



        //retrieve role for each users
        /** @var UserRoleTable $userRoleTable */
        $userRoleTable = $this->get('userRoleTable');
        $usersRoles = $userRoleTable->fetchAllObject();
        foreach ($users as $key => $user) {
            foreach ($usersRoles as $userRole) {
                if ($user['id'] == $userRole->user->id) {
                    $users[$key]['roles'][] = $userRole->role;
                }
            }
        }

        //retrieve anr access for each users
        /** @var UserAnrTable $userAnrTable */
        $userAnrTable = $this->get('userAnrTable');
        $usersAnrs = $userAnrTable->fetchAllObject();
        foreach ($users as $key => $user) {
            foreach ($usersAnrs as $userAnr) {
                if ($user['id'] == $userAnr->user->id) {
                    $anr = [
                        'id' => $userAnr->anr->id,
                        'label1' => $userAnr->anr->label1,
                        'label2' => $userAnr->anr->label2,
                        'label3' => $userAnr->anr->label3,
                        'label4' => $userAnr->anr->label4,
                        'rwd' => $userAnr->rwd,
                    ];
                    $users[$key]['anrs'][] = $anr;
                }
            }
        }
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
        //retrieve user information
        /** @var UserTable $table */
        $table = $this->get('table');
        $user = $table->get($id);

        //retrieve user roles
        /** @var UserRoleTable $userRoleTable */
        $userRoleTable = $this->get('userRoleTable');
        $usersRoles = $userRoleTable->getEntityByFields(['user' => $id]);
        foreach ($usersRoles as $userRole) {
            $user['role'][] = $userRole->role;
        }

        //retrieve anr that are not snapshots
        /** @var SnapshotTable $snapshotCliTable */
        $snapshotCliTable = $this->get('snapshotCliTable');
        $snapshots = $snapshotCliTable->fetchAll();
        $anrsSnapshots = [0];
        foreach ($snapshots as $snapshot) {
            $anrsSnapshots[$snapshot['anr']->id] = $snapshot['anr']->id;
        }
        $anrs = $this->get('anrTable')->getEntityByFields(['id' => ['op' => 'NOT IN', 'value' => $anrsSnapshots]]);
        $user['anrs'] = [];
        foreach ($anrs as $a) {
            $user['anrs'][$a->get('id')] = [
                'id' => $a->get('id'),
                'label1' => $a->get('label1'),
                'label2' => $a->get('label2'),
                'label3' => $a->get('label3'),
                'label4' => $a->get('label4'),
                'rwd' => -1,
            ];
        }

        //retrieve user access
        /** @var UserAnrTable $userAnrTable */
        $userAnrTable = $this->get('userAnrTable');
        $usersAnrs = $userAnrTable->getEntityByFields(['user' => $user['id']]);
        foreach ($usersAnrs as $userAnr) {
            if (isset($user['anrs'][$userAnr->get('anr')->get('id')])) {
                $user['anrs'][$userAnr->get('anr')->get('id')]['rwd'] = $userAnr->get('rwd');
            }
        }
        $user['anrs'] = array_values($user['anrs']);

        // fields we never want to return
        unset($user['password']);

        return $user;
    }


    /**
     * @inheritdoc
     */
    public function create($data, $last = true)
    {
        //create user
        $user = $this->get('entity');
        $data['status'] = 1;
        if (empty($data['language'])) {
            $data['language'] = $this->getLanguage();
        }
        $user->exchangeArray($data);
        /** @var UserTable $table */
        $table = $this->get('table');
        $id = $table->save($user);

        //associate role to user
        if (isset($data['role'])) {
            $i = 1;
            $nbRoles = count($data['role']);
            foreach ($data['role'] as $role) {
                $dataUserRole = [
                    'user' => $id,
                    'role' => $role,
                ];
                /** @var UserRoleService $userRoleService */
                $userRoleService = $this->get('userRoleService');
                $userRoleService->create($dataUserRole, ($i == $nbRoles));
                $i++;
            }
        }

        //give anr access to user
        if (isset($data['anrs'])) {
            /** @var SnapshotTable $snapshotCliTable */
            $snapshotCliTable = $this->get('snapshotCliTable');
            $snapshots = $snapshotCliTable->fetchAll();

            $anrsSnapshots = [0];
            foreach ($snapshots as $snapshot) {
                $anrsSnapshots[$snapshot['anr']->id] = $snapshot['anr']->id;
            }
            foreach ($data['anrs'] as $anr) {
                if (!isset($anrsSnapshots[$anr['id']])) {
                    $dataAnr = [
                        'user' => $id,
                        'anr' => $anr['id'],
                        'rwd' => $anr['rwd'],
                    ];
                    /** @var UserAnrService $userAnrService */
                    $userAnrService = $this->get('userAnrService');
                    $userAnrService->create($dataAnr);
                }
            }
        }

        return $id;
    }

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        $this->verifyAuthorizedAction($id, $data);

        $user = $this->get('table')->getEntity($id);

        if (isset($data['role'])) {
            $this->manageRoles($user, $data);
        }

        $this->updateUserAnr($id, $data);

        if (isset($data['dateEnd'])) {
            $data['dateEnd'] = new \DateTime($data['dateEnd']);
        }

        if (isset($data['dateStart'])) {
            $data['dateStart'] = new \DateTime($data['dateStart']);
        }

        return parent::update($id, $data);
    }


    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        if (isset($data['password'])) {
            $this->validatePassword($data);
        }

        $user = $this->get('table')->getEntity($id);

        if (isset($data['role'])) {
            $this->manageRoles($user, $data);
        }

        $this->verifyAuthorizedAction($id, $data);

        $this->updateUserAnr($id, $data);

        if (isset($data['dateEnd'])) {
            $data['dateEnd'] = new \DateTime($data['dateEnd']);
        }

        if (isset($data['dateStart'])) {
            $data['dateStart'] = new \DateTime($data['dateStart']);
        }

        return parent::patch($id, $data);
    }

    /**
     * @inheritdoc
     */
    public function delete($id)
    {
        $this->verifyAuthorizedAction($id, null);

        return parent::delete($id);
    }

    /**
     * Checks whether or not a specific action is authorized or not for the specified user id
     * @param int $id The user ID
     * @param array $data The action information array
     * @throws \MonarcCore\Exception\Exception If the user is not found, or if the action is invalid
     */
    public function verifyAuthorizedAction($id, $data)
    {
        //retrieve user status (admin or not)
        /** @var UserRoleTable $userRoleTable */
        $isAdmin = false;
        $userRoleTable = $this->get('userRoleTable');
        $userRoles = $userRoleTable->getEntityByFields(['user' => $id]);
        foreach ($userRoles as $userRole) {
            if ($userRole->role == \MonarcFO\Model\Entity\UserRole::SUPER_ADMIN_FO) {
                $isAdmin = true;
                break;
            }
        }

        if ($isAdmin) {
            /** @var \MonarcFO\Model\Table\UserTable $userTable */
            $userTable = $this->get('table');
            $user = $userTable->getEntity($id);

            //retrieve number of admin users
            $nbActivateAdminUser = 0;
            $adminUsersRoles = $userRoleTable->getEntityByFields(['role' => \MonarcFO\Model\Entity\UserRole::SUPER_ADMIN_FO, 'user'=>['op'=>'!=','value'=>$id]]);
            foreach ($adminUsersRoles as $adminUsersRole) {
                if (($adminUsersRole->user->status) && (is_null($adminUsersRole->user->dateEnd))) {
                    $nbActivateAdminUser++;
                }
            }

            //verify if status, dateEnd and role can be changed or user be deactivated
            if (
                ((($user->status) && (isset($data['status'])) && (!$data['status'])) //change status 1 -> 0
                 ||
                 ((is_null($user->dateEnd)) && (isset($data['dateEnd']))) //change dateEnd null -> date
                 ||
                 ((isset($data['role'])) && (!in_array('superadminfo', $data['role']))) //delete superadminfo role
                 ||
                 (is_null($data))) //delete superadminfo role
                 &&
                 $nbActivateAdminUser <= 1 //verify if this is not the last superadminfo and verify date_end
            ) {
                throw new \MonarcCore\Exception\Exception('You can not deactivate, delete or change role of the last admin', 412);
            }
        }
    }

    /**
     * Updates the access permissions to ANRs for the specified user ID
     * @param int $id The user ID
     * @param array $data An array of ANRs in the 'anrs' key that the user may access
     */
    public function updateUserAnr($id, $data)
    {
        if (isset($data['anrs'])) {

            //retieve current user anrs
            /** @var UserAnrTable $userAnrTable */
            $userAnrTable = $this->get('userAnrTable');
            $userAnrs = $userAnrTable->getEntityByFields(['user' => $id]);
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
                $futureUserAnrs[$userAnr['id']] = intval($userAnr['rwd']);
            }
            /** @var SnapshotTable $snapshotCliTable */
            $snapshotCliTable = $this->get('snapshotCliTable');
            $snapshots = $snapshotCliTable->fetchAll();
            foreach ($snapshots as $snapshot) {
                unset($futureUserAnrs[$snapshot['anr']->id]);
            }

            //add new anr access to user
            /** @var UserAnrService $userAnrService */
            $userAnrService = $this->get('userAnrService');
            foreach ($futureUserAnrs as $key => $futureUserAnr) {
                if (!isset($currentUserAnrs[$key])) {
                    $userAnrService->create([
                        'user' => $id,
                        'anr' => $key,
                        'rwd' => $futureUserAnr,
                    ]);
                } else if ($currentUserAnrs[$key]['rwd'] != $futureUserAnrs[$key]) {
                    $userAnrService->patch($currentUserAnrs[$key]['id'], ['rwd' => $futureUserAnrs[$key]]);
                }
            }

            //delete old anrs access to user
            foreach ($currentUserAnrs as $key => $currentUserAnr) {
                if (!isset($futureUserAnrs[$key])) {
                    $userAnrService->delete($currentUserAnr['id']);
                }
            }
        }
    }
}
