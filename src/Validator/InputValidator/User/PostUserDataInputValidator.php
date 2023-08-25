<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Validator\InputValidator\User;

use Laminas\InputFilter\ArrayInput;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Validator\InputValidator\InputValidationTranslator;
use Monarc\Core\Validator\InputValidator\User\PostUserDataInputValidator as CorePostUserDataInputValidatorAlias;
use Monarc\FrontOffice\Model\Entity\UserRole;
use Monarc\FrontOffice\Table\AnrTable;
use Monarc\FrontOffice\Table\UserTable;
use Monarc\FrontOffice\Validator\FieldValidator\AnrExistenceValidator;

class PostUserDataInputValidator extends CorePostUserDataInputValidatorAlias
{
    private AnrTable $anrTable;

    public function __construct(
        array $config,
        InputValidationTranslator $translator,
        UserTable $userTable,
        ConnectedUserService $connectedUserService,
        AnrTable $anrTable
    ) {
        parent::__construct($config, $translator, $userTable, $connectedUserService);

        $this->anrTable = $anrTable;
        $this->userRoles = UserRole::getAvailableRoles();
    }

    protected function getRules(): array
    {
        return array_merge(parent::getRules(), [
            [
                'name' => 'anrs',
                'required' => false,
                'type' => ArrayInput::class,
                'validators' => [
                    [
                        'name' => AnrExistenceValidator::class,
                        'options' => [
                            'anrTable' => $this->anrTable,
                        ],
                    ]
                ],
            ],
        ]);
    }
}
