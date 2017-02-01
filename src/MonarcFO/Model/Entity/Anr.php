<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use MonarcCore\Model\Entity\AnrSuperClass;

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
     * @return int
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * @param int $language
     * @return Anr
     */
    public function setLanguage($language)
    {
        $this->language = $language;
        return $this;
    }
}