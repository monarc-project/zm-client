<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\UserService;
use Monarc\Core\Model\Table\UserTable;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

/**
 * Api User RecoveryCodes Controller
 *
 * Class ApiUserRecoveryCodesController
 * @package Monarc\FrontOffice\Controller
 */
class ApiUserRecoveryCodesController extends AbstractRestfulController
{
    /** @var ConnectedUserService */
    private $connectedUserService;

    /** @var UserService */
    private $userService;

    /** @var UserTable */
    private $userTable;


    public function __construct(
        UserService $userService,
        ConnectedUserService $connectedUserService,
        UserTable $userTable
    ) {
        $this->userService = $userService;
        $this->connectedUserService = $connectedUserService;
        $this->userTable = $userTable;
    }

    /**
     * Generates and returns 5 new recovery codes (20 chars for each codes).
     */
    public function create($data)
    {
        $connectedUser = $this->connectedUserService->getConnectedUser();
        $status = 'ok';

        if (! $connectedUser->isTwoFactorAuthEnabled()) {
            $status = 'Two factor authentication not enbabled';
        } else {
            $recoveryCodes = array();
            for ($i = 0; $i < 5; $i++) {
                array_push($recoveryCodes, bin2hex(openssl_random_pseudo_bytes(10)));
            }

            $connectedUser->createRecoveryCodes($recoveryCodes);
            $this->userTable->saveEntity($connectedUser);
        }

        return new JsonModel([
            'status' => $status,
            'recoveryCodes' => $recoveryCodes,
        ]);
    }
}
