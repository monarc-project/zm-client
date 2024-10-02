<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\AnrInstanceMetadataFieldSuperClass;

/**
 * @ORM\Table(name="anr_instance_metadata_fields", indexes={
 *      @ORM\Index(name="anr_id", columns={"anr_id"})
 * })
 * @ORM\Entity
 */
class AnrInstanceMetadataField extends AnrInstanceMetadataFieldSuperClass
{
    /**
     * @var int
     *
     * @ORM\Column(name="is_deletable", type="smallint", options={"unsigned":true, "default":1})
     */
    protected $isDeletable = 1;

    /**
     * @var string
     *
     * @ORM\Column(name="label", type="string", length=255, nullable=false, options={"default": ""})
     */
    protected $label = '';

    /**
     * @var ArrayCollection|InstanceMetadata[]
     *
     * @ORM\OneToMany(targetEntity="InstanceMetadata", mappedBy="anrInstanceMetadataField")
     */
    protected $instancesMetadata;

    public function __construct()
    {
        $this->instancesMetadata = new ArrayCollection();
    }

    public function isDeletable(): bool
    {
        return (bool)$this->isDeletable;
    }

    public function setIsDeletable(bool $isDeletable): self
    {
        $this->isDeletable = (int)$isDeletable;

        return $this;
    }

    public function getInstancesMetadata()
    {
        return $this->instancesMetadata;
    }

    public function addInstanceMetadata(InstanceMetadata $instanceMetadata): self
    {
        if (!$this->instancesMetadata->contains($instanceMetadata)) {
            $this->instancesMetadata->add($instanceMetadata);
            $instanceMetadata->setAnrInstanceMetadataField($this);
        }

        return $this;
    }

    public function removeInstanceMetadata(InstanceMetadata $instanceMetadata): self
    {
        if ($this->instancesMetadata->contains($instanceMetadata)) {
            $this->instancesMetadata->removeElement($instanceMetadata);
        }

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }
}
