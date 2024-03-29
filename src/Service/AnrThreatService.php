<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractService;
use Monarc\FrontOffice\Model\Table\InstanceRiskTable;

/**
 * This class is the service that handles threats within an ANR.
 * @package Monarc\FrontOffice\Service
 */
class AnrThreatService extends AbstractService
{
    protected $dependencies = ['anr', 'theme'];
    protected $anrTable;
    protected $userAnrTable;
    protected $themeTable;
    protected $instanceRiskTable;
    protected $instanceRiskService;
    protected $filterColumns = [
        'label1', 'label2', 'label3', 'label4',
        'description1', 'description2', 'description3', 'description4',
        'code',
    ];

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        $this->manageQualification($id, $data);
        return parent::update($id, $data);
    }

    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        $this->manageQualification($id, $data);
        return parent::patch($id, $data);
    }

    /**
     * Updates the qualifications for the specified threat whenever they are created or updated. This noticeably
     * handles qualification values inheritance.
     *
     * @param int $id The threat ID
     * @param array $data The qualification data
     */
    public function manageQualification($id, $data)
    {
        if (isset($data['qualification'])) {
            $filter = [
                'anr' => $data['anr'],
                'threat' => $id,
            ];

            // If qualification is not forced, retrieve only inherited instance risksx
            if (empty($data['forceQualification'])) {
                $filter['mh'] = 1;
            }

            /** @var InstanceRiskTable $instanceRiskTable */
            $instanceRiskTable = $this->get('instanceRiskTable');
            $instancesRisks = $instanceRiskTable->getEntityByFields($filter);

            /** @var AnrInstanceRiskService $instanceRiskService */
            $instanceRiskService = $this->get('instanceRiskService');

            foreach ($instancesRisks as $i => $instanceRisk) {
                $instanceRisk->threatRate = $data['qualification'];

                // If qualification is forced, instances risks become inherited
                if (!empty($data['forceQualification'])) {
                    $instanceRisk->mh = 1;
                }

                $instanceRiskTable->saveEntity($instanceRisk, false);

                $instanceRiskService->updateRisks($instanceRisk);
                $instanceRiskService->updateInstanceRiskRecommendationsPositions($instanceRisk);
            }

            $instanceRiskTable->getDb()->flush();
        }
    }
}
