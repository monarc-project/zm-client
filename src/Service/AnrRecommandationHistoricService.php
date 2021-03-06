<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractService;
use Monarc\FrontOffice\Model\Table\RecommendationHistoricTable;

/**
 * TODO: This service is not really needed. Can be used the table class directly in the controller and AnrService.
 */
class AnrRecommandationHistoricService extends AbstractService
{
    protected $dependencies = ['anr'];
    protected $userAnrTable;
    protected $recommandationHistoricTable;
    protected $recommandationHistoricEntity;
}
