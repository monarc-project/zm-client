<?php
namespace MonarcFO\Service;
use MonarcFO\Model\Entity\Scale;

/**
 * Anr Scale Service
 *
 * Class AnrScaleService
 * @package MonarcFO\Service
 */
class AnrScaleService extends \MonarcCore\Service\AbstractService
{
	protected $filterColumns = array( );

    protected $anrTable;
    protected $AnrCheckStartedService;
    protected $dependencies = ['anr'];

    protected $types = [
        Scale::TYPE_IMPACT => 'impact',
        Scale::TYPE_THREAT => 'threat',
        Scale::TYPE_VULNERABILITY => 'vulnerability',
    ];

    /**
     * @return array
     */
    public function getTypes()
    {
        return $this->types;
    }

    /**
     * Get List
     *
     * @param int $page
     * @param int $limit
     * @param null $order
     * @param null $filter
     * @return mixed
     */
    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null){
        $anrId = isset($filterAnd['anr'])?$filterAnd['anr']:null;

        $scales = parent::getList($page, $limit, $order, $filter, $filterAnd);

        $types = $this->getTypes();

        foreach ($scales as $key => $scale) {
            $scales[$key]['type'] = $types[$scale['type']];
        }

        return [$scales,$this->get('AnrCheckStartedService')->canChange($anrId)];
    }
}
