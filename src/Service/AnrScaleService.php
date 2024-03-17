<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Entity as CoreEntity;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Table\ModelTable;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Table;

class AnrScaleService
{
    private CoreEntity\UserSuperClass $connectedUser;

    public function __construct(
        private Table\ScaleTable $scaleTable,
        private Table\InstanceRiskTable $instanceRiskTable,
        private ModelTable $modelTable,
        private Table\InstanceConsequenceTable $instanceConsequenceTable,
        private Table\ThreatTable $threatTable,
        private Table\OperationalInstanceRiskScaleTable $operationalInstanceRiskScaleTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getList(Entity\Anr $anr): array
    {
        $result = [];
        /** @var Entity\Scale[] $scales */
        $scales = $this->scaleTable->findByAnr($anr);
        $availableTypes = CoreEntity\ScaleSuperClass::getAvailableTypes();

        foreach ($scales as $scale) {
            $result[] = [
                'id' => $scale->getId(),
                'type' => $availableTypes[$scale->getType()],
                'min' => $scale->getMin(),
                'max' => $scale->getMax(),
            ];
        }

        return $result;
    }

    public function update(Entity\Anr $anr, int $id, array $data)
    {
        $this->validateIfScalesAreEditable($anr);

        /** @var Entity\Scale $scale */
        $scale = $this->scaleTable->findByIdAndAnr($id, $anr);

        $scale->setMin((int)$data['min'])
            ->setMax((int)$data['max'])
            ->setUpdater($this->connectedUser->getEmail());

        $this->scaleTable->save($scale);

        return $scale;
    }

    /**
     * Returns whether the ANR sensitive values (scales values) can NOT be changed safely.
     * It is not possible to change the scales thresholds when:
     *  - It has been explicitly disabled in the model ANR
     *  - Risks have been evaluated
     *  - Consequences have been evaluated
     *  - Threats have been evaluated
     */
    public function areScalesNotEditable(Entity\Anr $anr): bool
    {
        $areScalesUpdatable = true;
        if ($anr->getModelId() !== null) {
            /** @var CoreEntity\Model $model */
            $model = $this->modelTable->findById($anr->getModelId());
            $areScalesUpdatable = $model->areScalesUpdatable();
        }

        return !$areScalesUpdatable
            || $this->instanceRiskTable->isEvaluationStarted($anr)
            || $this->instanceConsequenceTable->isEvaluationStarted($anr)
            || $this->threatTable->isEvaluationStarted($anr)
            || $this->operationalInstanceRiskScaleTable->isEvaluationStarted($anr);
    }

    public function validateIfScalesAreEditable(Entity\Anr $anr): void
    {
        if ($this->areScalesNotEditable($anr)) {
            throw new Exception('Scales are not editable when the risks evaluation is started.', 412);
        }
    }
}
