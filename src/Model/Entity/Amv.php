<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\AmvSuperClass;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Table(name="amvs", indexes={
 *      @ORM\Index(name="anr", columns={"anr_id"}),
 *      @ORM\Index(name="asset", columns={"asset_id"}),
 *      @ORM\Index(name="threat", columns={"threat_id"}),
 *      @ORM\Index(name="vulnerability", columns={"vulnerability_id"})
 * })
 * @ORM\Entity
 */
class Amv extends AmvSuperClass
{
    /**
     * @var Uuid
     *
     * @ORM\Id
     * @ORM\Column(name="uuid", type="uuid", nullable=false)
     */
    protected $uuid;

    /**
     * @var Anr
     *
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="Anr", fetch="EAGER")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true, onDelete="CASCADE")
     * })
     */
    protected $anr;

    /**
     * @var Asset
     *
     * @ORM\ManyToOne(targetEntity="Asset", cascade={"persist"}, fetch="EAGER")
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
     * @var InstanceRisk[]|ArrayCollection
     *
     * @ORM\OneToMany(targetEntity="InstanceRisk", mappedBy="amv")
     */
    protected $instanceRisks;

    public function __construct()
    {
        $this->instanceRisks = new ArrayCollection();

        parent::__construct();
    }

    public function getInstanceRisks()
    {
        return $this->instanceRisks;
    }

    public function getImplicitPositionRelationsValues(): array
    {
        return array_merge(
            ['anr' => $this->anr->getId()],
            parent::getImplicitPositionRelationsValues()
        );
    }
}
