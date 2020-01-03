<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\AbstractEntity;
use Monarc\Core\Model\Entity\Traits\CreateEntityTrait;
use Monarc\Core\Model\Entity\Traits\UpdateEntityTrait;

/**
 * Client
 *
 * @ORM\Table(name="clients")
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class Client extends AbstractEntity
{
    use CreateEntityTrait;
    use UpdateEntityTrait;

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
                    'name' => 'Monarc\FrontOffice\Validator\UniqueClientProxyAlias',
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
