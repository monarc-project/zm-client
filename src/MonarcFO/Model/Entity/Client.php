<?php

namespace MonarcFO\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use MonarcCore\Model\Entity\AbstractEntity;
use MonarcCore\Model\Entity\Country;

/**
 * Clients
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
     * @var integer
     *
     * @ORM\Column(name="country_id", type="integer", nullable=true)
     */
    protected $country_id;

    /**
     * @var integer
     *
     * @ORM\Column(name="city_id", type="integer", nullable=true)
     */
    protected $city_id;

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
     * @ORM\Column(name="address", type="string", length=255, nullable=true)
     */
    protected $address;

    /**
     * @var string
     *
     * @ORM\Column(name="postalcode", type="string", length=16, nullable=true)
     */
    protected $postalcode;

    /**
     * @var string
     *
     * @ORM\Column(name="phone", type="string", length=20, nullable=true)
     */
    protected $phone;

    /**
     * @var string
     *
     * @ORM\Column(name="fax", type="string", length=20, nullable=true)
     */
    protected $fax;

    /**
     * @var string
     *
     * @ORM\Column(name="email", type="string", length=255, nullable=true)
     */
    protected $email;

    /**
     * @var string
     *
     * @ORM\Column(name="employees_number", type="string", length=255, nullable=true)
     */
    protected $employees_number;

    /**
     * @var string
     *
     * @ORM\Column(name="contact_fullname", type="string", length=255, nullable=true)
     */
    protected $contactFullname;

    /**
     * @var string
     *
     * @ORM\Column(name="contact_email", type="string", length=255, nullable=true)
     */
    protected $contact_email;

    /**
     * @var string
     *
     * @ORM\Column(name="contact_phone", type="string", length=20, nullable=true)
     */
    protected $contact_phone;

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
     * @ORM\Column(name="first_user_phone", type="string", length=20, nullable=true)
     */
    protected $first_user_phone;

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
     * @var Country
     */
    protected $country;

    /**
     * @return int
     */
    public function getCountryId()
    {
        return $this->country_id;
    }

    /**
     * @param int $country_id
     */
    public function setCountryId($country_id)
    {
        $this->country_id = $country_id;
    }

    /**
     * @return int
     */
    public function getCityId()
    {
        return $this->city_id;
    }

    /**
     * @param int $city_id
     */
    public function setCityId($city_id)
    {
        $this->city_id = $city_id;
    }

    /**
     * @return Country
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * @param Country $country
     */
    public function setCountry($country)
    {
        $this->country = $country;
    }

    public function getInputFilter($partial = false){

        if (!$this->inputFilter) {
            parent::getInputFilter($partial);

            $this->inputFilter->add(array(
                'name' => 'name',
                'required' => ($partial) ? false : true,
                'filters' => array(
                    array('name' => 'StringTrim',),
                ),
                'validators' => array(),
            ));

            $validators = array();
            if (!$partial) {
                $validators[] = array(
                    'name' => '\MonarcFO\Validator\UniqueClientProxyAlias',
                    'options' => array(
                        'adapter' => $this->getDbAdapter(),
                        'id' => $this->get('id'),
                    ),
                );
            }
            $this->inputFilter->add(array(
                'name' => 'proxyAlias',
                'required' => ($partial) ? false : true,
                'filters' => array(
                    array('name' => 'StringTrim',),
                ),
                'validators' => $validators
            ));
        }
        return $this->inputFilter;
    }
    
}

