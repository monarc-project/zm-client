<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

use MonarcCore\Model\Table\UserRoleTable;
use MonarcFO\Model\Table\UserAnrTable;

/**
 * User Role Service
 *
 * Class UserRoleService
 * @package MonarcFO\Service
 */
class UserRoleService extends \MonarcCore\Service\UserRoleService
{
    protected $userAnrCliTable;
    protected $userTable;
    protected $dependencies = ['user'];

    /**
     * Get By User Token
     *
     * @param $token
     * @return array
     * @throws \Exception
     */
    public function getByUserToken($token)
    {
        //retrieve user access
        $userId = $this->getUserIdByToken($token);
        $anrs = [];
        /** @var UserAnrTable $userAnrCliTable */
        $userAnrCliTable = $this->get('userAnrCliTable');
        $userAnrs = $userAnrCliTable->getEntityByFields(['user' => $userId]);
        foreach ($userAnrs as $userAnr) {
            $anrs[] = [
                'anr' => $userAnr->anr->id,
                'rwd' => $userAnr->rwd
            ];
        }

        return [
            'roles' => $this->getByUserId($userId),
            'anrs' => $anrs
        ];
    }
}