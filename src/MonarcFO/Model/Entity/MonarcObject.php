<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use MonarcCore\Model\Entity\ObjectSuperClass;

/**
 * MonarcObject
 *
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
     * @var \MonarcFO\Model\Entity\Anr
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Anr", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $anr;

    /**
     * @var \Doctrine\Common\Collections\Collection
     * @ORM\ManyToMany(targetEntity="MonarcFO\Model\Entity\Anr", inversedBy="objects", cascade={"persist"})
     * @ORM\JoinTable(name="anrs_objects",
     *  joinColumns={@ORM\JoinColumn(name="object_id", referencedColumnName="uuid"),@ORM\JoinColumn(name="anr_id2", referencedColumnName="anr_id")},
     *  inverseJoinColumns={@ORM\JoinColumn(name="anr_id", referencedColumnName="id")}
     * )
     */
    protected $anrs;

    /**
     * @var \MonarcFO\Model\Entity\ObjectCategory
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\ObjectCategory", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="object_category_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $category;

    /**
     * @var \MonarcFO\Model\Entity\Asset
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Asset", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="asset_id", referencedColumnName="uuid", nullable=true),
     *    @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $asset;

    /**
     * @var \MonarcFO\Model\Entity\RolfTag
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\RolfTag", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="rolf_tag_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $rolfTag;
}
