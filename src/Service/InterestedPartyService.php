<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\InterestedParty;
use Monarc\FrontOffice\Table\InterestedPartyTable;

class InterestedPartyService
{
    private string $connectedUserEmail;

    public function __construct(
        private InterestedPartyTable $interestedPartyTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUserEmail = $connectedUserService->getConnectedUser()->getEmail();
    }

    /**
     * @return InterestedParty[]
     */
    public function getList(Anr $anr): array
    {
        return $this->interestedPartyTable->findByAnrOrderedByPosition($anr);
    }

    public function getCount(Anr $anr): int
    {
        return count($this->getList($anr));
    }

    public function get(Anr $anr, int $id): InterestedParty
    {
        /** @var InterestedParty $interestedParty */
        $interestedParty = $this->interestedPartyTable->findByIdAndAnr($id, $anr);

        return $interestedParty;
    }

    public function create(Anr $anr, array $data, bool $saveInDb = true): InterestedParty
    {
        $interestedParty = (new InterestedParty())
            ->setAnr($anr)
            ->setStakeholder($this->normalizeText($data['stakeholder'] ?? null))
            ->setRequirement($this->normalizeText($data['requirement'] ?? null))
            ->setCreator($this->connectedUserEmail);

        $this->applyCreatePosition($interestedParty, $data);
        $this->interestedPartyTable->save($interestedParty, $saveInDb);

        return $interestedParty;
    }

    public function update(Anr $anr, int $id, array $data): InterestedParty
    {
        $interestedParty = $this->get($anr, $id);

        if (array_key_exists('stakeholder', $data)) {
            $interestedParty->setStakeholder($this->normalizeText($data['stakeholder']));
        }
        if (array_key_exists('requirement', $data)) {
            $interestedParty->setRequirement($this->normalizeText($data['requirement']));
        }
        if (isset($data['position']) && (int)$data['position'] !== $interestedParty->getPosition()) {
            $this->applyUpdatedPosition($interestedParty, (int)$data['position']);
        }

        $interestedParty->setUpdater($this->connectedUserEmail);
        $this->interestedPartyTable->save($interestedParty);

        return $interestedParty;
    }

    public function delete(Anr $anr, int $id): void
    {
        $interestedParty = $this->get($anr, $id);
        $this->interestedPartyTable->incrementPositions(
            $interestedParty->getPosition() + 1,
            -1,
            -1,
            ['anr' => $anr],
            $this->connectedUserEmail
        );
        $this->interestedPartyTable->remove($interestedParty);
    }

    public function duplicateFromSourceAnr(Anr $sourceAnr, Anr $newAnr): void
    {
        foreach ($this->interestedPartyTable->findByAnrOrderedByPosition($sourceAnr) as $sourceInterestedParty) {
            $this->interestedPartyTable->save(
                (new InterestedParty())
                    ->setAnr($newAnr)
                    ->setStakeholder($sourceInterestedParty->getStakeholder())
                    ->setRequirement($sourceInterestedParty->getRequirement())
                    ->setPosition($sourceInterestedParty->getPosition())
                    ->setCreator($this->connectedUserEmail),
                false
            );
        }
    }

    public function processForImport(Anr $anr, array $interestedPartiesData, bool $isMerge): void
    {
        $existingInterestedParties = $this->interestedPartyTable->findByAnrOrderedByPosition($anr);
        $position = count($existingInterestedParties) + 1;

        if ($isMerge) {
            $existingInterestedPartiesBySignature = [];
            foreach ($existingInterestedParties as $interestedParty) {
                $existingInterestedPartiesBySignature[$this->buildSignature(
                    $interestedParty->getStakeholder(),
                    $interestedParty->getRequirement()
                )] = $interestedParty;
            }

            foreach ($interestedPartiesData as $interestedPartyData) {
                $stakeholder = $this->normalizeText($interestedPartyData['stakeholder'] ?? null);
                $requirement = $this->normalizeText($interestedPartyData['requirement'] ?? null);
                if ($stakeholder === '' && $requirement === '') {
                    continue;
                }

                $signature = $this->buildSignature($stakeholder, $requirement);
                if (isset($existingInterestedPartiesBySignature[$signature])) {
                    continue;
                }

                $this->create($anr, [
                    'stakeholder' => $stakeholder,
                    'requirement' => $requirement,
                    'position' => $position++,
                ], false);
            }

            return;
        }

        foreach ($interestedPartiesData as $interestedPartyData) {
            $stakeholder = $this->normalizeText($interestedPartyData['stakeholder'] ?? null);
            $requirement = $this->normalizeText($interestedPartyData['requirement'] ?? null);
            if ($stakeholder === '' && $requirement === '') {
                continue;
            }

            $this->create($anr, [
                'stakeholder' => $stakeholder,
                'requirement' => $requirement,
                'position' => $position++,
            ], false);
        }
    }

    private function applyCreatePosition(InterestedParty $interestedParty, array $data): void
    {
        if (!empty($data['position'])) {
            $interestedParty->setPosition((int)$data['position']);
        } else {
            $maxPosition = $this->interestedPartyTable->findMaxPosition(
                $interestedParty->getImplicitPositionRelationsValues()
            );
            $interestedParty->setPosition($maxPosition + 1);
        }
    }

    private function applyUpdatedPosition(InterestedParty $interestedParty, int $newPosition): void
    {
        $oldPosition = $interestedParty->getPosition();
        $params = $interestedParty->getImplicitPositionRelationsValues();

        if ($newPosition < $oldPosition) {
            $this->interestedPartyTable
                ->incrementPositions($newPosition, $oldPosition, 1, $params, $this->connectedUserEmail);
        } else {
            $this->interestedPartyTable
                ->incrementPositions($oldPosition, $newPosition, -1, $params, $this->connectedUserEmail);
        }

        $interestedParty->setPosition($newPosition);
    }

    private function normalizeText(mixed $value): string
    {
        return trim((string)$value);
    }

    private function buildSignature(string $stakeholder, string $requirement): string
    {
        return strtolower($stakeholder) . '|' . strtolower($requirement);
    }
}
