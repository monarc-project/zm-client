<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Table\ModelTable;
use Monarc\FrontOffice\Model\Table\InstanceConsequenceTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskOpTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskTable;
use Monarc\FrontOffice\Model\Table\ThreatTable;

/**
 * Service that checks if an ANR has been started working on, or if it is safe to change sensitive parameters (such
 * as scales values).
 * @package Monarc\FrontOffice\Service
 */
class AnrCheckStartedService extends \Monarc\Core\Service\AbstractService
{
    /** @var ModelTable */
    protected $modelTable;
    /** @var InstanceRiskTable */
    protected $instanceRiskTable;
    /** @var InstanceConsequenceTable */
    protected $instanceConsequenceTable;
    /** @var ThreatTable */
    protected $threatTable;
    /** @var InstanceRiskOpTable */
    protected $instanceRiskOpTable;

    /**
     * Returns whether or not the ANR sensitive values (scales values) can be changed safely. It is not possible to
     * change the scales thresholds when:
     *  - It has been explicitly disabled in the model ANR
     *  - Risks have been evaluated
     *  - Consequences have been evaluated
     *  - Threats have been evaluated
     * @param \Monarc\FrontOffice\Model\Entity\Anr|array|int $anr The ANR entity, data array, or ID
     * @return bool True if the ANR sensitive values can be safely edited, false otherwise
     * @throws \Monarc\Core\Exception\Exception If the ANR in parameter is invalid
     */
    public function canChange($anr)
    {
        if (is_object($anr)) {
            if (!$anr instanceof AnrSuperClass) {
                throw new \Monarc\Core\Exception\Exception('Anr missing', 412);
            }
        } elseif (is_int($anr)) {
            $anr = $this->get('table')->getEntity($anr);
        } else {
            throw new \Monarc\Core\Exception\Exception('Anr missing', 412);
        }

        $isScalesUpdatable = true;
        if ($anr->get('model')) {
            $model = $this->modelTable->getEntity($anr->get('model'));
            $isScalesUpdatable = $model->get('isScalesUpdatable');
        }

        return !$this->instanceRiskTable->started($anr->get('id')) &&
            !$this->instanceConsequenceTable->started($anr->get('id')) &&
            !$this->threatTable->started($anr->get('id')) &&
            !$this->instanceRiskOpTable->started($anr->get('id')) &&
            $isScalesUpdatable;
    }
}
