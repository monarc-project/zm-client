<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Validator\InputValidator\User;

use Laminas\Filter\StringTrim;
use Laminas\InputFilter\ArrayInput;
use Laminas\InputFilter\InputFilter;
use Laminas\Validator\Callback;
use Laminas\Validator\EmailAddress;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\StringLength;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Validator\LanguageValidator;
use Monarc\Core\Validator\UniqueEmail;
use Monarc\FrontOffice\Model\Entity\UserRole;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\UserTable;
use Monarc\FrontOffice\Validator\FieldValidator\AnrExistenceValidator;
use Monarc\FrontOffice\Validator\InputValidator\AbstractMonarcInputValidator;

class CreateUserInputValidator extends AbstractMonarcInputValidator
{
    /** @var array */
    private $availableLanguages;

    /** @var UserTable */
    private $userTable;

    /** @var ConnectedUserService */
    private $connectedUserService;

    /** @var AnrTable */
    private $anrTable;

    public function __construct(
        InputFilter $inputFilter,
        UserTable $userTable,
        ConnectedUserService $connectedUserService,
        AnrTable $anrTable,
        array $config
    ) {
        $this->availableLanguages = $config['languages'];
        $this->userTable = $userTable;
        $this->connectedUserService = $connectedUserService;
        $this->anrTable = $anrTable;

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
                'name' => 'password',
                'required' => false,
                'filters' => [
                    [
                        'name' => StringTrim::class,
                    ],
                ],
                'validators' => [
                    [
                        'name' => StringLength::class,
                        'options' => [
                            'min' => 9,
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
                        // TODO: The following code requires the Db classes refactoring, also an issue with Laminas.
                        /*
                        'name' => NoRecordExists::class,
                        'options' => [
                            'adapter' => $this->userTable->getDb(),
                            'table' => $this->userTable->getDb()->getEntityManager()->getClassMetadata(User::class)->getTableName(),
                            'field' => 'email',
                        ],
                        'messages' => [
                            NoRecordExists::ERROR_RECORD_FOUND => 'This email is already used',
                        ],
                        */
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
        return in_array($value, UserRole::getAvailableRoles());
    }
}
