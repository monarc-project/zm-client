<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Service;

/** Evaluates directed event graphs without coupling graph rules to persistence. */
final class ScenarioRiskGraph
{
    /**
     * @param callable(int): array<int, int> $children
     */
    public static function createsCycle(int $fromId, int $toId, callable $children): bool
    {
        $pending = [$toId];
        $seen = [];
        while ($pending !== []) {
            $id = array_pop($pending);
            if ($id === $fromId) {
                return true;
            }
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            foreach ($children($id) as $child) {
                $pending[] = $child;
            }
        }

        return false;
    }
}
