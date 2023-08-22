<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\PasswordService;

class ApiAdminPasswordsController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    private PasswordService $passwordService;

    public function __construct(PasswordService $passwordService)
    {
        $this->passwordService = $passwordService;
    }

    /**
     * @param array $data
     */
    public function create($data)
    {
        /* Password forgotten. */
        if (!empty($data['email']) && empty($data['password'])) {
            try {
                $this->passwordService->passwordForgotten($data['email']);
            } catch (\Exception $e) {
                // Ignore the \Exception to avoid the data leak.
            }
        }

        /* Verify token. */
        if (!empty($data['token']) && empty($data['password'])) {
            if ($this->passwordService->verifyToken($data['token'])) {
                return $this->getSuccessfulJsonResponse();
            }

            throw new Exception('The token is not valid or there is a Browser cache issue.', 422);
        }

        /* Change password not logged. */
        if (!empty($data['token']) && !empty($data['password']) && !empty($data['confirm'])) {
            if ($data['password'] === $data['confirm']) {
                $this->passwordService->newPasswordByToken($data['token'], $data['password']);
            } else {
                throw new Exception('Password must be the same', 422);
            }
        }

        return $this->getSuccessfulJsonResponse();
    }
}
