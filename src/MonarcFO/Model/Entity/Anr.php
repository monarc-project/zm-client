<?php

namespace MonarcFO\Model\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Anr
 *
 * @ORM\Table(name="anrs")
 * @ORM\Entity
 */
class Anr extends \MonarcCore\Model\Entity\AnrSuperClass
{
    /**
     * @var \MonarcFO\Model\Entity\User
     *
     * @ORM\ManyToMany(targetEntity="MonarcFO\Model\Entity\User", mappedBy="users", cascade={"persist"})
     */
    protected $users;

    /**
     * @var integer
     *
     * @ORM\Column(name="language", type="integer", options={"unsigned":true, "default":1})
     */
    protected $language = 1;
}
