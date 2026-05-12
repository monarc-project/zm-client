<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\InputFormatter\FormattedInputParams;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\ReassessmentTriggerService as CoreReassessmentTriggerService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\ReassessmentTrigger;
use Monarc\FrontOffice\Table\ReassessmentTriggerTable;

class ReassessmentTriggerService
{
    private string $connectedUserEmail;

    public function __construct(
        private ReassessmentTriggerTable $reassessmentTriggerTable,
        private CoreReassessmentTriggerService $coreReassessmentTriggerService,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUserEmail = $connectedUserService->getConnectedUser()->getEmail();
    }

    /**
     * @return ReassessmentTrigger[]
     */
    public function getList(FormattedInputParams $params): array
    {
        return $this->reassessmentTriggerTable->findByParams($params);
    }

    public function getCount(FormattedInputParams $params): int
    {
        return $this->reassessmentTriggerTable->countByParams($params, 'id');
    }

    public function get(Anr $anr, int $id): ReassessmentTrigger
    {
        /** @var ReassessmentTrigger $reassessmentTrigger */
        $reassessmentTrigger = $this->reassessmentTriggerTable->findByIdAndAnr($id, $anr);

        return $reassessmentTrigger;
    }

    public function create(Anr $anr, array $data, bool $saveInDb = true): ReassessmentTrigger
    {
        $reassessmentTrigger = (new ReassessmentTrigger())
            ->setAnr($anr)
            ->setTriggerType($data['triggerType'] ?? null)
            ->setDescription(trim((string)$data['description']))
            ->setIsActive((bool)($data['isActive'] ?? true))
            ->setCreator($this->connectedUserEmail);

        $this->applyCreatePosition($reassessmentTrigger, $data);
        $this->reassessmentTriggerTable->save($reassessmentTrigger, $saveInDb);

        return $reassessmentTrigger;
    }

    public function update(Anr $anr, int $id, array $data): ReassessmentTrigger
    {
        $reassessmentTrigger = $this->get($anr, $id);

        if (!empty($data['triggerType'])) {
            $reassessmentTrigger->setTriggerType($data['triggerType']);
        }
        if (!empty($data['description'])) {
            $reassessmentTrigger->setDescription(trim((string)$data['description']));
        }
        if (isset($data['isActive'])) {
            $reassessmentTrigger->setIsActive((bool)$data['isActive']);
        }

        if (isset($data['position']) && (int)$data['position'] !== $reassessmentTrigger->getPosition()) {
            $this->applyUpdatedPosition($reassessmentTrigger, (int)$data['position']);
        }

        $reassessmentTrigger->setUpdater($this->connectedUserEmail);

        $this->reassessmentTriggerTable->save($reassessmentTrigger);

        return $reassessmentTrigger;
    }

    public function delete(Anr $anr, int $id): void
    {
        $reassessmentTrigger = $this->get($anr, $id);
        $this->reassessmentTriggerTable->incrementPositions(
            $reassessmentTrigger->getPosition() + 1,
            -1,
            -1,
            ['anr' => $anr],
            $this->connectedUserEmail
        );
        $this->reassessmentTriggerTable->remove($reassessmentTrigger);
    }

    public function duplicateFromSourceAnr(Anr $sourceAnr, Anr $newAnr): void
    {
        foreach ($this->reassessmentTriggerTable->findByAnrOrderedByPosition($sourceAnr) as $sourceTrigger) {
            $this->reassessmentTriggerTable->save(
                (new ReassessmentTrigger())
                    ->setAnr($newAnr)
                    ->setTriggerType($sourceTrigger->getTriggerType())
                    ->setDescription($sourceTrigger->getDescription())
                    ->setIsActive($sourceTrigger->isActive())
                    ->setPosition($sourceTrigger->getPosition())
                    ->setCreator($this->connectedUserEmail),
                false
            );
        }
    }

