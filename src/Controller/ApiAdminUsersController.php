<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2021 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\PasswordService;
use Monarc\FrontOffice\Validator\InputValidator\User\CreateUserInputValidator;
use Monarc\FrontOffice\Service\UserService;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

class ApiAdminUsersController extends AbstractRestfulController
{
    private const DEFAULT_LIMIT = 25;

    /** @var CreateUserInputValidator */
    private $createUserInputValidator;

    /** @var UserService */
    private $userService;

    /** @var PasswordService */
    private $passwordService;

    public function __construct(
        CreateUserInputValidator $createUserInputValidator,
        UserService $userService,
        PasswordService $passwordService
    ) {
        $this->createUserInputValidator = $createUserInputValidator;
        $this->userService = $userService;
        $this->passwordService = $passwordService;
    }

    public function getList()
    {
        $searchString = $this->params()->fromQuery('filter', '');
        $status = $this->params()->fromQuery('status', 1);
        $filter = $status === 'all'
            ? null
            : ['status' => (int)$status];
        $page = $this->params()->fromQuery('page', 1);
        $limit = $this->params()->fromQuery('limit', static::DEFAULT_LIMIT);
        $order = $this->params()->fromQuery('order', '');

        $users = $this->userService->getUsersList($searchString, $filter, $order);

        return new JsonModel(array(
            'count' => \count($users),
            'users' => \array_slice($users, $page - 1, $limit),
        ));
    }

    public function create($data)
    {
        if (!$this->createUserInputValidator->isValid($data)) {
            throw new Exception(
                'Data validation errors: [ ' . json_encode($this->createUserInputValidator->getErrorMessages()),
                400
            );
            /* TODO: make it on the application level to throw a particular exception interface and process in Module.
            return new JsonModel([
                'httpStatus' => 400,
                'errors' => $this->createUserInputValidator->getErrorMessages(),
            ]);*/
        }

        $this->userService->create($this->createUserInputValidator->getValidData());

        return new JsonModel(['status' => 'ok']);
    }

    public function update($id, $data)
    {
        $this->userService->update($id, $data);

        return new JsonModel(['status' => 'ok']);
    }

    public function get($id)
    {
        return new JsonModel($this->userService->getCompleteUser($id));
    }

    public function resetPasswordAction()
    {
        $id = (int)$this->params()->fromRoute('id');

        $this->passwordService->resetPassword($id);

        return new JsonModel($this->userService->getCompleteUser($id));
    }

    public function delete($id)
    {
        $this->userService->delete($id);

        $this->getResponse()->setStatusCode(204);

        return new JsonModel();
    }

    public function patch($id, $data)
    {
        $this->userService->patch($id, $data);

        return new JsonModel(['status' => 'ok']);
    }
}
