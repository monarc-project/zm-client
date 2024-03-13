<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\InstanceRiskSuperClass;

/**
 * @ORM\Table(name="instances_risks", indexes={
 *      @ORM\Index(name="anr", columns={"anr_id"}),
 *      @ORM\Index(name="amv_id", columns={"amv_id"}),
 *      @ORM\Index(name="asset_id", columns={"asset_id"}),
 *      @ORM\Index(name="threat_id", columns={"threat_id"}),
 *      @ORM\Index(name="vulnerability_id", columns={"vulnerability_id"}),
 *      @ORM\Index(name="instance_id", columns={"instance_id"})
 *      @ORM\Index(name="risk_owner_id", columns={"risk_owner_id"})
 * })
 * @ORM\Entity
 */
class InstanceRisk extends InstanceRiskSuperClass
{
    /**
     * @var Amv
     *
     * @ORM\ManyToOne(targetEntity="Amv", inversedBy="instanceRisks", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="amv_id", referencedColumnName="uuid", nullable=true, onDelete="SET NULL"),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $amv;

    /**
     * @var Asset
     *
     * @ORM\ManyToOne(targetEntity="Asset", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="asset_id", referencedColumnName="uuid"),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id")
     * })
     */
    protected $asset;

    /**
     * @var Threat
     *
     * @ORM\ManyToOne(targetEntity="Threat", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="threat_id", referencedColumnName="uuid"),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id")
     * })
     */
    protected $threat;

    /**
     * @var Vulnerability
     *
     * @ORM\ManyToOne(targetEntity="Vulnerability", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="vulnerability_id", referencedColumnName="uuid"),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id")
     * })
     */
    protected $vulnerability;

    /**
     * @var ArrayCollection|RecommendationRisk[]
     *
     * @ORM\OneToMany(targetEntity="RecommendationRisk", mappedBy="instanceRisk", cascade={"persist", "remove"})
     */
    protected $recommendationRisks;

    /**
     * @var InstanceRiskOwner|null
     *
     * @ORM\ManyToOne(targetEntity="InstanceRiskOwner", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="risk_owner_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $instanceRiskOwner;

    /**
     * @var string
     *
     * @ORM\Column(name="context", type="string", length=255, nullable=true)
     */
    protected $context;

    public function __construct()
    {
        $this->recommendationRisks = new ArrayCollection();
    }

    public static function constructFromObject(InstanceRiskSuperClass $instanceRisk): InstanceRiskSuperClass
    {
        /** @var InstanceRisk $instanceRisk */
        $instanceRisk = parent::constructFromObject($instanceRisk);

        return $instanceRisk->setContext($instanceRisk->getContext());
    }

    public static function constructFromObjectOfTheSameAnr(InstanceRisk $instanceRisk): static
    {
        return self::constructFromObject($instanceRisk)
            ->setAnr($instanceRisk->getAnr())
            ->setAsset($instanceRisk->getAsset())
            ->setThreat($instanceRisk->getThreat())
            ->setVulnerability($instanceRisk->getVulnerability())
            ->setAmv($instanceRisk->getAmv())
            ->setInstanceRiskOwner($instanceRisk->getInstanceRiskOwner());
    }

    public function getRecommendationRisks()
    {
        return $this->recommendationRisks;
    }

    public function getInstanceRiskOwner(): ?InstanceRiskOwner
    {
        return $this->instanceRiskOwner;
    }

    public function setInstanceRiskOwner(?InstanceRiskOwner $instanceRiskOwner): self
    {
        if ($instanceRiskOwner === null) {
            if ($this->instanceRiskOwner !== null) {
                $this->instanceRiskOwner->removeInstanceRisk($this);
                $this->instanceRiskOwner = null;
            }
        } else {
            $this->instanceRiskOwner = $instanceRiskOwner;
            $instanceRiskOwner->addInstanceRisk($this);
        }

        return $this;
    }

    public function getContext(): string
    {
        return (string)$this->context;
    }

    public function setContext(string $context): self
    {
        $this->context = $context;

        return $this;
    }
}
