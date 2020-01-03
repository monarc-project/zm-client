<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\AbstractEntity;
use Monarc\Core\Model\Entity\Traits\CreateEntityTrait;
use Monarc\Core\Model\Entity\Traits\UpdateEntityTrait;

/**
 * Delivery
 *
 * @ORM\Table(name="deliveries", indexes={
 *      @ORM\Index(name="anr", columns={"anr_id"})
 * })
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class Delivery extends AbstractEntity
{
    use CreateEntityTrait;
    use UpdateEntityTrait;

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var \Monarc\FrontOffice\Model\Entity\Anr
     *
     * @ORM\ManyToOne(targetEntity="Monarc\FrontOffice\Model\Entity\Anr", cascade={"persist"})
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
    protected $typedoc = 1;

    /**
     * @var string
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
     * @var int
     *
     * @ORM\Column(name="status", type="smallint", options={"unsigned":true, "default":0})
     */
    protected $status = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="classification", type="text", length=255, nullable=true)
     */
    protected $classification;

    /**
     * @var string
     *
     * @ORM\Column(name="resp_customer", type="text", length=255, nullable=true)
     */
    protected $respCustomer;

    /**
     * @var string
     *
     * @ORM\Column(name="resp_smile", type="text", length=255, nullable=true)
     */
    protected $respSmile;
    /**
     * @var string
     *
     * @ORM\Column(name="summary_eval_risk", type="text", length=255, nullable=true)
     */
    protected $summaryEvalRisk;

    /**
     * @return Anr
     */
    public function getAnr()
    {
        return $this->anr;
    }

    /**
     * @param Anr $anr
     * @return Delivery
     */
    public function setAnr($anr)
    {
        $this->anr = $anr;
        return $this;
    }
}
