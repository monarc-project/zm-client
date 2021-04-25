<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\InstanceService;
use Monarc\FrontOffice\Model\Table\InstanceTable;
use Monarc\FrontOffice\Model\Table\RecommandationTable;
use Monarc\FrontOffice\Model\Table\UserAnrTable;
use Monarc\FrontOffice\Service\Traits\RecommendationsPositionsUpdateTrait;

/**
 * This class is the service that handles instances in use within an ANR. Inherits most of the behavior from its
 * Monarc\Core parent class.
 * @package Monarc\FrontOffice\Service
 */
class AnrInstanceService extends InstanceService
{
    use RecommendationsPositionsUpdateTrait;

    /** @var UserAnrTable */
    protected $userAnrTable;
    protected $themeTable;
    protected $instanceRiskTable;
    protected $instanceRiskOpTable;

    public function delete($id)
    {
        /** @var InstanceTable $instanceTable */
        $instanceTable = $this->get('table');
        $anr = $instanceTable->findById($id)->getAnr();

        parent::delete($id);

        // Reset related recommendations positions to 0.
        /** @var RecommandationTable $recommendationTable */
        $recommendationTable = $this->get('recommandationTable');
        $unlinkedRecommendations = $recommendationTable->findUnlinkedWithNotEmptyPositionByAnr($anr);
        $recommendationsToResetPositions = [];
        foreach ($unlinkedRecommendations as $unlinkedRecommendation) {
            $recommendationsToResetPositions[$unlinkedRecommendation->getUuid()] = $unlinkedRecommendation;
        }
        if (!empty($recommendationsToResetPositions)) {
            $this->resetRecommendationsPositions($anr, $recommendationsToResetPositions);
        }
    }
}
