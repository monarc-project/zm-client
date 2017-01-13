<?php
namespace MonarcFO\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use MonarcCore\Model\Entity\AbstractEntity;

/**
 * Thme
 *
 * @ORM\Table(name="deliveries", indexes={
 *      @ORM\Index(name="anr", columns={"anr_id"})
 * })
 * @ORM\Entity
 */
class Delivery extends AbstractEntity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var \MonarcFO\Model\Entity\Anr
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Anr", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $anr;

    /**
     * @var smallint
     *
     * @ORM\Column(name="typedoc", type="smallint", options={"unsigned":true, "default":1})
     */
    protected $typedoc = 1;

    /**
     * @var text
     *
     * @ORM\Column(name="name", type="text", length=255, nullable=true)
     */
    protected $name;

    /**
     * @var string
     *
     * @ORM\Column(name="version", type="string", length=255, nullable=true)
     */
    protected $version;

    /**
     * @var smallint
     *
     * @ORM\Column(name="status", type="smallint", options={"unsigned":true, "default":0})
     */
    protected $status = 0;

    /**
     * @var text
     *
     * @ORM\Column(name="classification", type="text", length=255, nullable=true)
     */
    protected $classification;

    /**
     * @var text
     *
     * @ORM\Column(name="resp_customer", type="text", length=255, nullable=true)
     */
    protected $respCustomer;

    /**
     * @var text
     *
     * @ORM\Column(name="resp_smile", type="text", length=255, nullable=true)
     */
    protected $respSmile;
    /**
     * @var text
     *
     * @ORM\Column(name="summary_eval_risk", type="text", length=255, nullable=true)
     */
    protected $summaryEvalRisk;

    /**
     * @var string
     *
     * @ORM\Column(name="creator", type="string", length=255, nullable=true)
     */
    protected $creator;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime", nullable=true)
     */
    protected $createdAt;

    /**
     * @var string
     *
     * @ORM\Column(name="updater", type="string", length=255, nullable=true)
     */
    protected $updater;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="updated_at", type="datetime", nullable=true)
     */
    protected $updatedAt;

    /**
     * @return Anr
     */
    public function getAnr()
    {
        return $this->anr;
    }

    /**
     * @param Anr $anr
     * @return RolfCategory
     */
    public function setAnr($anr)
    {
        $this->anr = $anr;
        return $this;
    }
}
