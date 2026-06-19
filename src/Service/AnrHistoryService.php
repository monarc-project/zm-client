<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Service;

use DateTime;
use JsonException;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\AnrHistory;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Table\AnrHistoryTable;

class AnrHistoryService
{
    private User $connectedUser;

    public function __construct(
        private AnrHistoryTable $anrHistoryTable,
        ConnectedUserService $connectedUserService
    ) {
        /** @var User $connectedUser */
        $connectedUser = $connectedUserService->getConnectedUser();
        $this->connectedUser = $connectedUser;
    }

    /**
     * @return AnrHistory[]
     */
    public function getTargetHistory(Anr $anr, int $targetType, int $targetId, ?int $changeType = null): array
    {
        return $this->anrHistoryTable->findByAnrIdAndTarget($anr->getId(), $targetType, $targetId, $changeType);
    }

    /**
     * @return AnrHistory[]
     */
    public function getHistory(Anr $anr, array $targetTypes = [], ?int $changeType = null): array
    {
        return $this->anrHistoryTable->findByAnrIdAndTypes($anr->getId(), $targetTypes, $changeType);
    }

    public function getLatestTargetHistory(Anr $anr, int $targetType, int $targetId): ?AnrHistory
    {
        return $this->anrHistoryTable->findLatestByAnrIdAndTarget($anr->getId(), $targetType, $targetId);
    }

    /**
     * @return array<int, array<int, AnrHistory[]>>
     */
    public function getGroupedHistory(Anr $anr, array $targetTypes = []): array
    {
        $groupedHistory = [];
        foreach ($this->anrHistoryTable->findByAnrIdAndTypes($anr->getId(), $targetTypes) as $entry) {
            $groupedHistory[$entry->getTargetType()][$entry->getTargetId()][] = $entry;
        }

        return $groupedHistory;
    }

    /**
     * @return array<int, array<int, AnrHistory>>
     */
    public function getLatestGroupedHistory(Anr $anr, array $targetTypes = []): array
    {
        $latestGroupedHistory = [];
        foreach ($this->anrHistoryTable->findByAnrIdAndTypes($anr->getId(), $targetTypes) as $entry) {
            $latestGroupedHistory[$entry->getTargetType()][$entry->getTargetId()] = $entry;
        }

        return $latestGroupedHistory;
    }

    public function createEntry(
        Anr $anr,
        int $targetType,
        int $targetId,
        int $changeType,
        ?string $fieldCode = null,
        mixed $oldValue = null,
        mixed $newValue = null,
        bool $saveInDb = true
    ): AnrHistory {
        $entry = (new AnrHistory())
            ->setAnrId($anr->getId())
            ->setTargetType($targetType)
            ->setTargetId($targetId)
            ->setChangeType($changeType)
            ->setFieldCode($fieldCode)
            ->setOldValue($this->normalizeValue($oldValue))
            ->setNewValue($this->normalizeValue($newValue))
            ->setPerformedByFirstname($this->normalizeNullableText($this->connectedUser->getFirstname()))
            ->setPerformedByLastname($this->normalizeNullableText($this->connectedUser->getLastname()))
            ->setPerformedByEmail($this->normalizeNullableText($this->connectedUser->getEmail()));

        $this->anrHistoryTable->save($entry, $saveInDb);

        return $entry;
    }

    /**
     * @param array $entries
     */
    public function createEntries(Anr $anr, array $entries): void
    {
        foreach ($entries as $entry) {
            $this->createEntry(
                $anr,
                $entry['targetType'],
                $entry['targetId'],
                $entry['changeType'],
                $entry['fieldCode'] ?? null,
                $entry['oldValue'] ?? null,
                $entry['newValue'] ?? null,
                false
            );
        }

        if ($entries !== []) {
            $this->anrHistoryTable->flush();
        }
    }

    /**
     * @param array<int, array{
     *     targetType:int,
     *     targetId:int,
     *     changeType:int,
     *     fieldCode:?string,
     *     oldValue:mixed,
     *     newValue:mixed,
     *     performedByFirstname?:?string,
     *     performedByLastname?:?string,
     *     performedByEmail?:?string,
     *     createdAt?:?string
     * }> $entries
     */
    public function importEntries(Anr $anr, array $entries): void
    {
        foreach ($entries as $entry) {
            $historyEntry = (new AnrHistory())
                ->setAnrId($anr->getId())
                ->setTargetType($entry['targetType'])
                ->setTargetId($entry['targetId'])
                ->setChangeType($entry['changeType'])
                ->setFieldCode($entry['fieldCode'] ?? null)
                ->setOldValue($this->normalizeValue($entry['oldValue'] ?? null))
                ->setNewValue($this->normalizeValue($entry['newValue'] ?? null))
                ->setPerformedByFirstname($this->normalizeNullableText($entry['performedByFirstname'] ?? null))
                ->setPerformedByLastname($this->normalizeNullableText($entry['performedByLastname'] ?? null))
                ->setPerformedByEmail($this->normalizeNullableText($entry['performedByEmail'] ?? null));

            $createdAt = $this->parseCreatedAt($entry['createdAt'] ?? null);
            if ($createdAt !== null) {
                $historyEntry->setCreatedAt($createdAt);
            }

            $this->anrHistoryTable->save($historyEntry, false);
        }

        if ($entries !== []) {
            $this->anrHistoryTable->flush();
        }
    }

    public function prepareEntryData(AnrHistory $entry): array
    {
        return [
            'id' => $entry->getId(),
            'targetType' => $entry->getTargetType(),
            'targetId' => $entry->getTargetId(),
            'changeType' => $entry->getChangeType(),
            'fieldCode' => $entry->getFieldCode(),
            'oldValue' => $entry->getOldValue(),
            'newValue' => $entry->getNewValue(),
            'performedByFirstname' => $entry->getPerformedByFirstname(),
            'performedByLastname' => $entry->getPerformedByLastname(),
            'performedByEmail' => $entry->getPerformedByEmail(),
            'createdAt' => $entry->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    private function normalizeNullableText(?string $value): ?string
    {
        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }

    private function normalizeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string)$value;
        }

        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            return (string)$value;
        }
    }

    private function parseCreatedAt(?string $value): ?DateTime
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        $createdAt = DateTime::createFromFormat('Y-m-d H:i:s', $value);
        if ($createdAt !== false) {
            return $createdAt;
        }

        try {
            return new DateTime($value);
        } catch (\Exception) {
            return null;
        }
    }
}
