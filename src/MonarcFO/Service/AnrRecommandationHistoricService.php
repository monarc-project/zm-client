<?php
namespace MonarcFO\Service;

use MonarcFO\Service\AbstractService;

/**
 * Anr Recommandation Historic Service
 *
 * Class AnrRecommandationHistoricService
 * @package MonarcFO\Service
 */
class AnrRecommandationHistoricService extends \MonarcCore\Service\AbstractService
{
    protected $dependencies = ['anr'];
    protected $userAnrTable;
}