<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\Traits\CreateEntityTrait;
use Monarc\Core\Entity\Traits\UpdateEntityTrait;

/**
 * @ORM\Table(name="deliveries", indexes={
 *      @ORM\Index(name="anr", columns={"anr_id"})
 * })
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class Delivery
{
    use CreateEntityTrait;
    use UpdateEntityTrait;

    public const DOC_TYPE_CONTEXT_VALIDATION = 1;
    public const DOC_TYPE_MODEL_VALIDATION = 2;
    public const DOC_TYPE_FINAL_REPORT = 3;
    public const DOC_TYPE_IMPLEMENTATION_PLAN = 4;
    public const DOC_TYPE_SOA = 5;
    public const DOC_TYPE_REC_OF_PROCESSING_ACT = 6;
    public const DOC_TYPE_REC_OF_PROCESSING_ACT_ALL = 7;

    /**
     * @var int
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
     * @var int
     *
     * @ORM\Column(name="typedoc", type="smallint", options={"unsigned":true, "default":1})
     */
    protected $docType = self::DOC_TYPE_CONTEXT_VALIDATION;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="text", nullable=true)
     */
    protected $name = '';

    /**
     * @var string
     *
     * @ORM\Column(name="version", type="string", length=255, nullable=true)
     */
    protected $version = '';

    /**
     * @var int
     *
     * @ORM\Column(name="status", type="smallint", options={"unsigned":true, "default":0})
     */
    protected $status = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="classification", type="text", length=255, nullable=false)
     */
    protected $classification = '';

    /**
     * @var string
     *
     * @ORM\Column(name="resp_customer", type="text", length=255, nullable=false)
     */
    protected $respCustomer = '';

    /**
     * @var string
     *
     * @ORM\Column(name="responsible_manager", type="text", length=255, nullable=false)
     */
    protected $responsibleManager = '';

    /**
     * @var string
     *
     * @ORM\Column(name="summary_eval_risk", type="text", nullable=true)
     */
    protected $summaryEvalRisk = '';

    public function getId(): int
    {
        return $this->id;
    }

    public function getAnr(): Anr
    {
        return $this->anr;
    }

    public function setAnr(Anr $anr): self
    {
        $this->anr = $anr;

        return $this;
    }

    public function getDocType(): int
    {
        return $this->docType;
    }

    public function setDocType(int $docType): self
    {
        $this->docType = $docType;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getClassification(): string
    {
        return $this->classification;
    }

    public function setClassification(string $classification): self
    {
        $this->classification = $classification;

        return $this;
    }

    public function getRespCustomer(): string
    {
        return $this->respCustomer;
    }

    public function setRespCustomer(string $respCustomer): self
    {
        $this->respCustomer = $respCustomer;

        return $this;
    }

    public function getResponsibleManager(): string
    {
        return $this->responsibleManager;
    }

    public function setResponsibleManager(string $responsibleManager): self
    {
        $this->responsibleManager = $responsibleManager;

        return $this;
    }

    public function getSummaryEvalRisk(): string
    {
        return $this->summaryEvalRisk;
    }

    public function setSummaryEvalRisk(string $summaryEvalRisk): self
    {
        $this->summaryEvalRisk = $summaryEvalRisk;

        return $this;
    }
}
