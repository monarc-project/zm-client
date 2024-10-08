<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Doctrine\ORM\EntityNotFoundException;
use Monarc\Core\Model\Table\AbstractEntityTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\FrontOffice\Entity\Record;

class RecordTable extends AbstractEntityTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, Record::class, $connectedUserService);
    }

    public function findById(int $id): Record
    {
        $record = $this->getRepository()->find($id);
        if ($record === null) {
            throw EntityNotFoundException::fromClassNameAndIdentifier(\get_class($this), [$id]);
        }

        return $record;
    }

    /**
     * @return Record[]
     */
    public function findByAnr(Anr $anr): array
    {
        return $this->getRepository()
            ->createQueryBuilder('r')
            ->where('r.anr = :anr')
            ->setParameter('anr', $anr)
            ->getQuery()
            ->getResult();
    }
}
