<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\EntityManager;
use Monarc\Core\Table\AbstractTable;
use Monarc\FrontOffice\Entity\Question;
use Monarc\FrontOffice\Entity\QuestionChoice;

class QuestionChoiceTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, string $entityName = QuestionChoice::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    public function findChoicesOfQuestion(Question $question): array
    {
        return $this->getRepository()->findBy(['question' => $question], ['position' => 'ASC', 'id' => 'ASC']);
    }
}
