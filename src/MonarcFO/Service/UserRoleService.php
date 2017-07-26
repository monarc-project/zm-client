<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

use MonarcCore\Model\Table\UserRoleTable;
use MonarcFO\Model\Table\UserAnrTable;

/**
 * This class is the service that handles the users roles. This is a simple CRUD service that inherits from its
 * MonarcCore parent.
 * @see \MonarcCore\Service\UserRoleService
 * @package MonarcFO\Service
 */
class UserRoleService extends \MonarcCore\Service\UserRoleService
{
    protected $userAnrCliTable;
    protected $userTable;
    protected $dependencies = ['user'];

    /**
     * Retrieve user's roles from its authentication token
     * @param string $token User authentication token
     * @return array An array of roles and ANRs accesses
     * @throws \Exception If the token is not found
     */
    public function getByUserToken($token)
    {
        // Retrieve user access
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