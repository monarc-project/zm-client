<?php
namespace MonarcFO\Model\Entity;

use MonarcCore\Model\Entity\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;

/**
 * User
 *
 * @ORM\Table(name="users", uniqueConstraints={@ORM\UniqueConstraint(name="email", columns={"email"})})
 * @ORM\Entity
 */
class User extends AbstractEntity
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
     * @var \DateTime
     *
     * @ORM\Column(name="date_start", type="date", nullable=true)
     */
    protected $dateStart;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date_end", type="date", nullable=true)
     */
    protected $dateEnd;

    /**
     * @var smallint
     *
     * @ORM\Column(name="status", type="smallint", nullable=true)
     */
    protected $status = '1';

    /**
     * @var string
     *
     * @ORM\Column(name="firstname", type="string", length=255, nullable=true)
     */
    protected $firstname;

    /**
     * @var string
     *
     * @ORM\Column(name="lastname", type="string", length=255, nullable=true)
     */
    protected $lastname;

    /**
     * @var string
     *
     * @ORM\Column(name="email", type="string", length=255, nullable=true)
     */
    protected $email;

    /**
     * @var string
     *
     * @ORM\Column(name="phone", type="string", length=20, nullable=true)
     */
    protected $phone;

    /**
     * @var string
     *
     * @ORM\Column(name="password", type="string", length=255, nullable=true)
     */
    protected $password;

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
     * @var integer
     *
     * @ORM\Column(name="language", type="integer", precision=0, scale=0, nullable=false, unique=false)
     */
    protected $language;

    /**
     * @var \MonarcFO\Model\Entity\Anr
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Anr", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="current_anr_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $currentAnr;

    /**
     * @param bool $partial
     * @return mixed
     */
    public function getInputFilter($partial = false)
    {
        if (!$this->inputFilter) {
            parent::getInputFilter($partial);
            $this->inputFilter->add([
                'name' => 'firstname',
                'required' => ($partial) ? false : true,
                'filters' => [
                    ['name' => 'StringTrim',],
                ],
                'validators' => [],
            ]);
            $this->inputFilter->add([
                'name' => 'lastname',
                'required' => ($partial) ? false : true,
                'filters' => [
                    ['name' => 'StringTrim',],
                ],
                'validators' => [],
            ]);

            $validators = [
                ['name' => 'EmailAddress'],
            ];
            if (!$partial) {
                $validators[] = [
                    'name' => '\MonarcCore\Validator\UniqueEmail',
                    'options' => [
                        'adapter' => $this->getDbAdapter(),
                        'id' => $this->get('id'),
                    ],
                ];
            }

            $this->inputFilter->add([
                'name' => 'email',
                'required' => ($partial) ? false : true,
                'filters' => [
                    ['name' => 'StringTrim',],
                ],
                'validators' => $validators
            ]);

            $this->inputFilter->add([
                'name' => 'role',
                'required' => false,
            ]);

            $this->inputFilter->add([
                'name' => 'password',
                'allowEmpty' => true,
                'continueIfEmpty' => true,
                'required' => false,
                'filters' => [
                    [
                        'name' => '\MonarcCore\Filter\Password',
                        'options' => [
                            'salt' => $this->getUserSalt(),
                        ],
                    ],
                ],
                'validators' => [],
            ]);

            $this->inputFilter->add([
                'name' => 'language',
                'allowEmpty' => true,
                'continueIfEmpty' => true,
                'required' => false,
                'filters' => [
                    ['name' => 'ToInt',],
                ],
                'validators' => [],
            ]);
        }
        return $this->inputFilter;
    }

    public function setUserSalt($userSalt)
    {
        $this->parameters['userSalt'] = $userSalt;
        return $this;
    }

    public function getUserSalt()
    {
        return isset($this->parameters['userSalt']) ? $this->parameters['userSalt'] : '';
    }
}