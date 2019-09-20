<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Validator\User;

use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Validator\UniqueEmail;
use Monarc\Core\Validator\LanguageValidator;
use Monarc\FrontOffice\Model\Entity\UserRole;
use Monarc\FrontOffice\Model\Table\UserTable;
use Monarc\FrontOffice\Validator\AbstractMonarcInputValidator;
use Zend\Filter\StringTrim;
use Zend\InputFilter\ArrayInput;
use Zend\InputFilter\InputFilter;
use Zend\Validator\Callback;
use Zend\Validator\EmailAddress;
use Zend\Validator\NotEmpty;
use Zend\Validator\StringLength;

class CreateUserInputValidator extends AbstractMonarcInputValidator
{
    /** @var array */
    private $availableLanguages;

    /** @var UserTable */
    private $userTable;

    /** @var ConnectedUserService */
    private $connectedUserService;

    public function __construct(
        InputFilter $inputFilter,
        UserTable $userTable,
        ConnectedUserService $connectedUserService,
        array $config
    ) {
        $this->availableLanguages = $config['languages'];
        $this->userTable = $userTable;
        $this->connectedUserService = $connectedUserService;

        parent::__construct($inputFilter);
    }

    protected function getRules(): array
    {
        return [
            [
                'name' => 'firstname',
                'required' => true,
                'filters' => [
                    [
                        'name' => StringTrim::class,
                    ],
                ],
                'validators' => [
                    [
                        'name' => StringLength::class,
                        'options' => [
                            'min' => 1,
                            'max' => 100,
                        ]
                    ],
                ],
            ],
            [
                'name' => 'lastname',
                'required' => true,
                'filters' => [
                    [
                        'name' => StringTrim::class,
                    ],
                ],
                'validators' => [
                    [
                        'name' => StringLength::class,
                        'options' => [
                            'min' => 1,
                            'max' => 100,
                        ]
                    ],
                ],
            ],
            [
                'name' => 'email',
                'required' => true,
                'filters' => [
                    [
                        'name' => StringTrim::class,
                    ],
                ],
                'validators' => [
                    [
                        'name' => EmailAddress::class,
                    ],
                    [
                        'name' => UniqueEmail::class,
                        'options' => [
                            'userTable' => $this->userTable,
                            'currentUserId' => $this->connectedUserService->getConnectedUser()->getId(),
                        ],
                        // TODO: The following code requires the Db classes refactoring.
//                        'name' => NoRecordExists::class,
//                        'options' => [
//                            'adapter' => $this->dbCli,
//                            'table' => $this->dbCli->getEntityManager()->getClassMetadata(User::class)->getTableName(),
//                            'field' => 'email',
//                        ],
//                        'messages' => [
//                            NoRecordExists::ERROR_RECORD_FOUND => 'This email is already used',
//                        ],
                    ],
                ],
            ],
            [
                'name' => 'role',
                'required' => true,
                'type' => ArrayInput::class,
                'validators' => [
                    [
                        'name' => NotEmpty::class,
                    ],
                    [
                        'name' => Callback::class,
                        'options' => [
                            'callback' => [$this, 'validateRoles'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'language',
                'allowEmpty' => true,
                'continueIfEmpty' => true,
                'required' => false,
                'filters' => [
                    ['name' => 'ToInt'],
                ],
                'validators' => [
                    [
                        'name' => LanguageValidator::class,
                        'options' => [
                            'availableLanguages' => $this->availableLanguages,
                        ]
                    ]
                ],
            ],
        ];
    }

    public function validateRoles($value): bool
    {
        return in_array($value, [UserRole::SUPER_ADMIN_FO, UserRole::USER_FO]);
    }
}
