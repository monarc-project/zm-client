<?php

namespace MonarcFO\Model\Entity;

use MonarcCore\Model\Entity\AbstractEntity;
use MonarcCore\Model\Entity\UserTokenSuperClass;
use Zend\InputFilter\InputFilterInterface;

use Doctrine\ORM\Mapping as ORM;

/**
 * User Token
 *
 * @ORM\Table(name="user_tokens")
 * @ORM\Entity
 */
class UserToken extends UserTokenSuperClass
{

}

