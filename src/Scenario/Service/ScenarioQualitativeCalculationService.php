<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Service;

/** Applies the finite, profile-snapshotted qualitative policy approved in DEC-004. */
final class ScenarioQualitativeCalculationService
{
    /** @param array<string, mixed> $profile */
    public function validateProfile(array $profile): void
    {
        $this->validatePolicy($profile);
        $likelihood = $this->labelValues($profile['likelihoodLabels'] ?? [], true);
        $consequence = $this->labelValues($profile['consequenceLabels'] ?? [], false);
        if (!is_array($profile['dimensions'] ?? null) || $profile['dimensions'] === []) {
            throw new \InvalidArgumentException('At least one impact dimension is required.');
        }
        foreach ($profile['dimensions'] as $dimension) {
            if (!is_string($dimension) || $dimension === '') {
                throw new \InvalidArgumentException('The approved profile dimensions are invalid.');
            }
            $levels = $profile['dimensionLevels'][$dimension] ?? $profile['consequenceLabels'] ?? [];
            if ($this->labelValues($levels, false) !== $consequence) {
                throw new \InvalidArgumentException(
                    'All impact dimensions must use the approved ordered scale values.'
                );
            }
        }
        if (isset($profile['bands']['matrix'])) {
            $this->validateBandMatrix($profile['bands']['matrix'], $likelihood, $consequence);
        }
    }

    /** @param array<string, mixed> $policy */
    public function validatePolicy(array $policy): void
    {
        if (($policy['aggregationPolicy'] ?? null) !== 'highest_consequence_dimension') {
            throw new \InvalidArgumentException(
                'Only the approved highest-consequence aggregation policy is supported.'
            );
        }
        if (!is_array($policy['bands'] ?? null) || !is_array($policy['acceptanceRules'] ?? null)) {
            throw new \InvalidArgumentException('Profile bands and acceptance rules are required.');
        }
        if (isset($policy['bands']['thresholds'])) {
            $thresholds = $policy['bands']['thresholds'];
            if (!is_array($thresholds)
                || !is_int($thresholds['low'] ?? null)
                || !is_int($thresholds['high'] ?? null)
                || $thresholds['low'] < 0
                || $thresholds['high'] < $thresholds['low']) {
                throw new \InvalidArgumentException('Operational-risk acceptance thresholds are invalid.');
            }
        }
        $allowedAcceptanceRules = ['justification_required_for_retain', 'treatment_required_before_acceptance'];
        foreach (['low', 'medium', 'high', 'critical'] as $band) {
            if (isset($policy['bands']['matrix']) && !array_key_exists($band, $policy['acceptanceRules'])) {
                throw new \InvalidArgumentException('Each qualitative band requires an acceptance rule.');
            }
            if (isset($policy['bands']['matrix'])
                && !in_array($policy['acceptanceRules'][$band], $allowedAcceptanceRules, true)) {
                throw new \InvalidArgumentException('Each qualitative band requires an approved acceptance rule.');
            }
        }
    }

    /** @param array<string, mixed> $profile @param array<string, mixed> $input @return array<string, mixed> */
    public function calculate(array $profile, array $input): array
    {
        $this->validateProfile($profile);
        $likelihoodInput = $input['likelihood'] ?? null;
        $likelihood = (int) $likelihoodInput;
        $dimensions = (array) ($input['dimensionSeverities'] ?? []);
        $likelihoodValues = $this->labelValues($profile['likelihoodLabels'] ?? [], true);
        if ((!is_int($likelihoodInput) && !ctype_digit((string) $likelihoodInput))
            || !in_array($likelihood, $likelihoodValues, true) || $dimensions === []) {
            throw new \InvalidArgumentException('Likelihood and at least one impact dimension are required.');
        }
        foreach ($dimensions as $dimension => $severity) {
            $dimensionValues = $this->labelValues(
                $profile['dimensionLevels'][$dimension] ?? $profile['consequenceLabels'] ?? [],
                false
            );
            if (!is_string($dimension) || !is_int($severity) && !ctype_digit((string) $severity)
                || !in_array($dimension, $profile['dimensions'], true)
                || !in_array((int) $severity, $dimensionValues, true)) {
                throw new \InvalidArgumentException('Each impact severity must be an ordered qualitative value.');
            }
            $dimensions[$dimension] = (int) $severity;
        }
        $highest = max($dimensions);
        $controlling = array_keys(array_filter(
            $dimensions,
            static fn (int $value): bool => $value === $highest
        ));
        $controllingLabels = array_map(
            static fn (string $dimension): string => is_string($profile['dimensionLabels'][$dimension] ?? null)
                && trim($profile['dimensionLabels'][$dimension]) !== ''
                ? $profile['dimensionLabels'][$dimension]
                : $dimension,
            $controlling
        );
        $band = $this->band($profile, $likelihood, $highest);

        return [
            'inputs' => [
                'likelihood' => $likelihood,
                'dimensionSeverities' => $dimensions,
                'controlReferences' => array_values((array) ($input['controlReferences'] ?? [])),
                'evidenceReferences' => array_values((array) ($input['evidenceReferences'] ?? [])),
                'justification' => trim((string) ($input['justification'] ?? '')),
            ],
            'profile' => $profile,
            'aggregationRule' => 'highest_consequence_dimension',
            'dimensionResults' => $dimensions,
            'score' => ['likelihood' => $likelihood, 'consequence' => $highest],
            'band' => $band,
            'threshold' => $profile['acceptanceRules'][$band]
                ?? ($band === 'high' || $band === 'critical'
                    ? 'treatment_required_before_acceptance'
                    : 'justification_required_for_retain'),
            'explanation' => sprintf(
                '%s is based on likelihood %d and the highest impact %d in %s.',
                ucfirst($band),
                $likelihood,
                $highest,
                implode(', ', $controllingLabels)
            ),
        ];
    }

