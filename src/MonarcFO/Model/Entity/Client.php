<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use MonarcCore\Model\Entity\AbstractEntity;

/**
 * Client
 *
 * @ORM\Table(name="clients")
 * @ORM\Entity
 */
class Client extends AbstractEntity
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
     * @var integer
     *
     * @ORM\Column(name="model_id", type="integer", nullable=true)
     */
    protected $model_id;

    /**
     * @var integer
     *
     * @ORM\Column(name="logo_id", type="integer", nullable=true)
     */
    protected $logo_id;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255, nullable=true)
     */
    protected $name;

    /**
     * @var string
     *
     * @ORM\Column(name="proxy_alias", type="string", length=255, nullable=true)
     */
    protected $proxyAlias;

    /**
    * @var string
    *
    * @ORM\Column(name="contact_email", type="string", length=255, nullable=true)
    */
    protected $contact_email;

    /**
     * @var string
     *
     * @ORM\Column(name="first_user_firstname", type="string", length=255, nullable=true)
     */
    protected $first_user_firstname;

    /**
     * @var string
     *
     * @ORM\Column(name="first_user_lastname", type="string", length=255, nullable=true)
     */
    protected $first_user_lastname;

    /**
     * @var string
     *
     * @ORM\Column(name="first_user_email", type="string", length=255, nullable=true)
     */
    protected $first_user_email;

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
     * @param bool $partial
     * @return mixed
     */
    public function getInputFilter($partial = false)
    {
        if (!$this->inputFilter) {
            parent::getInputFilter($partial);

            $this->inputFilter->add([
                'name' => 'name',
                'required' => ($partial) ? false : true,
                'filters' => [
                    [
                        'name' => 'StringTrim',
                    ],
                ],
                'validators' => [],
            ]);

            $validators = [];
            if (!$partial) {
                $validators[] = [
                    'name' => '\MonarcFO\Validator\UniqueClientProxyAlias',
                    'options' => [
                        'adapter' => $this->getDbAdapter(),
                        'id' => $this->get('id'),
                    ],
                ];
            }
            $this->inputFilter->add([
                'name' => 'proxyAlias',
                'required' => ($partial) ? false : true,
                'filters' => [
                    [
                        'name' => 'StringTrim',
                    ],
                ],
                'validators' => $validators
            ]);
        }
        return $this->inputFilter;
    }
}
