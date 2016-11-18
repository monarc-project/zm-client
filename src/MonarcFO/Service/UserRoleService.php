<?php
namespace MonarcFO\Service;

use MonarcCore\Model\Table\UserRoleTable;
use MonarcCore\Service\AbstractService;
use MonarcFO\Model\Table\UserTable;
use Zend\Http\Header\GenericHeader;

class UserRoleService extends AbstractService
{
    protected $userCliTable;
    protected $userRoleTable;
    protected $userTokenTable;
    protected $userRoleEntity;

    /**
     * Get List
     *
     * @param int $page
     * @param int $limit
     * @param null $order
     * @param null $filter
     * @param array $options
     * @return array
     */
    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $options = [])
    {
        /** @var UserRoleTable $userRoleTable */
        $userRoleTable = $this->get('userRoleTable');

        return $userRoleTable->getRepository()->createQueryBuilder('t')
            ->select(array('t.id','t.role'))
            ->where('t.user = :id')
            ->setParameter(':id',$filter)
            ->getQuery()->getResult();
    }

    public function getEntity($id)
    {
        return $this->get('userRoleTable')->get($id);
    }

    public function getByUserId($userId)
    {
        /** @var UserRoleTable $userRoleTable */
        $userRoleTable = $this->get('userRoleTable');

        return $userRoleTable->getRepository()->createQueryBuilder('t')
            ->select(array('t.id','t.role'))
            ->where('t.user = :id')
            ->setParameter(':id',$userId)
            ->getQuery()->getResult();
    }

    public function getByUserToken($token) {

        if ($token instanceof GenericHeader) {
            $token = $token->getFieldValue();
        }

        $userTokenTable = $this->get('userTokenTable');

        $userToken = $userTokenTable->getRepository()->createQueryBuilder('t')
            ->select(array('t.id', 'IDENTITY(t.user) as userId', 't.token', 't.dateEnd'))
            ->where('t.token = :token')
            ->setParameter(':token', $token)
            ->getQuery()
            ->getResult();

        if (count($userToken)) {
            $userId = $userToken[0]['userId'];

            /** @var UserTable $userCliTable */
            $userCliTable = $this->get('userCliTable');
            $user = $userCliTable->getEntity($userId);

            $anrs = [];
            foreach($user->anrs as $anr) {
                $anrs[] = $anr->getJsonArray();
            }

            return [
                'roles' => $this->getByUserId($userId),
                'anrs' => $anrs
            ];
        } else {
            throw new \Exception('No user');
        }
    }

}