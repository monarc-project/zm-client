<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use MonarcCore\Model\Entity\AmvSuperclass;

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
     * @var \MonarcFO\Model\Entity\Anr
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Anr", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true, onDelete="CASCADE")
     * })
     */
    protected $anr;

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
     * @var \Doctrine\Common\Collections\Collection
     * @ORM\ManyToMany(targetEntity="MonarcFO\Model\Entity\Measure", inversedBy="amvs", cascade={"persist"})
     * @ORM\JoinTable(name="measures_amvs",
     *  joinColumns={@ORM\JoinColumn(name="amv_id", referencedColumnName="id")},
     *  inverseJoinColumns={@ORM\JoinColumn(name="measure_id", referencedColumnName="uniqid"),@ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id")}
     * )
     */
    protected $measures;
}
