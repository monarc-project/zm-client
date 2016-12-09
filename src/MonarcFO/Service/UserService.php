<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractService;
use MonarcFO\Model\Entity\UserRole;
use MonarcFO\Model\Table\UserAnrTable;
use MonarcFO\Model\Table\UserRoleTable;
use MonarcFO\Model\Table\UserTable;
use Zend\View\Model\JsonModel;

/**
 * User Service
 *
 * Class UserService
 * @package MonarcCore\Service
 */
class UserService extends AbstractService
{
    protected $userAnrTable;
    protected $userRoleTable;
    protected $userAnrService;
    protected $userRoleService;

    /**
     * Get Filtered Count
     *
     * @param int $page
     * @param int $limit
     * @param null $order
     * @param null $filter
     * @return bool|mixed
     */
    public function getFilteredCount($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null) {

        /** @var UserTable $table */
        $table = $this->get('table');

        return $table->countFiltered($page, $limit, $this->parseFrontendOrder($order),
            $this->parseFrontendFilter($filter, array('firstname', 'lastname', 'email')));
    }

    /**
     * Get List
     *
     * @param int $page
     * @param int $limit
     * @param null $order
     * @param null $filter
     * @return mixed
     */
    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null){

        /** @var UserTable $table */
        $table = $this->get('table');
        $users =  $table->fetchAllFiltered(
            array_keys($this->get('entity')->getJsonArray()),
            $page,
            $limit,
            $this->parseFrontendOrder($order),
            $this->parseFrontendFilter($filter, $this->filterColumns),
            $filterAnd
        );

        /** @var UserRoleTable $userRoleTable */
        $userRoleTable = $this->get('userRoleTable');
        $usersRoles = $userRoleTable->fetchAllObject();
        foreach($users as $key => $user) {
            foreach ($usersRoles as $userRole) {
                if ($user['id'] == $userRole->user->id) {
                    $users[$key]['roles'][] = $userRole->role;
                }
            }
        }

        /** @var UserAnrTable $userAnrTable */
        $userAnrTable = $this->get('userAnrTable');
        $usersAnrs = $userAnrTable->fetchAllObject();
        foreach($users as $key => $user) {
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
    public function getCompleteUser($id) {

        /** @var UserTable $table */
        $table = $this->get('table');
        $user = $table->get($id);

        /** @var UserRoleTable $userRoleTable */
        $userRoleTable = $this->get('userRoleTable');
        $usersRoles = $userRoleTable->getEntityByFields(['user' => $id]);
        foreach ($usersRoles as $userRole) {
            $user['role'][] = $userRole->role;
        }

        /** @var UserAnrTable $userAnrTable */
        $userAnrTable = $this->get('userAnrTable');
        $usersAnrs = $userAnrTable->fetchAllObject();
        foreach ($usersAnrs as $userAnr) {
            if ($id == $userAnr->user->id) {
                $anr = [
                    'id' => $userAnr->anr->id,
                    'label1' => $userAnr->anr->label1,
                    'label2' => $userAnr->anr->label2,
                    'label3' => $userAnr->anr->label3,
                    'label4' => $userAnr->anr->label4,
                    'rwd' => $userAnr->rwd,
                ];
                $user['anrs'][] = $anr;
            }
        }

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
        $user = $this->get('entity');
        $data['status'] = 1;

        if(empty($data['language'])){
            $data['language'] = $this->getLanguage();
        }

        $user->exchangeArray($data);

        /** @var UserTable $table */
        $table = $this->get('table');
        $id = $table->save($user);

        if (isset($data['role'])) {
            foreach($data['role'] as $role) {
                $dataUserRole = [
                    'user' => $id,
                    'role' => $role,
                ];
                /** @var UserRoleService $userRoleService */
                $userRoleService = $this->get('userRoleService');
                $userRoleService->create($dataUserRole);
            }
        }

        if (isset($data['anrs'])) {
            foreach($data['anrs'] as $anr) {
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

        return $id;
    }

    /**
     * Update
     *
     * @param $id
     * @param $data
     * @return mixed
     */
    public function update($id,$data){

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
    public function patch($id, $data){

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
    public function delete($id) {

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
    public function verifyAuthorizedAction($id, $data) {

        /** @var UserRoleTable $userRoleTable */
        $isAdmin = false;
        $userRoleTable = $this->get('userRoleTable');
        $userRoles = $userRoleTable->getEntityByFields(['user' => $id]);
        foreach($userRoles as $userRole) {
            if ($userRole->role == \MonarcFO\Model\Entity\UserRole::SUPER_ADMIN_FO) {
                $isAdmin = true;
            }
        }

        if ($isAdmin) {
            /** @var \MonarcFO\Model\Table\UserTable $userTable */
            $userTable = $this->get('table');
            $user = $userTable->getEntity($id);

            $nbActivateAdminUser = 0;
            $adminUsersRoles = $userRoleTable->getEntityByFields(['role' => \MonarcFO\Model\Entity\UserRole::SUPER_ADMIN_FO]);
            foreach($adminUsersRoles as $adminUsersRole) {
                $user = $userTable->getEntity($adminUsersRole->user->id);
                if (($user->status) && (is_null($user->dateEnd))) {
                    $nbActivateAdminUser++;
                }
            }

            if (
                (($user->status) && (isset($data['status'])) && (!$data['status'])) //change status 1 -> 0
                ||
                ((is_null($user->dateEnd)) && (isset($data['dateEnd']))) //change dateEnd null -> date
                ||
                ((isset($data['superadminfo'])) && (!$data['superadminfo'])) //delete superadminfo role
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
    public function updateUserRole($id, $data) {


        if (isset($data['role'])) {

            /** @var UserRoleTable $userRoleTable */
            $userRoleTable = $this->get('userRoleTable');
            $userRoles = $userRoleTable->getEntityByFields(['user' => $id]);
            $userRolesArray = [];
            foreach($userRoles as $userRole) {
                if (!in_array($userRole->role, $data['role'])) {
                    //delete role
                    $userRoleTable->delete($userRole->id);
                } else {
                    $userRolesArray[] = $userRole->role;
                }
            }

            foreach($data['role'] as $role) {
                if (!in_array($role, $userRolesArray)) {
                    //add role
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
    public function updateUserAnr($id, $data) {

        if (isset($data['anrs'])) {

            /** @var UserAnrTable $userAnrTable */
            $userAnrTable = $this->get('userAnrTable');
            $userAnrs = $userAnrTable->getEntityByFields(['user' => $id]);
            $currentUserAnrs = [];
            foreach($userAnrs as $userAnr) {
                $currentUserAnrs[$userAnr->anr->id] = [
                    'id' => $userAnr->id,
                    'rwd' => $userAnr->rwd
                ];
            }

            $futureUserAnrs = [];
            foreach($data['anrs'] as $userAnr) {
                $futureUserAnrs[$userAnr['id']] = intval($userAnr['rwd']);
            }

            /** @var UserAnrService $userAnrService */
            $userAnrService = $this->get('userAnrService');

            //create or update
            foreach($futureUserAnrs as $key => $futureUserAnr) {
                if (!isset($currentUserAnrs[$key])) {
                    $userAnrService->create([
                        'user' => $id,
                        'anr' => $key,
                        'rwd' => $futureUserAnr,
                    ]);
                } else  if ($currentUserAnrs[$key]['rwd'] != $futureUserAnrs[$key]) {
                    $userAnrService->patch($currentUserAnrs[$key]['id'], ['rwd' => $futureUserAnrs[$key]]);
                }
            }

            //delete
            foreach($currentUserAnrs as $key => $currentUserAnr) {
                if (!isset($futureUserAnrs[$key])) {
                    $userAnrService->delete($currentUserAnr['id']);
                }
            }
        }
    }

}
