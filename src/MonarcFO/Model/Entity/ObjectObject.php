<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use MonarcCore\Model\Entity\ObjectObjectSuperClass;

/**
 * Object Object
 *
 * @ORM\Table(name="objects_objects", indexes={
 *      @ORM\Index(name="anr", columns={"anr_id"}),
 *      @ORM\Index(name="father_id", columns={"father_id"}),
 *      @ORM\Index(name="child_id", columns={"child_id"})
 * })
 * @ORM\Entity
 */
class ObjectObject extends ObjectObjectSuperClass
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
     * @var \MonarcFO\Model\Entity\Object
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Object", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="father_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $father;

    /**
     * @var \MonarcFO\Model\Entity\Object
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Object", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="child_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $child;
}