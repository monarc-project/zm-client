<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\QuestionChoiceSuperClass;

/**
 * Question Choice
 *
 * @ORM\Table(name="questions_choices", indexes={
 *      @ORM\Index(name="question_id", columns={"question_id"}),
 *      @ORM\Index(name="anr_id", columns={"anr_id"}),
 * })
 * @ORM\Entity
 */
class QuestionChoice extends QuestionChoiceSuperClass
{
    /**
     * @var \Monarc\FrontOffice\Model\Entity\Question
     *
     * @ORM\ManyToOne(targetEntity="Monarc\FrontOffice\Model\Entity\Question")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="question_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $question;

    /**
     * @var \Monarc\FrontOffice\Model\Entity\Anr
     *
     * @ORM\ManyToOne(targetEntity="Monarc\FrontOffice\Model\Entity\Anr", cascade={"persist"})
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
