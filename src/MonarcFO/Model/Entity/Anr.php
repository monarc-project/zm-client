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

    /**
     * @var smallint
     *
     * @ORM\Column(name="model_id", type="integer", options={"unsigned":true, "default":0})
     */
    protected $model = '0';

    /**
     * @return User
     */
    public function getUsers()
    {
        return $this->users;
    }

    /**
     * @param User $users
     * @return Anr
     */
    public function setUsers($users)
    {
        $this->users = $users;
        return $this;
    }

    /**
     * @return int
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * @param int $language
     * @return Anr
     */
    public function setLanguage($language)
    {
        $this->language = $language;
        return $this;
    }


}
