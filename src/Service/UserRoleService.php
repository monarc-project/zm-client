<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Exception\UserNotLoggedInException;
use Monarc\Core\Service\UserRoleService as CoreUserRoleService;
use Monarc\FrontOffice\Entity\UserAnr;
use Monarc\FrontOffice\Table\UserTable;
use Monarc\FrontOffice\Table\UserTokenTable;

class UserRoleService extends CoreUserRoleService
{
    public function __construct(
        UserTable $userTable,
        UserTokenTable $userTokenTable
    ) {
        parent::__construct($userTable, $userTokenTable);
    }

    public function getUserRolesByToken(string $token): array
    {
        $userToken = $this->userTokenTable->findByToken($token);
        if ($userToken === null) {
            throw new UserNotLoggedInException();
        }

        $userAnrs = [];
        /** @var UserAnr $userAnr */
        foreach ($userToken->getUser()->getUserAnrs() as $userAnr) {
            $userAnrs[] = [
                'anr' => $userAnr->getAnr()->getId(),
                'rwd' => $userAnr->getRwd(),
            ];
        }

        return [
            'roles' => parent::getUserRolesByToken($token),
            'anrs' => $userAnrs,
        ];
    }
}
