<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Validator;

use Zend\InputFilter\InputFilter;

abstract class AbstractMonarcInputValidator
{
    /** @var InputFilter */
    private $inputFilter;

    public function __construct(InputFilter $inputFilter)
    {
        $this->inputFilter = $inputFilter;

        $this->initRules();
    }

    abstract protected function getRules(): array;

    private function initRules(): void
    {
        foreach ($this->getRules() as $rule) {
            $this->inputFilter->add($rule);
        }
    }

    public function isValid(array $data): bool
    {
        $this->inputFilter->setData($data);

        return $this->inputFilter->isValid();
    }

    public function getErrorMessages(): array
    {
        return $this->inputFilter->getMessages();
    }

    public function getValidData(): array
    {
        return $this->inputFilter->getValues();
    }
}
