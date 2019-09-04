<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\FrontOffice\Service\AbstractService;
use Monarc\FrontOffice\Model\Table\RecommandationHistoricTable;
use Monarc\FrontOffice\Model\Table\RecommandationTable;



/**
 * This class is the service that handles the recommendation events history. This is a simple CRUD service.
 * @package Monarc\FrontOffice\Service
 */
class AnrRecommandationHistoricService extends \Monarc\Core\Service\AbstractService
{
    protected $dependencies = ['anr'];
    protected $userAnrTable;
    protected $recommandationHistoricTable;
    protected $recommandationHistoricEntity;

    /**
     * Get Delivery Recommandations Risks
     *
     * @param $anrId
     * @return array|bool
     */

    public function getDeliveryRecommandationsHistory($anrId) {
        /** @var RecommandationHistoricTable $table */
        $table = $this->get('table');
        $recoRecords = $table->getEntityByFields(['anr' => $anrId], ['id' => 'ASC']);

        return $recoRecords;
    }
}
