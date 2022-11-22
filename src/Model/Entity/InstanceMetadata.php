<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\Traits\CreateEntityTrait;
use Monarc\Core\Model\Entity\Traits\UpdateEntityTrait;

/**
 * Instance
 *
 * @ORM\Table(name="instances_metadatas", indexes={
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
     *   @ORM\JoinColumn(name="instance_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $instance;

    /**
     * @var AnrMetadatasOnInstances
     *
     * @ORM\ManyToOne(targetEntity="AnrMetadatasOnInstances", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="metadata_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $metadata;

    /**
     * @var string
     *
     * @ORM\Column(name="comment_translation_key", type="string", length=255, options={"default": ""})
     */
    protected $commentTranslationKey = '';

    /**
     * @return int
     */
    public function getId()
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

        return $this;
    }

    public function getCommentTranslationKey(): string
    {
        return $this->commentTranslationKey;
    }

    public function setCommentTranslationKey(string $commentTranslationKey): self
    {
        $this->commentTranslationKey = $commentTranslationKey;

        return $this;
    }

    public function getMetadata(): AnrMetadatasOnInstances
    {
        return $this->metadata;
    }

    public function setMetadata(AnrMetadatasOnInstances $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }
}
