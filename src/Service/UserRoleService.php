<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Model\Table\UserRoleTable;
use Monarc\Core\Model\Table\UserTokenTable;
use Monarc\Core\Service\UserRoleService as CoreUserRoleService;
use Monarc\FrontOffice\Model\Table\UserAnrTable;

/**
 * This class is the service that handles the users roles. This is a simple CRUD service that inherits from its
 * Monarc\Core parent.
 * @see \Monarc\Core\Service\UserRoleService
 * @package Monarc\FrontOffice\Service
 */
class UserRoleService extends CoreUserRoleService
{
    /** @var UserAnrTable */
    private $userAnrTable;

    public function __construct(
        UserRoleTable $userRoleTable,
        UserTokenTable $userTokenTable,
        UserAnrTable $userAnrTable
    ) {
        parent::__construct($userRoleTable, $userTokenTable);

        $this->userAnrTable = $userAnrTable;
    }

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
        $userAnrs = $this->userAnrTable->getEntityByFields(['user' => $userId]);
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
