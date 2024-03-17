<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Monarc\FrontOffice\Model\DbCli;
use Monarc\Core\Model\Table\AbstractEntityTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Question;

/**
 * Class QuestionTable
 * @package Monarc\FrontOffice\Model\Table
 */
class QuestionTable extends AbstractEntityTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, Question::class, $connectedUserService);
    }

    /**
     * @return Question[]
     */
    public function findByAnr(Anr $anr): array
    {
        return $this->getRepository()
            ->createQueryBuilder('q')
            ->where('q.anr = :anr')
            ->setParameter('anr', $anr)
            ->getQuery()
            ->getResult();
    }

    public function saveEntity(Question $question, bool $flushAll = true): void
    {
        $em = $this->getDb()->getEntityManager();
        $em->persist($question);
        if ($flushAll) {
            $em->flush();
        }
    }

    public function deleteEntity(Question $question, bool $flush = true): void
    {
        $em = $this->getDb()->getEntityManager();
        $em->remove($question);
        if ($flush) {
            $em->flush();
        }
    }
}
