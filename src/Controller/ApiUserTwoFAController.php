<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Entity\User;
use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\Providers\Qr\EndroidQrCodeProvider;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\ConfigService;
use Monarc\Core\Table\UserTable;

class ApiUserTwoFAController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    private User $connectedUser;

    private TwoFactorAuth $tfa;

    public function __construct(
        private ConfigService $configService,
        private UserTable $userTable,
        ConnectedUserService $connectedUserService
    ) {
        /** @var User $connectedUser */
        $connectedUser = $connectedUserService->getConnectedUser();
        $this->connectedUser = $connectedUser;
        $qr = new EndroidQrCodeProvider();
        $this->tfa = new TwoFactorAuth('MONARC', 6, 30, 'sha1', $qr);
    }

    /**
     * Generates a new secret key for the connected user.
     * Returns the secret key and the corresponding QRCOde.
     */
    public function get($id)
    {
        if ($this->connectedUser === null) {
            throw new Exception('You are not authorized to do this action', 412);
        }

        // TODO: move to the service...
        // Create a new secret and generate a QRCode
        $label = 'MONARC';
        if ($this->configService->getInstanceName()) {
            $label .= ' ('. $this->configService->getInstanceName() .')';
        }
        $secret = $this->tfa->createSecret();
        $qrcode = $this->tfa->getQRCodeImageAsDataUri($label, $secret);

        return $this->getPreparedJsonResponse([
            'id' => $this->connectedUser->getId(),
            'secret' => $secret,
            'qrcode' => $qrcode,
        ]);
    }

    /**
     * Confirms the newly generated key with a token given by the user.
     * This is just good practice.
     *
     * @param array $data
     */
    public function create($data)
    {
        // TODO: move to the service...
        $res = $this->tfa->verifyCode($data['secretKey'], $data['verificationCode']);

        if ($res) {
            $this->connectedUser->setSecretKey($data['secretKey']);
            $this->connectedUser->setTwoFactorAuthEnabled(true);
            $this->userTable->save($this->connectedUser);
        }

        return $this->getPreparedJsonResponse([
            'status' => $res,
        ]);
    }

    /**
     * Disable the Two Factor Authentication for the connected user.
     * Also delete the secret key.
     */
    public function delete($id)
    {
        if ($this->connectedUser === null) {
            throw new Exception('You are not authorized to do this action', 412);
        }

        // TODO: move to the service.
        $this->connectedUser->setTwoFactorAuthEnabled(false);
        $this->connectedUser->setSecretKey('');
        $this->connectedUser->setRecoveryCodes([]);
        $this->userTable->save($this->connectedUser);

        $this->getResponse()->setStatusCode(204);

        return $this->getPreparedJsonResponse();
    }
}
