<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\AbstractEntity;
use Monarc\Core\Model\Entity\ObjectSuperClass;
use Monarc\Core\Model\Entity\Traits\CreateEntityTrait;
use Monarc\Core\Model\Entity\Traits\UpdateEntityTrait;

/**
 * Recommandation Risk
 *
 * @ORM\Table(name="recommandations_risks")
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class RecommandationRisk extends AbstractEntity
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
     * @var Anr
     *
     * @ORM\ManyToOne(targetEntity="Anr", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $anr;

    /**
     * @var Recommandation
     *
     * @ORM\ManyToOne(targetEntity="Recommandation", cascade={"persist"}, fetch="EAGER")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="recommandation_id", referencedColumnName="uuid", nullable=true),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $recommandation;

    /**
     * @var InstanceRisk
     *
     * @ORM\ManyToOne(targetEntity="InstanceRisk", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="instance_risk_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $instanceRisk;

    /**
     * @var InstanceRiskOp
     *
     * @ORM\ManyToOne(targetEntity="InstanceRiskOp", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="instance_risk_op_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $instanceRiskOp;

    /**
     * @var Instance
     *
     * @ORM\ManyToOne(targetEntity="Instance", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="instance_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $instance;

    /**
     * @var MonarcObject
     *
     * @ORM\ManyToOne(targetEntity="MonarcObject", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="object_global_id", referencedColumnName="uuid", nullable=true),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $globalObject;

    /**
     * @var Asset
     *
     * @ORM\ManyToOne(targetEntity="Asset", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="asset_id", referencedColumnName="uuid", nullable=true),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $asset;

    /**
     * @var Threat
     *
     * @ORM\ManyToOne(targetEntity="Threat", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="threat_id", referencedColumnName="uuid", nullable=true),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $threat;

    /**
     * @var Vulnerability
     *
     * @ORM\ManyToOne(targetEntity="Vulnerability", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="vulnerability_id", referencedColumnName="uuid", nullable=true),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
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
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id): self
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
     */
    public function setAnr($anr): self
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
     * @return InstanceRisk|null
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
     * @return InstanceRiskOp|null
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

    public function getGlobalObject(): ?ObjectSuperClass
    {
        return $this->globalObject;
    }

    public function hasGlobalObjectRelation(): bool
    {
        return $this->globalObject !== null;
    }

    public function setGlobalObject(?ObjectSuperClass $globalObject): self
    {
        $this->globalObject = $globalObject;

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

    /**
     * @param bool $partial
     * @return mixed
     */
    public function getInputFilter($partial = true)
    {
        if (!$this->inputFilter) {
            parent::getInputFilter($partial);

            $this->inputFilter->add([
                'name' => 'anr',
                'required' => ($partial) ? false : true,
                'allow_empty' => false,
            ]);

            $this->inputFilter->add([
                'name' => 'recommandation',
                'required' => ($partial) ? false : true,
                'allow_empty' => false,
            ]);

            $this->inputFilter->add([
                'name' => 'risk',
                'required' => ($partial) ? false : true,
                'allow_empty' => false,
            ]);

            $this->inputFilter->add([
                'name' => 'op',
                'required' => ($partial) ? false : true,
                'allow_empty' => false,
                'validators' => [
                    [
                        'name' => 'InArray',
                        'options' => [
                            'haystack' => [0, 1],
                        ],
                        'default' => 0,
                    ],
                ],
            ]);
        }

        return $this->inputFilter;
    }

    public function getFiltersForService(){
        $filterJoin = [
            [
                'as' => 'r',
                'rel' => 'recommandation',
            ],
        ];
        $filterLeft = [
            [
                'as' => 'r1',
                'rel' => 'recommandation',
            ],

        ];
        $filtersCol = [
            'r.uuid',
            'r.anr',
            'r.code',
        ];
        return [$filterJoin,$filterLeft,$filtersCol];
    }
}
