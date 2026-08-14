<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Validator\FieldValidator;

use Doctrine\ORM\EntityNotFoundException;
use Laminas\Validator\AbstractValidator;
use Monarc\FrontOffice\Table\UserTable;

class LinkedUserExistenceValidator extends AbstractValidator
{
    public const LINKED_USER_DOES_NOT_EXIST = 'LINKED_USER_DOES_NOT_EXIST';

    protected $messageTemplates = [
        self::LINKED_USER_DOES_NOT_EXIST => 'Linked user with the ID (%value%) does not exist.',
    ];

    public function isValid($value)
    {
        if ($value === null || $value === '' || (int)$value === 0) {
            return true;
        }

        /** @var UserTable $userTable */
        $userTable = $this->getOption('userTable');

        try {
            $userTable->findById((int)$value);
        } catch (EntityNotFoundException) {
            $this->error(self::LINKED_USER_DOES_NOT_EXIST, $value);

            return false;
        }

        return true;
    }
}
