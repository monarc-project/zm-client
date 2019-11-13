<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\InstanceConsequenceSuperClass;

/**
 * Instance Consequence
 *
 * @ORM\Table(name="instances_consequences", indexes={
 *      @ORM\Index(name="anr", columns={"anr_id"}),
 *      @ORM\Index(name="instance_id", columns={"instance_id"}),
 *      @ORM\Index(name="object_id", columns={"object_id"}),
 *      @ORM\Index(name="scale_impact_type_id", columns={"scale_impact_type_id"})
 * })
 * @ORM\Entity
 */
class InstanceConsequence extends InstanceConsequenceSuperClass
{
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
     * @var \Monarc\FrontOffice\Model\Entity\Instance
     *
     * @ORM\ManyToOne(targetEntity="Monarc\FrontOffice\Model\Entity\Instance", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="instance_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $instance;

    /**
     * @var \Monarc\FrontOffice\Model\Entity\MonarcObject
     *
     * @ORM\ManyToOne(targetEntity="Monarc\FrontOffice\Model\Entity\MonarcObject", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="object_id", referencedColumnName="uuid", nullable=true),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $object;

    /**
     * @var \Monarc\FrontOffice\Model\Entity\ScaleImpactType
     *
     * @ORM\ManyToOne(targetEntity="Monarc\FrontOffice\Model\Entity\ScaleImpactType", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="scale_impact_type_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $scaleImpactType;
}
