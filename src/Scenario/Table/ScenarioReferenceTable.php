<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Table;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Monarc\FrontOffice\Entity\Anr;

/** Resolves only authorised, current MONARC references used by Scenario links. */
final class ScenarioReferenceTable
{
    private Connection $connection;

    public function __construct(EntityManager $entityManager)
    {
        $this->connection = $entityManager->getConnection();
    }

    public function hasRiskSource(int $anrId, string $reference): bool
    {
        return $this->exists('risk_sources', 'id', $anrId, $reference);
    }

    public function hasThreat(int $anrId, string $uuid): bool
    {
        return $this->exists('threats', 'uuid', $anrId, $uuid);
    }

    public function hasVulnerability(int $anrId, string $uuid): bool
    {
        return $this->exists('vulnerabilities', 'uuid', $anrId, $uuid);
    }

    public function hasSubject(int $anrId, string $type, string $uuid): bool
    {
        $table = $type === 'asset' ? 'assets' : 'objects';

        return in_array($type, ['asset', 'process', 'object', 'information', 'supplier', 'objective'], true)
            && $this->exists($table, 'uuid', $anrId, $uuid);
    }

    public function hasControl(int $anrId, string $uuid): bool
    {
        return $this->exists('measures', 'uuid', $anrId, $uuid);
    }

    /** @return array<string, mixed> */
    public function list(Anr $anr, string $kind, string $query, int $page, int $pageSize): array
    {
        if ($kind === 'control') {
            return $this->listControls($anr, $query, $page, $pageSize);
        }

        $labelField = 'label' . $anr->getLanguage();
        $nameField = 'name' . $anr->getLanguage();
        $definitions = [
            // Risk sources are legacy per-ANR records with one shared label;
            // they do not have the label1--label4 convention used elsewhere.
            'risk-source' => ['risk_sources', 'id', 'label'],
            'threat' => ['threats', 'uuid', $labelField],
            'vulnerability' => ['vulnerabilities', 'uuid', $labelField],
            'asset' => ['assets', 'uuid', $labelField],
            'process' => ['objects', 'uuid', $nameField],
            'object' => ['objects', 'uuid', $nameField],
            'information' => ['objects', 'uuid', $nameField],
            'supplier' => ['objects', 'uuid', $nameField],
            'objective' => ['objects', 'uuid', $nameField],
        ];
        if (!isset($definitions[$kind])) {
            return ['items' => [], 'page' => $page, 'pageSize' => $pageSize, 'total' => 0];
        }
        [$table, $identifier, $label] = $definitions[$kind];
        $where = 'anr_id = ?';
        $parameters = [$anr->getId()];
        if ($query !== '') {
            $where .= sprintf(' AND %s LIKE ?', $label);
            $parameters[] = '%' . $query . '%';
        }
        $total = (int) $this->connection->fetchOne(
            sprintf('SELECT COUNT(*) FROM %s WHERE %s', $table, $where),
            $parameters
        );
        $rows = $this->connection->fetchAllAssociative(
            sprintf(
                'SELECT %s AS reference_value, %s AS label FROM %s WHERE %s ORDER BY %s ASC LIMIT %d OFFSET %d',
                $identifier,
                $label,
                $table,
                $where,
                $label,
                $pageSize,
                ($page - 1) * $pageSize
            ),
            $parameters
        );

        return [
            'items' => array_map(static fn (array $row): array => [
                'value' => (string) $row['reference_value'],
                'label' => (string) $row['label'],
                'type' => $kind,
                'available' => true,
            ], $rows),
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
        ];
    }

    private function exists(string $table, string $identifier, int $anrId, string $value): bool
    {
        return (bool) $this->connection->fetchOne(
            sprintf('SELECT 1 FROM %s WHERE anr_id = ? AND %s = ? LIMIT 1', $table, $identifier),
            [$anrId, $value]
        );
    }

    /** @return array<string, mixed> */
    private function listControls(Anr $anr, string $query, int $page, int $pageSize): array
    {
        $controlLabel = 'measure.label' . $anr->getLanguage();
        $referentialLabel = 'referential.label' . $anr->getLanguage();
        $where = 'measure.anr_id = ?';
        $parameters = [$anr->getId()];
        if ($query !== '') {
            $where .= sprintf(
                ' AND (%1$s LIKE ? OR %2$s LIKE ? OR measure.code LIKE ?)',
                $controlLabel,
                $referentialLabel
            );
            $parameters[] = '%' . $query . '%';
            $parameters[] = '%' . $query . '%';
            $parameters[] = '%' . $query . '%';
        }
        $join = ' FROM measures measure LEFT JOIN referentials referential '
            . 'ON referential.anr_id = measure.anr_id AND referential.uuid = measure.referential_uuid';
        $total = (int) $this->connection->fetchOne(
            sprintf('SELECT COUNT(*)%s WHERE %s', $join, $where),
            $parameters
        );
        $rows = $this->connection->fetchAllAssociative(
            sprintf(
                'SELECT measure.uuid AS reference_value, measure.code, %1$s AS control_label, '
                . '%2$s AS referential_label%3$s WHERE %4$s '
                . 'ORDER BY referential_label ASC, control_label ASC, measure.code ASC LIMIT %5$d OFFSET %6$d',
                $controlLabel,
                $referentialLabel,
                $join,
                $where,
                $pageSize,
                ($page - 1) * $pageSize
            ),
            $parameters
        );

        return [
            'items' => array_map(static fn (array $row): array => [
                'value' => (string) $row['reference_value'],
                'label' => sprintf(
                    '%s — %s',
                    self::controlLabel($row),
                    self::referentialLabel($row)
                ),
                'type' => 'control',
                'available' => true,
            ], $rows),
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
        ];
    }

    /** @param array<string, mixed> $row */
    private static function controlLabel(array $row): string
    {
        return trim((string) ($row['control_label'] ?? ''))
            ?: trim((string) ($row['code'] ?? ''))
            ?: 'Unlabelled control';
    }

    /** @param array<string, mixed> $row */
    private static function referentialLabel(array $row): string
    {
        return trim((string) ($row['referential_label'] ?? '')) ?: 'Unspecified referential';
    }
}
