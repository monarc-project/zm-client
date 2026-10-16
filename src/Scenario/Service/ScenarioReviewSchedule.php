<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Service;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/** Calculates the Scenario projection from the existing ANR reassessment strategy. */
final class ScenarioReviewSchedule
{
    /** @return array{state:string,nextReviewAt:?string,lastReviewAt:?string,reviewFrequency:?string,guidance:?string} */
    public function calculate(?string $lastReviewAt, ?string $reviewFrequency, ?DateTimeInterface $now = null): array
    {
        $now = ($now === null
            ? new DateTimeImmutable('now', new DateTimeZone('UTC'))
            : DateTimeImmutable::createFromInterface($now))
            ->setTimezone(new DateTimeZone('UTC'));
        $lastReview = $this->date($lastReviewAt);
        $months = $this->months($reviewFrequency);
        if ($lastReview === null || $months === null) {
            return [
                'state' => 'not_scheduled',
                'nextReviewAt' => null,
                'lastReviewAt' => $lastReview?->format('Y-m-d'),
                'reviewFrequency' => $reviewFrequency,
                'guidance' => 'Set a completed review date and a periodic review frequency in Context Establishment.',
            ];
        }

        $nextReview = $lastReview->modify('+' . $months . ' months');
        $today = $now->setTime(0, 0);
        $state = $today < $nextReview
            ? 'upcoming'
            : ($today->format('Y-m-d') === $nextReview->format('Y-m-d') ? 'due' : 'overdue');

        return [
            'state' => $state,
            'nextReviewAt' => $nextReview->format('Y-m-d\T00:00:00\Z'),
            'lastReviewAt' => $lastReview->format('Y-m-d'),
            'reviewFrequency' => $reviewFrequency,
            'guidance' => null,
        ];
    }

    private function date(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTime(0, 0);
        } catch (\Exception) {
            return null;
        }
    }

    private function months(?string $frequency): ?int
    {
        return match ($frequency) {
            'Monthly' => 1,
            'Quarterly' => 3,
            'Semi-annually' => 6,
            'Annually' => 12,
            default => null,
        };
    }
}
