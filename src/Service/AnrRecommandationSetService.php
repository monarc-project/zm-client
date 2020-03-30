<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\OptimisticLockException;
use Monarc\Core\Service\AbstractService;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\RecommandationSetTable;
use Monarc\FrontOffice\Model\Table\RecommandationTable;
use Monarc\FrontOffice\Service\Traits\RecommendationsPositionsUpdateTrait;

/**
 * AnrRecommandationSet Service
 *
 * Class AnrRecommandationSetService
 * @package Monarc\FrontOffice\Service
 */
class AnrRecommandationSetService extends AbstractService
{
    use RecommendationsPositionsUpdateTrait;

    protected $dependencies = ['anr'];
    protected $filterColumns = ['uuid', 'label1', 'label2', 'label3', 'label4'];
    protected $userAnrTable;
    protected $selfCoreService;

    /** @var AnrTable */
    protected $anrTable;

    /** @var RecommandationTable */
    protected $recommandationTable;

    /**
     * @inheritdoc
     */
    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null)
    {
        $data = $this->get('table')->fetchAllFiltered(
            array_keys($this->get('entity')->getJsonArray()),
            1,
            0,
            $this->parseFrontendOrder($order),
            $this->parseFrontendFilter($filter, $this->filterColumns),
            $filterAnd
        );

        return $data;
    }

    /**
     * @param array $id
     *
     * @return bool
     *
     * @throws EntityNotFoundException
     * @throws OptimisticLockException
     */
    public function delete($id)
    {
        /** @var RecommandationSetTable $recommendationSetTable */
        $recommendationSetTable = $this->get('table');
        /** @var AnrTable $anrTable */
        $anrTable = $this->get('anrTable');
        $anr = $anrTable->findById($id['anr']);
        $recommendationSet = $recommendationSetTable->findByAnrAndUuid($anr, $id['uuid']);

        $recommendationsToResetPositions = [];
        foreach ($recommendationSet->getRecommandations() as $recommendation) {
            if (!$recommendation->isPositionEmpty()) {
                $recommendationsToResetPositions[$recommendation->getUuid()] = $recommendation;
            }
        }
        if (!empty($recommendationsToResetPositions)) {
            $this->resetRecommendationsPositions($anr, $recommendationsToResetPositions);
        }

        $recommendationSetTable->deleteEntity($recommendationSet);

        return true;
    }
}
