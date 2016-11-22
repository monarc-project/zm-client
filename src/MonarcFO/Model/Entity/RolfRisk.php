<?php

namespace MonarcFO\Model\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * RolfRisk
 *
 * @ORM\Table(name="rolf_risks", indexes={
 *      @ORM\Index(name="anr", columns={"anr_id"})
 * })
 * @ORM\Entity
 */
class RolfRisk extends \MonarcCore\Model\Entity\RolfRiskSuperclass
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
     * @ORM\ManyToMany(targetEntity="MonarcFO\Model\Entity\RolfCategory", inversedBy="rolf_categories", cascade={"persist"})
     * @ORM\JoinTable(name="rolf_risks_categories",
     *  joinColumns={@ORM\JoinColumn(name="rolf_risk_id", referencedColumnName="id")},
     *  inverseJoinColumns={@ORM\JoinColumn(name="rolf_category_id", referencedColumnName="id")}
     * )
     */
    protected $categories;

    /**
     * @var \MonarcFO\Model\Entity\RolfTag
     *
     * @ORM\ManyToMany(targetEntity="MonarcFO\Model\Entity\RolfTag", inversedBy="rolf_tags", cascade={"persist"})
     * @ORM\JoinTable(name="rolf_risks_tags",
     *  joinColumns={@ORM\JoinColumn(name="rolf_risk_id", referencedColumnName="id")},
     *  inverseJoinColumns={@ORM\JoinColumn(name="rolf_tag_id", referencedColumnName="id")}
     * )
     */
    protected $tags;
}

