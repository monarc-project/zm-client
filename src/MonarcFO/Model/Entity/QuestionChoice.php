<?php

namespace MonarcFO\Model\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Question's choices
 *
 * @ORM\Table(name="questions_choices", indexes={
 *      @ORM\Index(name="question_id", columns={"question_id"}),
 *      @ORM\Index(name="anr_id", columns={"anr_id"}),
 * })
 * @ORM\Entity
 */
class QuestionChoice extends \MonarcCore\Model\Entity\QuestionChoiceSuperclass
{
    /**
     * @var \MonarcFO\Model\Entity\Question
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Question")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="question_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $question;

    /**
     * @var \MonarcFO\Model\Entity\Anr
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Anr", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $anr;
}

