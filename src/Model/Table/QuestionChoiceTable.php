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
use Monarc\FrontOffice\Entity\QuestionChoice;

/**
 * Class QuestionChoiceTable
 * @package Monarc\FrontOffice\Model\Table
 */
class QuestionChoiceTable extends AbstractEntityTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, QuestionChoice::class, $connectedUserService);
    }

    public function saveEntity(QuestionChoice $question, bool $flushAll = true): void
    {
        $em = $this->getDb()->getEntityManager();
        $em->persist($question);
        if ($flushAll) {
            $em->flush();
        }
    }
}
