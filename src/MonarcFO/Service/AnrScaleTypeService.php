<?php
namespace MonarcFO\Service;

/**
 * Anr Scale Type Service
 *
 * Class AnrScaleTypeService
 * @package MonarcFO\Service
 */
class AnrScaleTypeService extends \MonarcCore\Service\AbstractService
{
	protected $filterColumns = [];
    protected $dependencies = ['anr', 'scale'];

    protected $anrTable;
    protected $scaleTable;
    protected $types = [
        1 => 'C',
        2 => 'I',
        3 => 'D',
        4 => 'R',
        5 => 'O',
        6 => 'L',
        7 => 'F',
        8 => 'P',
        9 => 'CUS',
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

        $scales = parent::getList($page, $limit, $order, $filter, $filterAnd);

        $types = $this->getTypes();

        foreach ($scales as $key => $scale) {
            if(isset($scale['type'])){
                $scales[$key]['type'] = $types[$scale['type']];
            }
        }

        return $scales;
    }
}
