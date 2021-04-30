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
     * @ORM\ManyToOne(targetEntity="Recommandation", cascade={"persist"})
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

    public function getAnr(): ?Anr
    {
        return $this->anr;
    }

    public function setAnr(?Anr $anr): self
    {
        $this->anr = $anr;

        return $this;
    }

    public function getRecommandation(): ?Recommandation
    {
        return $this->recommandation;
    }

    public function setRecommandation(?Recommandation $recommandation): self
    {
        $this->recommandation = $recommandation;

        return $this;
    }

    public function getInstanceRisk(): ?InstanceRisk
    {
        return $this->instanceRisk;
    }

    public function setInstanceRisk(?InstanceRisk $instanceRisk): self
    {
        $this->instanceRisk = $instanceRisk;

        return $this;
    }

    public function getInstanceRiskOp(): ?InstanceRiskOp
    {
        return $this->instanceRiskOp;
    }

    public function setInstanceRiskOp(?InstanceRiskOp $instanceRiskOp)
    {
        $this->instanceRiskOp = $instanceRiskOp;

        return $this;
    }

    public function getInstance(): ?Instance
    {
        return $this->instance;
    }

    public function setInstance(?Instance $instance): self
    {
        $this->instance = $instance;

        return $this;
    }

    public function hasGlobalObjectRelation(): bool
    {
        return $this->globalObject !== null;
    }

    public function getGlobalObject(): ?MonarcObject
    {
        return $this->globalObject;
    }

    public function setGlobalObject(?MonarcObject $globalObject): self
    {
        $this->globalObject = $globalObject;

        return $this;
    }

    public function getAsset(): ?Asset
    {
        return $this->asset;
    }

    public function setAsset(?Asset $asset): self
    {
        $this->asset = $asset;

        return $this;
    }

    public function getThreat(): ?Threat
    {
        return $this->threat;
    }

    public function setThreat(?Threat $threat): self
    {
        $this->threat = $threat;

        return $this;
    }

    public function getVulnerability(): ?Vulnerability
    {
        return $this->vulnerability;
    }

    public function setVulnerability(?Vulnerability $vulnerability): self
    {
        $this->vulnerability = $vulnerability;

        return $this;
    }

    public function getCommentAfter(): string
    {
        return (string)$this->commentAfter;
    }

    public function setCommentAfter(string $commentAfter): self
    {
        $this->commentAfter = $commentAfter;

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
