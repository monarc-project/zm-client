<?php

namespace MonarcFO\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use MonarcCore\Model\Entity\PasswordTokenSuperClass;
use Zend\InputFilter\InputFilter;

/**
 * Password Token
 *
 * @ORM\Table(name="passwords_tokens")
 * @ORM\Entity
 */
class PasswordToken extends PasswordTokenSuperClass
{

}
