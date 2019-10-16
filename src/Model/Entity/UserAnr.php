<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use DateTime;
use Monarc\Core\Model\Entity\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\Traits;

/**
 * User Anr
 *
 * @ORM\Table(name="users_anrs")
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class UserAnr extends AbstractEntity
{
    use Traits\CreateEntityTrait;
    use Traits\UpdateEntityTrait;

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var \Monarc\FrontOffice\Model\Entity\User
     *
     * @ORM\ManyToOne(targetEntity="Monarc\FrontOffice\Model\Entity\User", cascade={"persist"}, inversedBy="userAnrs")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $user;

    /**
     * @var \Monarc\FrontOffice\Model\Entity\Anr
     *
     * @ORM\ManyToOne(targetEntity="Monarc\FrontOffice\Model\Entity\Anr", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true, onDelete="CASCADE")
     * })
     */
    protected $anr;

    /**
     * @var int
     *
     * @ORM\Column(name="rwd", type="smallint", nullable=true, options={"unsigned":true, "default":1})
     */
    protected $rwd = 1;

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return \Monarc\FrontOffice\Model\Entity\User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param \Monarc\FrontOffice\Model\Entity\User $user
     * @return UserAnr
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return \Monarc\FrontOffice\Model\Entity\Anr
     */
    public function getAnr()
    {
        return $this->anr;
    }

    /**
     * @param \Monarc\FrontOffice\Model\Entity\Anr $anr
     * @return UserAnr
     */
    public function setAnr($anr)
    {
        $this->anr = $anr;
        return $this;
    }

    /**
     * @return int
     */
    public function getRwd()
    {
        return $this->rwd;
    }

    /**
     * @param int $rwd
     * @return UserAnr
     */
    public function setRwd($rwd)
    {
        $this->rwd = $rwd;
        return $this;
    }

    public function getInputFilter($partial = false)
    {
        if (!$this->inputFilter) {
            parent::getInputFilter($partial);

            $this->inputFilter->add([
                'name' => 'user',
                'required' => true,
                'allow_empty' => false,
                'filters' => [
                    [
                        'name' => 'Digits',
                    ],
                ],
            ]);

            $this->inputFilter->add([
                'name' => 'anr',
                'required' => true,
                'allow_empty' => false,
                'filters' => [
                    [
                        'name' => 'Digits',
                    ],
                ],
            ]);
        }

        return $this->inputFilter;
    }
}