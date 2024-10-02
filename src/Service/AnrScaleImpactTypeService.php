<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Entity\ScaleImpactTypeSuperClass;
use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Table;

class AnrScaleImpactTypeService
{
    private UserSuperClass $connectedUser;

    public function __construct(
        private Table\ScaleImpactTypeTable $scaleImpactTypeTable,
        private Table\ScaleTable $scaleTable,
        private Table\InstanceTable $instanceTable,
        private AnrInstanceConsequenceService $instanceConsequenceService,
        private AnrScaleService $anrScaleService,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getList(Entity\Anr $anr): array
    {
        $result = [];
        $scaleImpactTypesShortcuts = ScaleImpactTypeSuperClass::getScaleImpactTypesShortcuts();
        /** @var Entity\ScaleImpactType $scaleImpactType */
        foreach ($this->scaleImpactTypeTable->findByAnr($anr) as $scaleImpactType) {
            $result[] = array_merge([
                'id' => $scaleImpactType->getId(),
                'isHidden' => (int)$scaleImpactType->isHidden(),
                'isSys' => (int)$scaleImpactType->isSys(),
                'type' => $scaleImpactTypesShortcuts[$scaleImpactType->getType()] ?? 'CUS',
            ], $scaleImpactType->getLabels());
        }

        return $result;
    }

    public function create(Entity\Anr $anr, array $data, bool $saveInTheDb = true): Entity\ScaleImpactType
    {
        $scaleImpactType = (new Entity\ScaleImpactType())
            ->setAnr($anr)
            ->setScale(
                $data['scale'] instanceof Entity\Scale ? $data['scale'] : $this->scaleTable->findById($data['scale'])
            )
            ->setLabels($data['labels'] ?? $data)
            ->setType($data['type'] ?? $this->scaleImpactTypeTable->findMaxTypeValueByAnr($anr) + 1)
            ->setCreator($this->connectedUser->getEmail());
        if (isset($data['isHidden'])) {
            $scaleImpactType->setIsHidden((bool)$data['isHidden']);
        }

        /* Create InstanceConsequence for each instance of the current anr. */
        /** @var Entity\Instance $instance */
        foreach ($this->instanceTable->findByAnr($scaleImpactType->getAnr()) as $instance) {
            $this->instanceConsequenceService->createInstanceConsequence(
                $instance,
                $scaleImpactType,
                $scaleImpactType->isHidden()
            );
        }

        $this->scaleImpactTypeTable->save($scaleImpactType, $saveInTheDb);

        return $scaleImpactType;
    }

    /**
     * Hide/show or change label of scales impact types on the Evaluation scales page.
     */
    public function patch(Entity\Anr $anr, int $id, array $data): Entity\ScaleImpactType
    {
        /** @var Entity\ScaleImpactType $scaleImpactType */
        $scaleImpactType = $this->scaleImpactTypeTable->findByIdAndAnr($id, $anr);

        if (isset($data['isHidden']) && (bool)$data['isHidden'] !== $scaleImpactType->isHidden()) {
            /* It's not allowed to change visibility if analysis evaluation is started. */
            $this->anrScaleService->validateIfScalesAreEditable($anr);

            $scaleImpactType->setIsHidden((bool)$data['isHidden']);
            $this->instanceConsequenceService->updateConsequencesByScaleImpactType(
                $scaleImpactType,
                (bool)$data['isHidden']
            );
        }

        $scaleImpactType->setLabels($data)->setUpdater($this->connectedUser->getEmail());

        $this->scaleImpactTypeTable->save($scaleImpactType);

        return $scaleImpactType;
    }


    public function delete(Entity\Anr $anr, int $id): void
    {
        /* It's not allowed to delete the scale if analysis evaluation is started. */
        $this->anrScaleService->validateIfScalesAreEditable($anr);

        /** @var Entity\ScaleImpactType $scaleImpactType */
        $scaleImpactType = $this->scaleImpactTypeTable->findByIdAndAnr($id, $anr);
        if ($scaleImpactType->isSys()) {
            throw new Exception('Default Scale Impact Types can\'t be removed.', '403');
        }

        $this->scaleImpactTypeTable->remove($scaleImpactType);
    }
}
