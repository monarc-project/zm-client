<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use MonarcCore\Model\Entity\ScaleImpactTypeSuperClass;

/**
 * Scale Type
 *
 * @ORM\Table(name="scales_impact_types", indexes={
 *      @ORM\Index(name="anr", columns={"anr_id"}),
 *      @ORM\Index(name="scale_id", columns={"scale_id"})
 * })
 * @ORM\Entity
 */
class ScaleImpactType extends ScaleImpactTypeSuperClass
{

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
     * @var \MonarcFO\Model\Entity\Scale
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Scale", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="scale_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $scale;
}