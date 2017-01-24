<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Class AnrScaleCommentServiceFactory
 * @package MonarcFO\Service
 */
class AnrScaleCommentServiceFactory extends AbstractServiceFactory
{
    /**
     * @var array
     */
    protected $ressources = [
        'entity'=> 'MonarcFO\Model\Entity\ScaleComment',
        'table'=> 'MonarcFO\Model\Table\ScaleCommentTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'scaleTable' => 'MonarcFO\Model\Table\ScaleTable',
        'scaleImpactTypeTable' => 'MonarcFO\Model\Table\ScaleImpactTypeTable',
    ];
}
