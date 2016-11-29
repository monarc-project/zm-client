<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

class AnrInterviewServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'table'=> '\MonarcFO\Model\Table\InterviewTable',
        'entity'=> '\MonarcFO\Model\Entity\Interview',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
    );
}
