<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\AbstractEntity;
use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Entity\AssetSuperClass;
use Monarc\Core\Model\Entity\InstanceRiskOpSuperClass;
use Monarc\Core\Model\Entity\InstanceRiskSuperClass;
use Monarc\Core\Model\Entity\InstanceSuperClass;
use Monarc\Core\Model\Entity\ObjectSuperClass;
use Monarc\Core\Model\Entity\ThreatSuperClass;
use Monarc\Core\Model\Entity\Traits\CreateEntityTrait;
use Monarc\Core\Model\Entity\Traits\UpdateEntityTrait;
use Monarc\Core\Model\Entity\VulnerabilitySuperClass;

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
     * @var AnrSuperClass
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
     * @var InstanceRiskSuperClass
     *
     * @ORM\ManyToOne(targetEntity="InstanceRisk", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="instance_risk_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $instanceRisk;

    /**
     * @var InstanceRiskOpSuperClass
     *
     * @ORM\ManyToOne(targetEntity="InstanceRiskOp", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="instance_risk_op_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $instanceRiskOp;

    /**
     * @var InstanceSuperClass
     *
     * @ORM\ManyToOne(targetEntity="Instance", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="instance_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $instance;

    /**
     * @var ObjectSuperClass
     *
     * @ORM\ManyToOne(targetEntity="MonarcObject", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="object_global_id", referencedColumnName="uuid", nullable=true),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $objectGlobal;

    /**
     * @var AssetSuperClass
     *
     * @ORM\ManyToOne(targetEntity="Asset", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="asset_id", referencedColumnName="uuid", nullable=true),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $asset;

    /**
     * @var ThreatSuperClass
     *
     * @ORM\ManyToOne(targetEntity="Threat", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="threat_id", referencedColumnName="uuid", nullable=true),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $threat;

    /**
     * @var VulnerabilitySuperClass
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
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return AnrSuperClass
     */
    public function getAnr()
    {
        return $this->anr;
    }

    /**
     * @param AnrSuperClass $anr
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
     */
    public function setRecommandation($recommandation): self
    {
        $this->recommandation = $recommandation;

        return $this;
    }

    /**
     * @return InstanceRiskSuperClass
     */
    public function getInstanceRisk()
    {
        return $this->instanceRisk;
    }

    /**
     * @param InstanceRiskSuperClass $instanceRisk
     */
    public function setInstanceRisk($instanceRisk): self
    {
        $this->instanceRisk = $instanceRisk;

        return $this;
    }

    /**
     * @return InstanceRiskOpSuperClass
     */
    public function getInstanceRiskOp()
    {
        return $this->instanceRiskOp;
    }

    /**
     * @param InstanceRiskOpSuperClass $instanceRiskOp
     */
    public function setInstanceRiskOp($instanceRiskOp)
    {
        $this->instanceRiskOp = $instanceRiskOp;

        return $this;
    }

    /**
     * @return InstanceSuperClass
     */
    public function getInstance()
    {
        return $this->instance;
    }

    /**
     * @param InstanceRiskSuperClass $instance
     */
    public function setInstance($instance): self
    {
        $this->instance = $instance;

        return $this;
    }

    /**
     * @return ObjectSuperClass
     */
    public function getObjectGlobal()
    {
        return $this->objectGlobal;
    }

    /**
     * @param ObjectSuperClass $objectGlobal
     *
     * @return RecommandationRisk
     */
    public function setObjectGlobal($objectGlobal)
    {
        $this->objectGlobal = $objectGlobal;

        return $this;
    }

    /**
     * @return AssetSuperClass
     */
    public function getAsset()
    {
        return $this->asset;
    }

    /**
     * @param Asset $asset
     */
    public function setAsset($asset): self
    {
        $this->asset = $asset;

        return $this;
    }

    /**
     * @return ThreatSuperClass
     */
    public function getThreat(): self
    {
        return $this->threat;
    }

    /**
     * @param ThreatSuperClass $threat
     */
    public function setThreat($threat): self
    {
        $this->threat = $threat;

        return $this;
    }

    /**
     * @return VulnerabilitySuperClass
     */
    public function getVulnerability()
    {
        return $this->vulnerability;
    }

    /**
     * @param VulnerabilitySuperClass $vulnerability
     */
    public function setVulnerability($vulnerability): self
    {
        $this->vulnerability = $vulnerability;

        return $this;
    }

    /**
     * @param bool $partial
     *
     * @return mixed
     */
    public function getInputFilter($partial = true)
    {
        if (!$this->inputFilter) {
            parent::getInputFilter($partial);

            $this->inputFilter->add([
                'name' => 'anr',
                'required' => !$partial,
                'allow_empty' => false,
            ]);

            $this->inputFilter->add([
                'name' => 'recommandation',
                'required' => !$partial,
                'allow_empty' => false,
            ]);

            $this->inputFilter->add([
                'name' => 'risk',
                'required' => !$partial,
                'allow_empty' => false,
            ]);

            $this->inputFilter->add([
                'name' => 'op',
                'required' => !$partial,
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

    public function getFiltersForService()
    {
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

        return [$filterJoin, $filterLeft, $filtersCol];
    }
}
