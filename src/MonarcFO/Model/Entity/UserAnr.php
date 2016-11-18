<?php

namespace MonarcFO\Model\Entity;

use MonarcCore\Model\Entity\AbstractEntity;
use Zend\InputFilter\InputFilterInterface;

use Doctrine\ORM\Mapping as ORM;

/**
 * User Anr
 *
 * @ORM\Table(name="users_anrs")
 * @ORM\Entity
 */
class UserAnr extends AbstractEntity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var \MonarcFO\Model\Entity\User
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\User", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="user_id", referencedColumnName="id", nullable=true, onDelete="CASCADE")
     * })
     */
    protected $user;

    /**
     * @var \MonarcFO\Model\Entity\Anr
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Anr", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true, onDelete="CASCADE")
     * })
     */
    protected $anr;

    /**
     * @var smallint
     *
     * @ORM\Column(name="rwd", type="smallint", nullable=true, options={"unsigned":true, "default":1})
     */
    protected $rwd = 1;

    /**
     * @var string
     *
     * @ORM\Column(name="creator", type="string", length=255, nullable=true)
     */
    protected $creator;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime", nullable=true)
     */
    protected $createdAt;

    /**
     * @var string
     *
     * @ORM\Column(name="updater", type="string", length=255, nullable=true)
     */
    protected $updater;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="updated_at", type="datetime", nullable=true)
     */
    protected $updatedAt;

    /**
     * @return \MonarcFO\Model\Entity\User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param \MonarcFO\Model\Entity\User $user
     * @return UserAnr
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return \MonarcFO\Model\Entity\Anr
     */
    public function getAnr()
    {
        return $this->anr;
    }

    /**
     * @param \MonarcFO\Model\Entity\Anr $anr
     * @return UserAnr
     */
    public function setAnr($anr)
    {
        $this->anr = $anr;
        return $this;
    }

    /**
     * @return smallint
     */
    public function getRwd()
    {
        return $this->rwd;
    }

    /**
     * @param smallint $rwd
     * @return UserAnr
     */
    public function setRwd($rwd)
    {
        $this->rwd = $rwd;
        return $this;
    }
}

