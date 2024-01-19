<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\FrontOffice\Model\Entity\User;
use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\Providers\Qr\EndroidQrCodeProvider;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\ConfigService;
use Monarc\Core\Table\UserTable;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

class ApiUserTwoFAController extends AbstractRestfulController
{
    private ConnectedUserService $connectedUserService;

    private ConfigService $configService;

    private UserTable $userTable;

    private TwoFactorAuth $tfa;

    public function __construct(
        ConfigService $configService,
        ConnectedUserService $connectedUserService,
        UserTable $userTable
    ) {
        $this->configService = $configService;
        $this->connectedUserService = $connectedUserService;
        $this->userTable = $userTable;
        $qr = new EndroidQrCodeProvider();
        $this->tfa = new TwoFactorAuth('MONARC', 6, 30, 'sha1', $qr);
    }

    /**
     * Generates a new secret key for the connected user.
     * Returns the secret key and the corresponding QRCOde.
     */
    public function get($id)
    {
        $connectedUser = $this->connectedUserService->getConnectedUser();
        if ($connectedUser === null) {
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

        return new JsonModel([
            'id' => $connectedUser->getId(),
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
        /** @var User $connectedUser */
        $connectedUser = $this->connectedUserService->getConnectedUser();

        // TODO: move to the service...
        $res = $this->tfa->verifyCode($data['secretKey'], $data['verificationCode']);

        if ($res) {
            $connectedUser->setSecretKey($data['secretKey']);
            $connectedUser->setTwoFactorAuthEnabled(true);
            $this->userTable->save($connectedUser);
        }

        return new JsonModel([
            'status' => $res,
        ]);
    }

    /**
     * Disable the Two Factor Authentication for the connected user.
     * Also delete the secret key.
     */
    public function delete($id)
    {
        $connectedUser = $this->connectedUserService->getConnectedUser();
        if ($connectedUser === null) {
            throw new Exception('You are not authorized to do this action', 412);
        }

        // TODO: move to the service.
        $connectedUser->setTwoFactorAuthEnabled(false);
        $connectedUser->setSecretKey('');
        $connectedUser->setRecoveryCodes([]);
        $this->userTable->save($connectedUser);

        $this->getResponse()->setStatusCode(204);

        return new JsonModel();
    }
}
