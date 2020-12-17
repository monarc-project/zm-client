<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Validator\FieldValidator;

use Doctrine\ORM\EntityNotFoundException;
use Laminas\Validator\AbstractValidator;
use Monarc\FrontOffice\Model\Table\AnrTable;

class AnrExistenceValidator extends AbstractValidator
{
    public const ANR_DOES_NOT_EXIST = 'ANR_DOES_NOT_EXIST';

    protected $messageTemplates = [
        self::ANR_DOES_NOT_EXIST => 'Anr with the ID (%value%) does not exist.',
    ];

    public function isValid($value)
    {
        if (empty($value) && !isset($value['id'])) {
            return true;
        }

        /** @var AnrTable $anrTable */
        $anrTable = $this->getOptions()['anrTable'];
        $anrId = (int)($value['id'] ?? $value);
        try {
            $anrTable->findById($anrId);
        } catch (EntityNotFoundException $e) {
            $this->error(self::ANR_DOES_NOT_EXIST, $value);

            return false;
        }

        return true;
    }
}
