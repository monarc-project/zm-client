<?php

namespace MonarcFO\Model\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use MonarcCore\Model\Entity\AbstractEntity;
use Zend\InputFilter\InputFilter;

/**
 * Asset
 *
 * @ORM\Table(name="assets")
 * @ORM\Entity
 */
class Asset extends \MonarcCore\Model\Entity\AssetSuperClass
{
    /**
     * @var \MonarcCore\Model\Entity\Anr
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Anr", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $anr;
}
