<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use MonarcCore\Model\Entity\AbstractEntity;
//use MonarcCore\Model\TAble\AbstractEntityTAble;


/**
 * Soa
 *
 * @ORM\Table(name="category", indexes={
 *      @ORM\Index(name="measure", columns={"measure_id"}),
 *      @ORM\Index(name="anr", columns={"anr_id"})

 * })
 * @ORM\Entity
 */
class Category extends AbstractEntity
{



    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;



    /**
     * @var text
     *
     * @ORM\Column(name="label1", type="text", length=255, nullable=true)
     */
    protected $label1 ;


    /**
     * @var text
     *
     * @ORM\Column(name="label2", type="text", length=255, nullable=true)
     */
    protected $label2 ;

    /**
     * @var text
     *
     * @ORM\Column(name="label3", type="text", length=255, nullable=true)
     */
    protected $label3 ;

    /**
     * @var text
     *
     * @ORM\Column(name="label4", type="text", length=255, nullable=true)
     */
    protected $label4 ;


          /**
           * @return TEXT_LONG
           */
          public function getlabel1()
          {
              return $this->label1;
          }

          /**
           * @param TEXT_LONG $label1
           *
           */
          public function setlabel1($label1)
          {
              $this->label1 = $label1;
          }

          /**
           * @return TEXT_LONG
           */
          public function getlabel2()
          {
              return $this->label;
          }

          /**
           * @param TEXT_LONG $label2
           *
           */
          public function setlabel2($label2)
          {
              $this->label2 = $label2;
          }

          /**
           * @return TEXT_LONG
           */
          public function getlabel3()
          {
              return $this->label3;
          }

          /**
           * @param TEXT_LONG $label3
           *
           */
          public function setlabel3($label3)
          {
              $this->label3 = $label3;
          }

          /**
           * @return TEXT_LONG
           */
          public function getlabel4()
          {
              return $this->label4;
          }

          /**
           * @param TEXT_LONG $label4
           *
           */
          public function setlabel4($label4)
          {
              $this->label4 = $label4;
          }

}
