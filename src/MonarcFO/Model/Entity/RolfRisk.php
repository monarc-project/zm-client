<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use MonarcCore\Model\Entity\RolfRiskSuperclass;

/**
 * Rolf Risk
 *
 * @ORM\Table(name="rolf_risks", indexes={
 *      @ORM\Index(name="anr", columns={"anr_id"})
 * })
 * @ORM\Entity
 */
class RolfRisk extends RolfRiskSuperclass
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
     * @var \MonarcFO\Model\Entity\RolfCategory
     *
     * @ORM\ManyToMany(targetEntity="MonarcFO\Model\Entity\RolfCategory", cascade={"persist"})
     * @ORM\JoinTable(name="rolf_risks_categories",
     *  joinColumns={@ORM\JoinColumn(name="rolf_risk_id", referencedColumnName="id")},
     *  inverseJoinColumns={@ORM\JoinColumn(name="rolf_category_id", referencedColumnName="id")}
     * )
     */
    protected $categories;

    /**
     * @var \MonarcFO\Model\Entity\RolfTag
     *
     * @ORM\ManyToMany(targetEntity="MonarcFO\Model\Entity\RolfTag", inversedBy="risks", cascade={"persist"})
     * @ORM\JoinTable(name="rolf_risks_tags",
     *  joinColumns={@ORM\JoinColumn(name="rolf_risk_id", referencedColumnName="id")},
     *  inverseJoinColumns={@ORM\JoinColumn(name="rolf_tag_id", referencedColumnName="id")}
     * )
     */
    protected $tags;

    /**
     * @return Anr
     */
    public function getAnr()
    {
        return $this->anr;
    }

    /**
     * @param Anr $anr
     * @return RolfRisk
     */
    public function setAnr($anr)
    {
        $this->anr = $anr;
        return $this;
    }

    /**
     * @return RolfCategory
     */
    public function getCategories()
    {
        return $this->categories;
    }

    /**
     * @param RolfCategory $categories
     * @return RolfRisk
     */
    public function setCategories($categories)
    {
        $this->categories = $categories;
        return $this;
    }

    /**
     * @return RolfTag
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * @param RolfTag $tags
     * @return RolfRisk
     */
    public function setTags($tags)
    {
        $this->tags = $tags;
        return $this;
    }
}