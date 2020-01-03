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
 * Recommandation Historic
 *
 * @ORM\Table(name="recommandations_historics")
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class RecommandationHistoric extends AbstractEntity
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
     * @var \Monarc\FrontOffice\Model\Entity\InstanceRisk
     *
     * @ORM\ManyToOne(targetEntity="Monarc\FrontOffice\Model\Entity\InstanceRisk", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="instance_risk_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $instanceRisk;

    /**
     * @var \Monarc\FrontOffice\Model\Entity\InstanceRiskOp
     *
     * @ORM\ManyToOne(targetEntity="Monarc\FrontOffice\Model\Entity\InstanceRiskOp", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="instance_risk_op_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $instanceRiskOp;

    /**
     * @var smallint
     *
     * @ORM\Column(name="final", type="smallint", options={"unsigned":false, "default":1})
     */
    protected $final = 1;

    /**
     * @var string
     *
     * @ORM\Column(name="impl_comment", type="string", length=255, nullable=true)
     */
    protected $implComment;

    /**
     * @var string
     *
     * @ORM\Column(name="reco_code", type="string", length=100, nullable=true)
     */
    protected $recoCode;

    /**
     * @var string
     *
     * @ORM\Column(name="reco_description", type="string", length=255, nullable=true)
     */
    protected $recoDescription;

    /**
     * @var smallint
     *
     * @ORM\Column(name="reco_importance", type="smallint", options={"unsigned":true, "default":1})
     */
    protected $recoImportance = 1;

    /**
     * @var string
     *
     * @ORM\Column(name="reco_comment", type="string", length=255, nullable=true)
     */
    protected $recoComment;

    /**
     * @var string
     *
     * @ORM\Column(name="reco_responsable", type="string", length=255, nullable=true)
     */
    protected $recoResponsable;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="reco_duedate", type="datetime", nullable=true)
     */
    protected $recoDuedate;

    /**
     * @var string
     *
     * @ORM\Column(name="risk_instance", type="string", length=255, nullable=true)
     */
    protected $riskInstance;

    /**
     * @var string
     *
     * @ORM\Column(name="risk_instance_context", type="string", length=255, nullable=true)
     */
    protected $riskInstanceContext;

    /**
     * @var string
     *
     * @ORM\Column(name="risk_asset", type="string", length=255, nullable=true)
     */
    protected $riskAsset;

    /**
     * @var string
     *
     * @ORM\Column(name="risk_threat", type="string", length=255, nullable=true)
     */
    protected $riskThreat;

    /**
     * @var smallint
     *
     * @ORM\Column(name="risk_threat_val", type="smallint", options={"unsigned":false, "default":-1})
     */
    protected $riskThreatVal = -1;

    /**
     * @var string
     *
     * @ORM\Column(name="risk_vul", type="string", length=255, nullable=true)
     */
    protected $riskVul;

    /**
     * @var smallint
     *
     * @ORM\Column(name="risk_vul_val_before", type="smallint", options={"unsigned":false, "default":-1})
     */
    protected $riskVulValBefore = -1;

    /**
     * @var smallint
     *
     * @ORM\Column(name="risk_vul_val_after", type="smallint", options={"unsigned":false, "default":-1})
     */
    protected $riskVulValAfter = -1;

    /**
     * @var string
     *
     * @ORM\Column(name="risk_op_description", type="string", length=255, nullable=true)
     */
    protected $riskOpDescription;

    /**
     * @var smallint
     *
     * @ORM\Column(name="net_prob_before", type="smallint", options={"unsigned":false, "default":-1})
     */
    protected $netProbBefore = -1;

    /**
     * @var smallint
     *
     * @ORM\Column(name="net_r_before", type="smallint", options={"unsigned":false, "default":-1})
     */
    protected $netRBefore = -1;

    /**
     * @var smallint
     *
     * @ORM\Column(name="net_o_before", type="smallint", options={"unsigned":false, "default":-1})
     */
    protected $netOBefore = -1;

    /**
     * @var smallint
     *
     * @ORM\Column(name="net_l_before", type="smallint", options={"unsigned":false, "default":-1})
     */
    protected $netLBefore = -1;

    /**
     * @var smallint
     *
     * @ORM\Column(name="net_f_before", type="smallint", options={"unsigned":false, "default":-1})
     */
    protected $netFBefore = -1;

    /**
     * @var smallint
     *
     * @ORM\Column(name="net_p_before", type="smallint", options={"unsigned":false, "default":-1})
     */
    protected $netPBefore = -1;

    /**
     * @var smallint
     *
     * @ORM\Column(name="risk_kind_of_measure", type="smallint", options={"unsigned":false, "default":-1})
     */
    protected $riskKindOfMeasure = -1;

    /**
     * @var string
     *
     * @ORM\Column(name="risk_comment_before", type="string", length=255, nullable=true)
     */
    protected $riskCommentBefore;

    /**
     * @var string
     *
     * @ORM\Column(name="risk_comment_after", type="string", length=255, nullable=true)
     */
    protected $riskCommentAfter;

    /**
     * @var smallint
     *
     * @ORM\Column(name="risk_max_risk_before", type="smallint", options={"unsigned":false, "default":-1})
     */
    protected $riskMaxRiskBefore = -1;

    /**
     * @var string
     *
     * @ORM\Column(name="risk_color_before", type="string", length=100, nullable=true)
     */
    protected $riskColorBefore;

    /**
     * @var smallint
     *
     * @ORM\Column(name="risk_max_risk_after", type="smallint", options={"unsigned":false, "default":-1})
     */
    protected $riskMaxRiskAfter = -1;

    /**
     * @var string
     *
     * @ORM\Column(name="risk_color_after", type="string", length=100, nullable=true)
     */
    protected $riskColorAfter;

    /**
     * @var string
     *
     * @ORM\Column(name="cache_comment_after", type="string", length=255, nullable=true)
     */
    protected $cacheCommentAfter;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return Asset
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return Anr
     */
    public function getAnr()
    {
        return $this->anr;
    }

    /**
     * @param Anr $anr
     * @return Scale
     */
    public function setAnr($anr)
    {
        $this->anr = $anr;
        return $this;
    }
}
