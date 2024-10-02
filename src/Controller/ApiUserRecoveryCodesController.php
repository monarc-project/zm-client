<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Table\UserTable;

class ApiUserRecoveryCodesController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    private User $connectedUser;

    public function __construct(private UserTable $userTable, ConnectedUserService $connectedUserService)
    {
        /** @var User $user */
        $user = $connectedUserService->getConnectedUser();
        $this->connectedUser = $user;
    }

    /**
     * Generates and returns 5 new recovery codes (20 chars for each code).
     */
    public function create($data)
    {
        $status = 'ok';

        if ($this->connectedUser->isTwoFactorAuthEnabled()) {
            $recoveryCodes = [];
            for ($i = 0; $i < 5; $i++) {
                $recoveryCodes[] = bin2hex(openssl_random_pseudo_bytes(10));
            }

            $this->connectedUser->createRecoveryCodes($recoveryCodes);
            $this->userTable->save($this->connectedUser);
        } else {
            $status = 'Two factor authentication not enbabled';
        }

        return $this->getPreparedJsonResponse([
            'status' => $status,
            'recoveryCodes' => $recoveryCodes,
        ]);
    }
}
