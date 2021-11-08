<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Model\Table\ModelTable;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\InstanceConsequenceTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskTable;
use Monarc\FrontOffice\Table\OperationalInstanceRiskScaleTable;
use Monarc\FrontOffice\Model\Table\ThreatTable;

/**
 * Service that checks if an ANR has been started working on, or if it is safe to change sensitive parameters (such
 * as scales values).
 * @package Monarc\FrontOffice\Service
 */
class AnrCheckStartedService
{
    private AnrTable $anrTable;

    protected ModelTable $modelTable;

    protected InstanceRiskTable $instanceRiskTable;

    protected InstanceConsequenceTable $instanceConsequenceTable;

    protected ThreatTable $threatTable;

    protected OperationalInstanceRiskScaleTable $operationalInstanceRiskScaleTable;

    public function __construct(
        AnrTable $anrTable,
        ModelTable $modelTable,
        InstanceRiskTable $instanceRiskTable,
        InstanceConsequenceTable $instanceConsequenceTable,
        ThreatTable $threatTable,
        OperationalInstanceRiskScaleTable $operationalInstanceRiskScaleTable
    ) {
        $this->anrTable = $anrTable;
        $this->modelTable = $modelTable;
        $this->instanceRiskTable = $instanceRiskTable;
        $this->instanceConsequenceTable = $instanceConsequenceTable;
        $this->threatTable = $threatTable;
        $this->operationalInstanceRiskScaleTable = $operationalInstanceRiskScaleTable;
    }

    /**
     * Returns whether or not the ANR sensitive values (scales values) can be changed safely.
     * It is not possible to change the scales thresholds when:
     *  - It has been explicitly disabled in the model ANR
     *  - Risks have been evaluated
     *  - Consequences have been evaluated
     *  - Threats have been evaluated
     */
    public function canChange(int $anrId): bool
    {
        $anr = $this->anrTable->findById($anrId);

        $isScalesEditable = true;
        if ($anr->getModel()) {
            $model = $this->modelTable->getEntity($anr->getModel());
            $isScalesEditable = $model->get('isScalesUpdatable');
        }

        return $isScalesEditable
            && !$this->instanceRiskTable->started($anr->getId())
            && !$this->instanceConsequenceTable->started($anr->getId())
            && !$this->threatTable->started($anr->getId())
            && !$this->operationalInstanceRiskScaleTable->isRisksEvaluationStartedForAnr($anr);
    }
}
