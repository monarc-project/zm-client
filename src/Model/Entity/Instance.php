<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\InstanceSuperClass;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Instance
 *
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
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $anr;

    // TODO: implement the same links on Client's entity side with 2 fields rel for parent etc. !!!

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
    protected $instanceMetadatas;

    public function __construct($obj = null)
    {
        $this->instanceMetadatas = new ArrayCollection();

        parent::__construct($obj);
    }

    /**
     * @return InstanceMetadata[]
     */
    public function getInstanceMetadatas()
    {
        return $this->instanceMetadatas;
    }

    public function addInstanceMetadata(InstanceMetadata $instanceMetada): self
    {
        if (!$this->instanceMetadatas->contains($instanceMetada)) {
            $this->instanceMetadatas->add($instanceMetada);
            $instanceMetada->setInstance($this);
        }

        return $this;
    }
}
