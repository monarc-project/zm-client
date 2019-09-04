<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\AnrSuperClass;

/**
 * Anr
 *
 * @ORM\Table(name="anrs")
 * @ORM\Entity
 */
class Anr extends AnrSuperClass
{
    /**
     * @var integer
     *
     * @ORM\Column(name="language", type="integer", options={"unsigned":true, "default":1})
     */
    protected $language = 1;

    /**
     * @var smallint
     *
     * @ORM\Column(name="model_id", type="integer", options={"unsigned":true, "default":0})
     */
    protected $model = '0';

    /**
     * @var smallint
     *
     * @ORM\Column(name="model_impacts", type="smallint", options={"unsigned":true, "default":0})
     */
    protected $modelImpacts = '0';

    /**
     * @var smallint
     *
     * @ORM\Column(name="cache_model_is_scales_updatable", type="smallint", options={"unsigned":true, "default":0})
     */
    protected $cacheModelIsScalesUpdatable = '0';

    /**
     * @var \Monarc\FrontOffice\Model\Entity\Referential
     *
     * @ORM\OneToMany(targetEntity="Monarc\FrontOffice\Model\Entity\Referential", mappedBy="anr", cascade={"persist"})
     */
    protected $referentials;

    /**
    * @param Referential $referentials
    */
    public function setReferentials($referentials)
    {
        $this->referentials = $referentials;
    }

    /**
    * @return Referential
    */
    public function getReferentials()
    {
        return $this->referentials;
    }

}
