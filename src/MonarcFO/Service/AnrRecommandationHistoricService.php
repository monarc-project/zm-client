<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

use MonarcFO\Service\AbstractService;
use MonarcFO\Model\Table\RecommandationHistoricTable;
use MonarcFO\Model\Table\RecommandationTable;



/**
 * This class is the service that handles the recommendation events history. This is a simple CRUD service.
 * @package MonarcFO\Service
 */
class AnrRecommandationHistoricService extends \MonarcCore\Service\AbstractService
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
