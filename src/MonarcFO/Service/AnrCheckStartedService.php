<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

use MonarcCore\Model\Entity\AnrSuperClass;
use MonarcCore\Model\Table\ModelTable;
use MonarcFO\Model\Table\InstanceConsequenceTable;
use MonarcFO\Model\Table\InstanceRiskOpTable;
use MonarcFO\Model\Table\InstanceRiskTable;
use MonarcFO\Model\Table\ThreatTable;

/**
 * Service that checks if an ANR has been started working on, or if it is safe to change sensitive parameters (such
 * as scales values).
 * @package MonarcFO\Service
 */
class AnrCheckStartedService extends \MonarcCore\Service\AbstractService
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
     * @param \MonarcFO\Model\Entity\Anr|array|int $anr The ANR entity, data array, or ID
     * @return bool True if the ANR sensitive values can be safely edited, false otherwise
     * @throws \MonarcCore\Exception\Exception If the ANR in parameter is invalid
     */
    public function canChange($anr)
    {
        if (is_object($anr)) {
            if (!$anr instanceof AnrSuperClass) {
                throw new \MonarcCore\Exception\Exception('Anr missing', 412);
            }
        } elseif (is_int($anr)) {
            $anr = $this->get('table')->getEntity($anr);
        } else {
            throw new \MonarcCore\Exception\Exception('Anr missing', 412);
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