<?php
namespace MonarcFO\Service;

/**
 * Anr Check Started Service
 *
 * Class AnrCheckStartedService
 * @package MonarcFO\Service
 */
class AnrCheckStartedService extends \MonarcCore\Service\AbstractService
{
    protected $modelTable;
    protected $instanceRiskTable;
    protected $instanceConsequenceTable;
    protected $threatTable;
    protected $instanceRiskOpTable;

    /**
     * canChange
     *
     * @param $anr (mixed)
     * @return bool
     * @throws \Exception
     */
    public function canChange($anr)
    {
        if (is_object($anr)) {
            if (!$anr instanceof AnrSuperClass) {
                throw new \Exception('Anr missing', 412);
            }
        } elseif (is_int($anr)) {
            $anr = $this->get('table')->getEntity($anr);
        } else {
            throw new \Exception('Anr missing', 412);
        }

        $isScalesUpdatable = true;
        if ($anr->get('model')) {
            $model = $this->get('modelTable')->getEntity($anr->get('model'));
            $isScalesUpdatable = $model->get('isScalesUpdatable');
        }

        return !$this->get('instanceRiskTable')->started($anr->get('id')) &&
            !$this->get('instanceConsequenceTable')->started($anr->get('id')) &&
            !$this->get('threatTable')->started($anr->get('id')) &&
            !$this->get('instanceRiskOpTable')->started($anr->get('id')) &&
            $isScalesUpdatable;
    }
}