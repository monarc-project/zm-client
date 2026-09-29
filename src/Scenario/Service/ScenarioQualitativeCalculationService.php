<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Service;

/** Applies the finite, profile-snapshotted qualitative policy approved in DEC-004. */
final class ScenarioQualitativeCalculationService
{
    /** @param array<string, mixed> $profile */
    public function validateProfile(array $profile): void
    {
        if (($profile['aggregationPolicy'] ?? null) !== 'highest_consequence_dimension') {
            throw new \InvalidArgumentException(
                'Only the approved highest-consequence aggregation policy is supported.'
            );
        }
        foreach (['likelihoodLabels', 'consequenceLabels'] as $field) {
            $values = array_map(
                static fn (mixed $label): int => is_array($label) ? (int) ($label['value'] ?? 0) : 0,
                (array) ($profile[$field] ?? [])
            );
            sort($values);
            if ($values !== [1, 2, 3, 4, 5]) {
                throw new \InvalidArgumentException('The approved profile requires ordered labels from 1 through 5.');
            }
        }
        if (!is_array($profile['dimensions'] ?? null) || $profile['dimensions'] === []) {
            throw new \InvalidArgumentException('At least one consequence dimension is required.');
        }
        if (!is_array($profile['bands'] ?? null) || !is_array($profile['acceptanceRules'] ?? null)) {
            throw new \InvalidArgumentException('Profile bands and acceptance rules are required.');
        }
    }

    /** @param array<string, mixed> $profile @param array<string, mixed> $input @return array<string, mixed> */
    public function calculate(array $profile, array $input): array
    {
        $this->validateProfile($profile);
        $likelihood = (int) ($input['likelihood'] ?? 0);
        $dimensions = (array) ($input['dimensionSeverities'] ?? []);
        if ($likelihood < 1 || $likelihood > 5 || $dimensions === []) {
            throw new \InvalidArgumentException('Likelihood and at least one consequence dimension are required.');
        }
        foreach ($dimensions as $dimension => $severity) {
            if (!is_string($dimension) || !is_int($severity) && !ctype_digit((string) $severity)
                || !in_array($dimension, $profile['dimensions'], true)
                || (int) $severity < 1 || (int) $severity > 5) {
                throw new \InvalidArgumentException('Each consequence severity must be an ordered qualitative value.');
            }
            $dimensions[$dimension] = (int) $severity;
        }
        $highest = max($dimensions);
        $controlling = array_keys(array_filter(
            $dimensions,
            static fn (int $value): bool => $value === $highest
        ));
        $band = $this->band($likelihood, $highest);

        return [
            'inputs' => [
                'likelihood' => $likelihood,
                'dimensionSeverities' => $dimensions,
                'controlReferences' => array_values((array) ($input['controlReferences'] ?? [])),
                'evidenceReferences' => array_values((array) ($input['evidenceReferences'] ?? [])),
                'rationale' => trim((string) ($input['rationale'] ?? '')),
            ],
            'profile' => $profile,
            'aggregationRule' => 'highest_consequence_dimension',
            'dimensionResults' => $dimensions,
            'score' => ['likelihood' => $likelihood, 'consequence' => $highest],
            'band' => $band,
            'threshold' => $band === 'high' || $band === 'critical'
                ? 'treatment_required_before_acceptance'
                : 'rationale_required_for_retain',
            'explanation' => sprintf(
                '%s is based on likelihood %d and the highest consequence %d in %s.',
                ucfirst($band),
                $likelihood,
                $highest,
                implode(', ', $controlling)
            ),
        ];
    }

    private function band(int $likelihood, int $consequence): string
    {
        if ($likelihood >= 4 && $consequence >= 4) {
            return 'critical';
        }
        if (($likelihood >= 4 && $consequence >= 3) || ($consequence >= 4 && $likelihood >= 3)) {
            return 'high';
        }
        if (($likelihood >= 3 && $consequence >= 2) || ($consequence >= 3 && $likelihood >= 2)) {
            return 'medium';
        }

        return 'low';
    }
}
