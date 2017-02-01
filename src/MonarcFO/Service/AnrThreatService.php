<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

use MonarcFO\Model\Table\InstanceRiskTable;

/**
 * Anr Threat Service
 *
 * Class AnrThreatService
 * @package MonarcFO\Service
 */
class AnrThreatService extends \MonarcCore\Service\AbstractService
{
    protected $dependencies = ['anr', 'theme'];
    protected $anrTable;
    protected $userAnrTable;
    protected $themeTable;
    protected $instanceRiskTable;
    protected $filterColumns = [
        'label1', 'label2', 'label3', 'label4',
        'description1', 'description2', 'description3', 'description4',
        'code',
    ];

    /**
     * Update
     *
     * @param $id
     * @param $data
     * @return mixed
     */
    public function update($id, $data)
    {
        $this->manageQualification($id, $data);

        return parent::update($id, $data);
    }

    /**
     * Patch
     *
     * @param $id
     * @param $data
     * @return mixed
     */
    public function patch($id, $data)
    {
        $this->manageQualification($id, $data);

        return parent::patch($id, $data);
    }

    /**
     * Manage Qualification
     *
     * @param $id
     * @param $data
     */
    public function manageQualification($id, $data)
    {
        if (isset($data['qualification'])) {
            $filter = [
                'anr' => $data['anr'],
                'threat' => $id,
            ];

            //if qualification is not forced, retrieve only instance risks inherited
            if ((!isset($data['forceQualification'])) || $data['forceQualification'] == 0) {
                $filter['mh'] = 1;
            }

            /** @var InstanceRiskTable $instanceRiskTable */
            $instanceRiskTable = $this->get('instanceRiskTable');
            $instancesRisks = $instanceRiskTable->getEntityByFields($filter);

            $i = 1;
            $nbInstancesRisks = count($instancesRisks);
            foreach ($instancesRisks as $instanceRisk) {
                $instanceRisk->threatRate = $data['qualification'];
                //if qualification is forced, instances risks become inherited
                if ((isset($data['forceQualification'])) && $data['forceQualification'] == 1) {
                    $instanceRisk->mh = 1;
                }
                $instanceRiskTable->save($instanceRisk, ($i == $nbInstancesRisks));
                $i++;
            }
        }
    }
}
