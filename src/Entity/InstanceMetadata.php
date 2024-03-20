<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\Traits\CreateEntityTrait;
use Monarc\Core\Entity\Traits\UpdateEntityTrait;

/**
 * @ORM\Table(name="instances_metadata", indexes={
 *      @ORM\Index(name="instance_id", columns={"instance_id"}),
 *      @ORM\Index(name="metadata_id", columns={"metadata_id"}),
 * })
 * @ORM\Entity
 */
class InstanceMetadata
{
    use CreateEntityTrait;
    use UpdateEntityTrait;

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var Instance
     *
     * @ORM\ManyToOne(targetEntity="Instance", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="instance_id", referencedColumnName="id", nullable=false)
     * })
     */
    protected $instance;

    /**
     * @var AnrInstanceMetadataField
     *
     * @ORM\ManyToOne(targetEntity="AnrInstanceMetadataField", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="metadata_id", referencedColumnName="id", nullable=false)
     * })
     */
    protected $anrInstanceMetadataField;

    /**
     * @var string
     *
     * @ORM\Column(name="comment", type="text", nullable=true)
     */
    public $comment = '';

    public function getId(): int
    {
        return $this->id;
    }

    public function getInstance(): Instance
    {
        return $this->instance;
    }

    public function setInstance(Instance $instance): self
    {
        $this->instance = $instance;
        $instance->addInstanceMetadata($this);

        return $this;
    }

    public function getAnrInstanceMetadataField(): AnrInstanceMetadataField
    {
        return $this->anrInstanceMetadataField;
    }

    public function setAnrInstanceMetadataField(AnrInstanceMetadataField $anrInstanceMetadataField): self
    {
        $this->anrInstanceMetadataField = $anrInstanceMetadataField;
        $anrInstanceMetadataField->addInstanceMetadata($this);

        return $this;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function setComment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }
}
