<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\ObjectSuperClass;

/**
 * @ORM\Table(name="objects", indexes={
 *      @ORM\Index(name="anr_id", columns={"anr_id"}),
 *      @ORM\Index(name="object_category_id", columns={"object_category_id"}),
 *      @ORM\Index(name="asset_id", columns={"asset_id"}),
 *      @ORM\Index(name="rolf_tag_id", columns={"rolf_tag_id"})
 * })
 * @ORM\Entity
 */
class MonarcObject extends ObjectSuperClass
{
    /**
     * @var Anr
     *
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="Anr")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=false)
     * })
     */
    protected $anr;

    /**
     * @var ArrayCollection|Anr[]
     *
     * @ORM\ManyToMany(targetEntity="Anr", inversedBy="objects", cascade={"persist"})
     * @ORM\JoinTable(name="anrs_objects",
     *  joinColumns={
     *     @ORM\JoinColumn(name="object_id", referencedColumnName="uuid"),
     *     @ORM\JoinColumn(name="anr_id2", referencedColumnName="anr_id")
     *  },
     *  inverseJoinColumns={@ORM\JoinColumn(name="anr_id", referencedColumnName="id")}
     * )
     */
    protected $anrs;

    /**
     * @var Asset
     *
     * @ORM\ManyToOne(targetEntity="Asset", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="asset_id", referencedColumnName="uuid", nullable=true),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $asset;

    /**
     * Note: If the property use used, the order has to be performed manually due to Doctrine limitation.
     *       Ordered list can be retrieved with use $childrenLinks relation.
     *
     * @var ArrayCollection|ObjectSuperClass[]
     *
     * @ORM\ManyToMany(targetEntity="MonarcObject")
     * @ORM\JoinTable(name="objects_objects",
     *  joinColumns={
     *     @ORM\JoinColumn(name="father_id", referencedColumnName="uuid"),
     *     @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id")
     *  },
     *  inverseJoinColumns={
     *     @ORM\JoinColumn(name="child_id", referencedColumnName="uuid"),
     *     @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id")
     *  }
     * )
     */
    protected $children;

}
