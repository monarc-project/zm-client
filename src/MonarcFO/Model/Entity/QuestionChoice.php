<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use MonarcCore\Model\Entity\QuestionChoiceSuperclass;

/**
 * Question Choice
 *
 * @ORM\Table(name="questions_choices", indexes={
 *      @ORM\Index(name="question_id", columns={"question_id"}),
 *      @ORM\Index(name="anr_id", columns={"anr_id"}),
 * })
 * @ORM\Entity
 */
class QuestionChoice extends QuestionChoiceSuperclass
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

    /**
     * @return Question
     */
    public function getQuestion()
    {
        return $this->question;
    }

    /**
     * @param Question $question
     * @return QuestionChoice
     */
    public function setQuestion($question)
    {
        $this->question = $question;
        return $this;
    }

    /**
     * @return Anr
     */
    public function getAnr()
    {
        return $this->anr;
    }

    /**
     * @param Anr $anr
     * @return QuestionChoice
     */
    public function setAnr($anr)
    {
        $this->anr = $anr;
        return $this;
    }
}