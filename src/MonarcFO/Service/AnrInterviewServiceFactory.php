<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Anr Interview Service Factory
 *
 * Class AnrInterviewServiceFactory
 * @package MonarcFO\Service
 */
class AnrInterviewServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => '\MonarcFO\Model\Table\InterviewTable',
        'entity' => '\MonarcFO\Model\Entity\Interview',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
    ];
}