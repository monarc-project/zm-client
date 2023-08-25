<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Validator\FieldValidator;

use Laminas\Validator\AbstractValidator;
use Monarc\Core\Model\Entity\Model;
use Monarc\Core\Table\ModelTable;

class IsModelActiveValidator extends AbstractValidator
{
    protected const MODEL_IS_INACTIVE = 'MODEL_IS_INACTIVE';
    protected const MISSING_MODEL_ID = 'MISSING_MODEL_ID';

    protected $messageTemplates = [
        self::MISSING_MODEL_ID => 'Model ID parameter is missing.',
        self::MODEL_IS_INACTIVE => 'Model "%value%" is inactive.',
    ];

    public function isValid($value)
    {
        if (empty($value)) {
            $this->error(self::MISSING_MODEL_ID);

            return false;
        }

        /** @var ModelTable $modelTable */
        $modelTable = $this->getOption('modelTable');
        /** @var Model $model */
        $model = $modelTable->findById($value);
        if (!$model->isActive()) {
            $this->error(self::MISSING_MODEL_ID, $model->getLabel($this->getOption('languageIndex')));

            return false;
        }

        return true;
    }
}

