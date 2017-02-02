<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

use MonarcFO\Model\Table\UserAnrTable;
use MonarcFO\Model\Table\UserRoleTable;
use MonarcFO\Model\Table\UserTable;

/**
 * User Service
 *
 * Class UserService
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
     * Get List
     *
     * @param int $page
     * @param int $limit
     * @param null $order
     * @param null $filter
     * @return mixed
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

        return $users;
    }

    /**
     * Get Complete User
     *
     * @param $id
     * @return bool
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

        return $user;
    }


    /**
     * Create
     *
     * @param $data
     * @param bool $last
     * @return mixed
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
     * Update
     *
     * @param $id
     * @param $data
     * @return mixed
     */
    public function update($id, $data)
    {
        $this->verifyAuthorizedAction($id, $data);

        $this->updateUserRole($id, $data);

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
     * Patch
     *
     * @param $id
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    public function patch($id, $data)
    {
        if (isset($data['password'])) {
            $this->validatePassword($data);
        }

        $this->verifyAuthorizedAction($id, $data);

        $this->updateUserRole($id, $data);

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
     * Delete
     *
     * @param $id
     * @return mixed
     */
    public function delete($id)
    {
        $this->verifyAuthorizedAction($id, null);

        return parent::delete($id);
    }

    /**
     * Verify Authorized Action
     *
     * @param $id
     * @param $data
     * @throws \Exception
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

            //verify if status, dateEnd and role can be change or user be desactivated
            if (
                (($user->status) && (isset($data['status'])) && (!$data['status'])) //change status 1 -> 0
                ||
                ((is_null($user->dateEnd)) && (isset($data['dateEnd']))) //change dateEnd null -> date
                ||
                ((isset($data['role'])) && (!in_array('superadminfo', $data['role']))) //delete superadminfo role
                ||
                (is_null($data)) //delete superadminfo role
            ) {
                //verify if is not the last superadminfo and verify date_end
                if ($nbActivateAdminUser <= 1) {
                    throw new \Exception('You can not desactivate, delete or change role of the last admin', 412);
                }
            }
        }
    }

    /**
     * Update User Role
     *
     * @param $id
     * @param $data
     */
    public function updateUserRole($id, $data)
    {
        if (isset($data['role'])) {

            //delete old roles
            /** @var UserRoleTable $userRoleTable */
            $userRoleTable = $this->get('userRoleTable');
            $userRoles = $userRoleTable->getEntityByFields(['user' => $id]);
            $userRolesArray = [];
            foreach ($userRoles as $userRole) {
                if (!in_array($userRole->role, $data['role'])) {
                    $userRoleTable->delete($userRole->id);
                } else {
                    $userRolesArray[] = $userRole->role;
                }
            }

            //add new roles
            foreach ($data['role'] as $role) {
                if (!in_array($role, $userRolesArray)) {
                    $dataUserRole = [
                        'user' => $id,
                        'role' => $role,
                    ];
                    /** @var UserRoleService $userRoleService */
                    $userRoleService = $this->get('userRoleService');
                    $userRoleService->create($dataUserRole);
                }
            }
        }
    }

    /**
     * Update User Anr
     *
     * @param $id
     * @param $data
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