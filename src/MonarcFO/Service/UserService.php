<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractService;
use MonarcFO\Model\Table\UserAnrTable;
use MonarcFO\Model\Table\UserRoleTable;

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

        return $this->get('table')->countFiltered($page, $limit, $this->parseFrontendOrder($order),
            $this->parseFrontendFilter($filter, array('firstname', 'lastname', 'email')));
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

        $id = $this->get('table')->save($user);

        if (isset($data['superadminfo'])) {
            $dataUserRole = [
                'user' => $id,
                'role' => \MonarcFO\Model\Entity\UserRole::SUPER_ADMIN_FO,
            ];
            /** @var UserRoleService $userRoleService */
            $userRoleService = $this->get('userRoleService');
            $userRoleService->create($dataUserRole);

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

        if (isset($data['superadminfo'])) {
            /** @var UserRoleTable $userRoleTable */
            $isAdmin = false;
            $userRoleTable = $this->get('userRoleTable');
            $userRoles = $userRoleTable->getEntityByFields(['user' => $id]);
            foreach($userRoles as $userRole) {
                if ($userRole->role == \MonarcFO\Model\Entity\UserRole::SUPER_ADMIN_FO) {
                    $isAdmin = true;
                }
            }

            if (($data['superadminfo']) && (!$isAdmin)) {
                //add role
                $dataUserRole = [
                    'user' => $id,
                    'role' => \MonarcFO\Model\Entity\UserRole::SUPER_ADMIN_FO,
                ];
                /** @var UserRoleService $userRoleService */
                $userRoleService = $this->get('userRoleService');
                $userRoleService->create($dataUserRole);
            } else if ((!$data['superadminfo']) && ($isAdmin)){
                //delete role
                foreach($userRoles as $userRole) {
                    if ($userRole->role == \MonarcFO\Model\Entity\UserRole::SUPER_ADMIN_FO) {
                        $userRoleTable->delete($userRole->id);
                    }
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
