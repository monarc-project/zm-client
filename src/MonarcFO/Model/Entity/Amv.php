<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use MonarcCore\Model\Entity\AmvSuperclass;
use Zend\Validator\Uuid;

/**
 * Amv
 *
 * @ORM\Table(name="amvs", indexes={
 *      @ORM\Index(name="anr", columns={"anr_id"}),
 *      @ORM\Index(name="asset", columns={"asset_id"}),
 *      @ORM\Index(name="threat", columns={"threat_id"}),
 *      @ORM\Index(name="vulnerability", columns={"vulnerability_id"})
 * })
 * @ORM\Entity
 */
class Amv extends AmvSuperclass
{

    /**
     * @var integer
     *
     * @ORM\Column(name="uuid", type="uuid", nullable=false)
     * @ORM\Id
     */
    protected $uuid;
    /**
     * @var \MonarcFO\Model\Entity\Anr
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Anr", fetch="EAGER")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true, onDelete="CASCADE")
     * })
     */
    protected $anr;

    /**
     * @var \MonarcFO\Model\Entity\Asset
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Asset", cascade={"persist"}, fetch="EAGER")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="asset_id", referencedColumnName="uuid", nullable=true),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $asset;

    /**
     * @var \MonarcFO\Model\Entity\Threat
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Threat", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="threat_id", referencedColumnName="uuid", nullable=true),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $threat;

    /**
     * @var \MonarcFO\Model\Entity\Vulnerability
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Vulnerability", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="vulnerability_id", referencedColumnName="uuid", nullable=true),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $vulnerability;

    /**
     * @var \Doctrine\Common\Collections\Collection
     * @ORM\ManyToMany(targetEntity="MonarcFO\Model\Entity\Measure", mappedBy="amvs", )
     * @ORM\JoinTable(name="measures_amvs",
     *  joinColumns={@ORM\JoinColumn(name="amv_id", referencedColumnName="uuid"),@ORM\JoinColumn(name="anr_id2", referencedColumnName="anr_id")},
     *  inverseJoinColumns={@ORM\JoinColumn(name="measure_id", referencedColumnName="uuid"),@ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id")}
     * )
     */
    protected $measures;

    protected $parameters = array(
        'implicitPosition' => array(
            'field' => 'asset',
        ),
    );

    public function getInputFilter($partial = false)
    {
        if (!$this->inputFilter) {
            parent::getInputFilter($partial);

            $this->inputFilter->add(array(
                'name' => 'status',
                'required' => ($partial) ? false : true,
                'allow_empty' => false,
                'validators' => array(
                    array(
                        'name' => 'Callback', //'\MonarcCore\Validator\Uuid',
                        'options' => array(
                            'messages' => array(
                                \Zend\Validator\Callback::INVALID_VALUE => 'an uuid is missing or incorrect',
                            ),
                            'callback' => function ($value, $context = array()) use ($partial) {
                                if (!$partial) {
                                    $texts = ['threat', 'vulnerability', 'asset'];
                                    foreach ($texts as $text) {
                                        if (is_array($context[$text])) {
                                            if (!preg_match(Uuid::REGEX_UUID, $context[$text]['uuid'])) return false;
                                        } else {
                                            if (!preg_match(Uuid::REGEX_UUID, $context[$text])) return false;
                                        }
                                    }
                                    return true;
                                } else {
                                    return true;
                                }
                            },
                        ),
                    ),
                ),
            ));
        }
        return $this->inputFilter;
    }
}
