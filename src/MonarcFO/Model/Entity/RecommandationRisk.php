<?php

namespace MonarcFO\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use MonarcCore\Model\Entity\AbstractEntity;

/**
 * Recommandation Risk
 *
 * @ORM\Table(name="recommandations_risks")
 * @ORM\Entity
 */
class RecommandationRisk extends AbstractEntity
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
     * @var \MonarcFO\Model\Entity\Anr
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Anr", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $anr;

    /**
     * @var \MonarcFO\Model\Entity\Recommandation
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Recommandation", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="recommandation_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $recommandation;

    /**
     * @var \MonarcFO\Model\Entity\InstanceRisk
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\InstanceRisk", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="instance_risk_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $instanceRisk;

    /**
     * @var \MonarcFO\Model\Entity\InstanceRiskOp
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\InstanceRiskOp", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="instance_risk_op_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $instanceRiskOp;

    /**
     * @var \MonarcFO\Model\Entity\Instance
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Instance", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="instance_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $instance;

    /**
     * @var \MonarcFO\Model\Entity\Object
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Object", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="object_global_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $objectGlobal;

    /**
     * @var \MonarcFO\Model\Entity\Asset
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Asset", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="asset_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $asset;

    /**
     * @var \MonarcFO\Model\Entity\Threat
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Threat", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="threat_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $threat;

    /**
     * @var \MonarcFO\Model\Entity\Vulnerability
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Vulnerability", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="vulnerability_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $vulnerability;

    /**
     * @var string
     *
     * @ORM\Column(name="comment_after", type="string", length=255, nullable=true)
     */
    protected $commentAfter;

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
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return Asset
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return Anr
     */
    public function getAnr()
    {
        return $this->anr;
    }

    /**
     * @param Anr $anr
     * @return Scale
     */
    public function setAnr($anr)
    {
        $this->anr = $anr;
        return $this;
    }

    /**
     * @return Recommandation
     */
    public function getRecommandation()
    {
        return $this->recommandation;
    }

    /**
     * @param Recommandation $recommandation
     * @return RecommandationRisk
     */
    public function setRecommandation($recommandation)
    {
        $this->recommandation = $recommandation;
        return $this;
    }

    /**
     * @return InstanceRisk
     */
    public function getInstanceRisk()
    {
        return $this->instanceRisk;
    }

    /**
     * @param InstanceRisk $instanceRisk
     * @return RecommandationRisk
     */
    public function setInstanceRisk($instanceRisk)
    {
        $this->instanceRisk = $instanceRisk;
        return $this;
    }

    /**
     * @return InstanceRiskOp
     */
    public function getInstanceRiskOp()
    {
        return $this->instanceRiskOp;
    }

    /**
     * @param InstanceRiskOp $instanceRiskOp
     * @return RecommandationRisk
     */
    public function setInstanceRiskOp($instanceRiskOp)
    {
        $this->instanceRiskOp = $instanceRiskOp;
        return $this;
    }

    /**
     * @return Instance
     */
    public function getInstance()
    {
        return $this->instance;
    }

    /**
     * @param Instance $instance
     * @return RecommandationRisk
     */
    public function setInstance($instance)
    {
        $this->instance = $instance;
        return $this;
    }

    /**
     * @return Object
     */
    public function getObjectGlobal()
    {
        return $this->objectGlobal;
    }

    /**
     * @param Object $objectGlobal
     * @return RecommandationRisk
     */
    public function setObjectGlobal($objectGlobal)
    {
        $this->objectGlobal = $objectGlobal;
        return $this;
    }

    /**
     * @return Asset
     */
    public function getAsset()
    {
        return $this->asset;
    }

    /**
     * @param Asset $asset
     * @return RecommandationRisk
     */
    public function setAsset($asset)
    {
        $this->asset = $asset;
        return $this;
    }

    /**
     * @return Threat
     */
    public function getThreat()
    {
        return $this->threat;
    }

    /**
     * @param Threat $threat
     * @return RecommandationRisk
     */
    public function setThreat($threat)
    {
        $this->threat = $threat;
        return $this;
    }

    /**
     * @return Vulnerability
     */
    public function getVulnerability()
    {
        return $this->vulnerability;
    }

    /**
     * @param Vulnerability $vulnerability
     * @return RecommandationRisk
     */
    public function setVulnerability($vulnerability)
    {
        $this->vulnerability = $vulnerability;
        return $this;
    }

    public function getInputFilter($partial = true){
        if (!$this->inputFilter) {
            parent::getInputFilter($partial);

            $this->inputFilter->add(array(
                'name' => 'anr',
                'required' => ($partial) ? false : true,
                'allow_empty' => false,
            ));

            $this->inputFilter->add(array(
                'name' => 'recommandation',
                'required' => ($partial) ? false : true,
                'allow_empty' => false,
            ));

            $this->inputFilter->add(array(
                'name' => 'risk',
                'required' => ($partial) ? false : true,
                'allow_empty' => false,
            ));

            $this->inputFilter->add(array(
                'name' => 'op',
                'required' => ($partial) ? false : true,
                'allow_empty' => false,
                'validators' => array(
                    array(
                        'name' => 'InArray',
                        'options' => array(
                            'haystack' => [0, 1],
                        ),
                        'default' => 0,
                    ),
                ),
            ));

        }

        return $this->inputFilter;
    }

}