    public function duplicateDefaultTriggersToAnr(Anr $anr): void
    {
        foreach ($this->coreReassessmentTriggerService->getSelectionData($anr->getLanguageCode()) as $defaultTrigger) {
            $this->reassessmentTriggerTable->save(
                (new ReassessmentTrigger())
                    ->setAnr($anr)
                    ->setTriggerType($defaultTrigger['triggerType'])
                    ->setDescription($defaultTrigger['description'])
                    ->setIsActive((bool)$defaultTrigger['isActive'])
                    ->setPosition((int)$defaultTrigger['position'])
                    ->setCreator($this->connectedUserEmail),
                false
            );
        }
    }

    public function processForImport(Anr $anr, array $reassessmentTriggersData, bool $isMerge): void
    {
        $existingTriggers = $this->reassessmentTriggerTable->findByAnrOrderedByPosition($anr);
        $position = count($existingTriggers) + 1;
        if ($isMerge) {
            $existingTriggersByType = [];
            foreach ($existingTriggers as $trigger) {
                if ($trigger->getTriggerType() !== 'Other') {
                    $existingTriggersByType[$trigger->getTriggerType()] = $trigger;
                }
            }
            foreach ($reassessmentTriggersData as $triggerData) {
                if (empty(trim($triggerData['triggerType'] ?? '')) || empty(trim($triggerData['description'] ?? ''))) {
                    continue;
                }

                $triggerType = trim($triggerData['triggerType']);
                if (isset($existingTriggersByType[$triggerType])) {
                    $existingTrigger = $existingTriggersByType[$triggerType];
                    if ($existingTrigger->getDescription() !== $triggerData['description']
                        || $existingTrigger->isActive() !== ($triggerData['isActive'] ?? true)) {
                        $existingTrigger->setDescription($triggerData['description'])
                            ->setIsActive((bool)($triggerData['isActive'] ?? true))
                            ->setUpdater($this->connectedUserEmail);
                        $this->reassessmentTriggerTable->save($existingTrigger, false);
                    }
                } else {
                    $this->create($anr, [
                        'triggerType' => $triggerType,
                        'description' => $triggerData['description'],
                        'isActive' => $triggerData['isActive'] ?? true,
                        'position' => $position++,
                    ], false);
                }
            }
        } else {
            foreach ($reassessmentTriggersData as $triggerData) {
                if (trim(($triggerData['triggerType'] ?? '')) !== ''
                    && trim(($triggerData['description'] ?? '')) !== ''
                ) {
                    $this->create($anr, [
                        'triggerType' => $triggerData['triggerType'],
                        'description' => $triggerData['description'],
                        'isActive' => $triggerData['isActive'] ?? true,
                        'position' => $position++,
                    ], false);
                }
            }
        }
    }

    private function applyCreatePosition(ReassessmentTrigger $reassessmentTrigger, array $data): void
    {
        if (!empty($data['position'])) {
            $reassessmentTrigger->setPosition((int)$data['position']);
        } else {
            $maxPosition = $this->reassessmentTriggerTable->findMaxPosition(
                $reassessmentTrigger->getImplicitPositionRelationsValues()
            );
            $reassessmentTrigger->setPosition($maxPosition + 1);
        }
    }

    private function applyUpdatedPosition(ReassessmentTrigger $reassessmentTrigger, int $newPosition): void
    {
        $oldPosition = $reassessmentTrigger->getPosition();
        $params = $reassessmentTrigger->getImplicitPositionRelationsValues();
        
        if ($newPosition < $oldPosition) {
            $this->reassessmentTriggerTable
                ->incrementPositions($newPosition, $oldPosition, 1, $params, $this->connectedUserEmail);
        } else {
            $this->reassessmentTriggerTable
                ->incrementPositions($oldPosition, $newPosition, -1, $params, $this->connectedUserEmail);
        }

        $reassessmentTrigger->setPosition($newPosition);
    }
}
