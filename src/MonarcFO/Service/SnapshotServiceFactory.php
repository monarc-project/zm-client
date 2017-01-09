<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

class SnapshotServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'entity'=> 'MonarcFO\Model\Entity\Snapshot',
        'table'=> 'MonarcFO\Model\Table\SnapshotTable',
        'anrTable'=> 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'anrService'=> 'MonarcFO\Service\AnrService',
    );
}