    /** @param array<string, mixed> $profile */
    private function band(array $profile, int $likelihood, int $consequence): string
    {
        if (isset($profile['bands']['matrix'])) {
            return (string) $profile['bands']['matrix'][(string) $likelihood][(string) $consequence];
        }
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

    /** @param mixed $labels @return array<int, int> */
    private function labelValues(mixed $labels, bool $likelihood): array
    {
        if (!is_array($labels) || $labels === [] || count($labels) > 20) {
            throw new \InvalidArgumentException('The approved profile labels are invalid.');
        }
        $values = [];
        foreach ($labels as $offset => $label) {
            if (!is_array($label) || !is_int($label['value'] ?? null)
                || !is_string($label['label'] ?? null)
                || trim($label['label']) === '') {
                throw new \InvalidArgumentException('The approved profile labels are invalid.');
            }
            $value = (int) $label['value'];
            if ($value < 0 || $value > 255
                || ($likelihood && !$this->isIndexedLikelihood($values, $value, $offset))
                || (!$likelihood && $values !== [] && $value <= $values[array_key_last($values)])) {
                throw new \InvalidArgumentException('The approved profile labels are invalid.');
            }
            $values[] = $value;
        }

        return $values;
    }

    /** @param array<int, int> $values */
    private function isIndexedLikelihood(array $values, int $value, int $offset): bool
    {
        if ($offset === 0) {
            return $value === 0 || $value === 1;
        }

        $startsAtZero = $values[0] === 0;

        return $value === $offset + ($startsAtZero ? 0 : 1);
    }

    /** @param array<int, int> $likelihoodValues @param array<int, int> $consequenceValues */
    private function validateBandMatrix(mixed $matrix, array $likelihoodValues, array $consequenceValues): void
    {
        if (!is_array($matrix)) {
            throw new \InvalidArgumentException('The qualitative band matrix is invalid.');
        }
        $allowed = ['low', 'medium', 'high', 'critical'];
        $severity = array_flip($allowed);
        foreach ($likelihoodValues as $likelihoodIndex => $likelihood) {
            $row = $matrix[(string) $likelihood] ?? null;
            if (!is_array($row)) {
                throw new \InvalidArgumentException('The qualitative band matrix is invalid.');
            }
            foreach ($consequenceValues as $consequenceIndex => $consequence) {
                $band = $row[(string) $consequence] ?? null;
                if (!in_array($band, $allowed, true)) {
                    throw new \InvalidArgumentException('The qualitative band matrix is invalid.');
                }
                if ($consequenceIndex > 0
                    && $severity[$band] < $severity[$row[(string) $consequenceValues[$consequenceIndex - 1]]]) {
                    throw new \InvalidArgumentException('Qualitative bands cannot decrease as consequence increases.');
                }
                if ($likelihoodIndex > 0
                    && $severity[$band] < $severity[
                        $matrix[(string) $likelihoodValues[$likelihoodIndex - 1]][(string) $consequence]
                    ]) {
                    throw new \InvalidArgumentException('Qualitative bands cannot decrease as likelihood increases.');
                }
            }
        }
    }
}
