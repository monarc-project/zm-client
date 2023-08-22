<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\InstanceSuperClass;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\Table(name="instances", indexes={
 *      @ORM\Index(name="anr", columns={"anr_id"}),
 *      @ORM\Index(name="asset_id", columns={"asset_id"}),
 *      @ORM\Index(name="object_id", columns={"object_id"}),
 *      @ORM\Index(name="root_id", columns={"root_id"}),
 *      @ORM\Index(name="parent_id", columns={"parent_id"})
 * })
 * @ORM\Entity
 */
class Instance extends InstanceSuperClass
{
    /**
     * @var Anr
     *
     * @ORM\ManyToOne(targetEntity="Anr", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=false)
     * })
     */
    protected $anr;

    /**
     * @var Asset
     *
     * @ORM\ManyToOne(targetEntity="Asset", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="asset_id", referencedColumnName="uuid", nullable=true),
     *    @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $asset;

    /**
     * @var MonarcObject
     *
     * @ORM\ManyToOne(targetEntity="MonarcObject", cascade={"persist"}, fetch="EAGER")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="object_id", referencedColumnName="uuid", nullable=true),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $object;

    /**
     * @var InstanceMetadata[]|ArrayCollection
     *
     * @ORM\OneToMany(targetEntity="InstanceMetadata", mappedBy="instance")
     */
    protected $instanceMetadata;

    public function __construct()
    {
        parent::__construct();

        $this->instanceMetadata = new ArrayCollection();
    }

    /**
     * @return InstanceMetadata[]
     */
    public function getInstanceMetadata()
    {
        return $this->instanceMetadata;
    }

    public function addInstanceMetadata(InstanceMetadata $instanceMetadata): self
    {
        if (!$this->instanceMetadata->contains($instanceMetadata)) {
            $this->instanceMetadata->add($instanceMetadata);
            $instanceMetadata->setInstance($this);
        }

        return $this;
    }

    public function getHierarchyString(): string
    {
        return implode(' > ', array_column($this->getHierarchyArray(), 'name' . $this->anr->getLanguage()));
    }
}
