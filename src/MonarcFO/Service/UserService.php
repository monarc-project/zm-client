<?php
namespace MonarcFO\Service;

use MonarcCore\Model\Entity\User;
use MonarcCore\Model\Entity\UserRole;
use MonarcCore\Model\Table\UserTable;
use MonarcCore\Service\AbstractService;
use MonarcCore\Validator\PasswordStrength;
use MonarcFO\Model\Table\UserRoleTable;

/**
 * User Service
 *
 * Class UserService
 * @package MonarcCore\Service
 */
class UserService extends AbstractService
{
    protected $userRoleTable;

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

        $this->manageRoles($user, $data);

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

        $this->verifyUserStatus($id, $data);

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

        $this->verifyUserStatus($id, $data);

        if (isset($data['dateEnd'])) {
            $data['dateEnd'] = new \DateTime($data['dateEnd']);
        }

        if (isset($data['dateStart'])) {
            $data['dateStart'] = new \DateTime($data['dateStart']);
        }

        return parent::patch($id, $data);
    }

    /**
     * Verify User Status
     *
     * @param $id
     * @param $data
     * @throws \Exception
     */
    public function verifyUserStatus($id, $data) {

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

            if (
                (($user->status) && (isset($data['status'])) && (!$data['status'])) //change status 1 -> 0
                ||
                ((is_null($user->dateEnd)) && (isset($data['dateEnd']))) //change dateEnd null -> date
            ) {
                //verify if is not the last superadminfo and verify date_end
                $adminUsersRoles = $userRoleTable->getEntityByFields(['role' => \MonarcFO\Model\Entity\UserRole::SUPER_ADMIN_FO]);
                $nbActivateUser = 0;
                foreach($adminUsersRoles as $adminUsersRole) {
                    $user = $userTable->getEntity($adminUsersRole->user->id);
                    if (($user->status) && (is_null($user->dateEnd))) {
                        $nbActivateUser++;
                    }
                }
                if (count($nbActivateUser) <= 1) {
                    throw new \Exception('You can not desactivate the last admin', 412);
                }
            }
        }
    }

}
