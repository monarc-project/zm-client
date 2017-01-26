<?php
namespace MonarcFO\Service;

/**
 * Anr Measure Service
 *
 * Class AnrMeasureService
 * @package MonarcFO\Service
 */
class AnrMeasureService extends \MonarcCore\Service\AbstractService
{
    protected $anrTable;
    protected $userAnrTable;
    protected $dependencies = ['anr'];
    protected $filterColumns = [
        'description1', 'description2', 'description3', 'description4',
        'code', 'status'
    ];

    /**
     * Get List
     *
     * @param int $page
     * @param int $limit
     * @param null $order
     * @param null $filter
     * @return mixed
     */
    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null)
    {
        // Do a soft limit, as we need to manually parse the codes to order them.
        $data = $this->get('table')->fetchAllFiltered(
            array_keys($this->get('entity')->getJsonArray()),
            1,
            0,
            $this->parseFrontendOrder($order),
            $this->parseFrontendFilter($filter, $this->filterColumns),
            $filterAnd
        );

        if ($order == "code" || $order == "-code") {
            $desc = ($order == "-code");

            // Codes might be in xx.xx.xx format which need a numerical sorting instead of an alphabetical one
            $re = '/(([0-9]+)(\.)?)+/m';
            usort($data, function ($a, $b) use ($re, $desc) {
                $a_match = (preg_match($re, $a['code']) > 0);
                $b_match = (preg_match($re, $b['code']) > 0);

                if ($a_match && $b_match) {
                    $a_values = explode('.', $a['code']);
                    $b_values = explode('.', $b['code']);

                    if (count($a_values) < count($b_values)) {
                        return $desc ? 1 : -1;
                    } else if (count($a_values) > count($b_values)) {
                        return $desc ? -1 : 1;
                    } else {
                        for ($i = 0; $i < count($a_values); ++$i) {
                            if ($a_values[$i] != $b_values[$i]) {
                                return $desc ? (intval($b_values[$i]) - intval($a_values[$i])) : (intval($a_values[$i]) - intval($b_values[$i]));
                            }
                        }

                        // If we reach here, all values are equal
                        return 0;
                    }


                } else if ($a_match && !$b_match) {
                    return $desc ? 1 : -1;
                } else if (!$a_match && $b_match) {
                    return $desc ? -1 : 1;
                } else {
                    return $desc ? intval($b_match) - intval($a_match) : strcmp($a_match, $b_match);
                }
            });
        }

        return array_slice($data, ($page - 1) * $limit, $limit, false);
    }
}