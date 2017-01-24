<?php
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
    protected $filterColumns = [
        'label1', 'label2', 'label3', 'label4',
        'description1', 'description2', 'description3', 'description4',
        'code',
    ];
    protected $dependencies = ['anr', 'theme'];
    protected $anrTable;
    protected $userAnrTable;
    protected $themeTable;
    protected $instanceRiskTable;

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
            /** @var InstanceRiskTable $instanceRiskTable */
            $instanceRiskTable = $this->get('instanceRiskTable');
            $filter = [
                'anr' => $data['anr'],
                'threat' => $id,
            ];
            if ((!isset($data['forceQualification'])) || $data['forceQualification'] == 0) {
                $filter['mh'] = 1;
            }
            $instancesRisks = $instanceRiskTable->getEntityByFields($filter);

            $i = 1;
            $nbInstancesRisks = count($instancesRisks);
            foreach ($instancesRisks as $instanceRisk) {
                $instanceRisk->threatRate = $data['qualification'];
                if ((isset($data['forceQualification'])) && $data['forceQualification'] == 1) {
                    $instanceRisk->mh = 1;
                }
                $instanceRiskTable->save($instanceRisk, ($i == $nbInstancesRisks));

                $i++;
            }
        }
    }
}
