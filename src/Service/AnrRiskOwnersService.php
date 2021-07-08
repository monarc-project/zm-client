<?php

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractService;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOwner;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskOwnerTable;


class AnrRiskOwnersService extends AbstractService
{

    protected $anrTable;
    protected $table;
    
    /**
     * Computes and returns the list of owners for the entire ANR.
     * @param int $anrId The ANR ID
     * @param array $params An array of fields to filter
     * @param bool $count If true, only the number of risks will be returned
     * @return int|array If $count is true, the number of owners. Otherwise, an array of owners.
     */
    public function getOwners($anrId, $params = [], $count = false)
    {
        file_put_contents('php://stderr', print_r('getOwners', TRUE).PHP_EOL);
        $anr = $this->get('anrTable')->getEntity($anrId); // check that the ARN exists
        return $this->get('table')->getRisks($anrId, $params);
    }
}
