<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2021 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Exception\UserNotLoggedInException;
use Monarc\Core\Service\UserRoleService as CoreUserRoleService;

class UserRoleService extends CoreUserRoleService
{
    public function getUserRolesByToken(string $token): array
    {
        $userToken = $this->userTokenTable->findByToken($token);
        if ($userToken === null) {
            throw new UserNotLoggedInException();
        }

        $userAnrs = [];
        foreach ($userToken->getUser()->getUserAnrs() as $userAnr) {
            $userAnrs[] = [
                'anr' => $userAnr->anr->id,
                'rwd' => $userAnr->rwd
            ];
        }

        return [
            'roles' => parent::getUserRolesByToken($token),
            'anrs' => $userAnrs,
        ];
    }
}
