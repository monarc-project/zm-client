<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\PasswordService;
use Monarc\FrontOffice\Validator\InputValidator\User\CreateUserInputValidator;
use Monarc\FrontOffice\Service\UserService;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

/**
 * Api Admin Users Controller
 *
 * Class ApiAdminUsersController
 * @package Monarc\FrontOffice\Controller
 */
class ApiAdminUsersController extends AbstractRestfulController
{
    /** @var CreateUserInputValidator */
    private $createUserInputValidator;

    /** @var UserService */
    private $userService;

    /** @var PasswordService */
    private $passwordService;

    public function __construct(CreateUserInputValidator $createUserInputValidator, UserService $userService, PasswordService $passwordService)
    {
        $this->createUserInputValidator = $createUserInputValidator;
        $this->userService = $userService;
        $this->passwordService = $passwordService;
    }

    /**
     * @inheritdoc
     */
    public function getList()
    {
        $page = $this->params()->fromQuery('page');
        $limit = $this->params()->fromQuery('limit');
        $order = $this->params()->fromQuery('order');
        $filter = $this->params()->fromQuery('filter');
        $status = $this->params()->fromQuery('status', 1);
        $filterAnd = $status === 'all'
            ? null
            : ['status' => (int)$status];

        return new JsonModel(array(
            'count' => $this->userService->getFilteredCount($filter, $filterAnd),
            'users' => $this->userService->getList($page, $limit, $order, $filter, $filterAnd)
        ));
    }

    /**
     * @inheritdoc
     */
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

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        // TODO: The request data filtration is responsibility of the sanitization layer + validation should be applied.
        // Security: Don't allow changing role, password, status and history fields. To clean later.
        if (isset($data['status'])) {
            unset($data['status']);
        }
        if (isset($data['id'])) {
            unset($data['id']);
        }
        if (isset($data['salt'])) {
            unset($data['salt']);
        }
        if (isset($data['updatedAt'])) {
            unset($data['updatedAt']);
        }
        if (isset($data['updater'])) {
            unset($data['updater']);
        }
        if (isset($data['createdAt'])) {
            unset($data['createdAt']);
        }
        if (isset($data['creator'])) {
            unset($data['creator']);
        }
        if (isset($data['dateStart'])) {
            unset($data['dateStart']);
        }
        if (isset($data['dateEnd'])) {
            unset($data['dateEnd']);
        }

        $this->userService->update($id, $data);

        return new JsonModel(['status' => 'ok']);
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        return new JsonModel($this->userService->getCompleteUser($id));
    }

    /**
     * @inheritdoc
     */
    public function resetPasswordAction()
    {
        $id = $this->params()->fromRoute('id');

        $this->passwordService->resetPassword($id);

        return new JsonModel($this->userService->getCompleteUser($id));
    }

    /**
     * @inheritdoc
     */
    public function delete($id)
    {
        $this->userService->delete($id);

        $this->getResponse()->setStatusCode(204);

        return new JsonModel();
    }

    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        $this->userService->patch($id, $data);

        return new JsonModel(array('status' => 'ok'));
    }
}
