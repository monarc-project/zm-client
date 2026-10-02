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
        // `objects` is a generic legacy table.  It has no server-verifiable
        // process/information/supplier/objective discriminator, so accepting
        // it under one of those labels would forge a typed Scenario reference.
        if ($type === 'asset') {
            return $this->hasAssetObject($anrId, $uuid)
                // Retain compatibility for the early Scenario links created
                // before asset types and concrete assets were distinguished.
                || $this->exists('assets', 'uuid', $anrId, $uuid);
        }

        return $type === 'object' && $this->exists('objects', 'uuid', $anrId, $uuid);
    }

    public function hasControl(int $anrId, string $uuid): bool
    {
        return $this->exists('measures', 'uuid', $anrId, $uuid);
    }

    /** @return array<string, string>|null */
    public function snapshot(Anr $anr, string $kind, string $uuid): ?array
    {
        if ($kind === 'asset') {
            return $this->assetSnapshot($anr, $uuid);
        }
        $labelField = 'label' . $anr->getLanguage();
        $nameField = 'name' . $anr->getLanguage();
        $definitions = [
            'risk-source' => ['risk_sources', 'id', 'label'],
            'threat' => ['threats', 'uuid', $labelField],
            'vulnerability' => ['vulnerabilities', 'uuid', $labelField],
            'asset' => ['assets', 'uuid', $labelField],
            'object' => ['objects', 'uuid', $nameField],
        ];
        if ($kind === 'control') {
            return $this->controlSnapshot($anr, $uuid);
        }
        if (!isset($definitions[$kind])) {
            return null;
        }
        [$table, $identifier, $label] = $definitions[$kind];
        $row = $this->connection->fetchAssociative(
            sprintf('SELECT %s AS label FROM %s WHERE anr_id = ? AND %s = ? LIMIT 1', $label, $table, $identifier),
            [(int) $anr->getId(), $uuid]
        );
        if ($row === false) {
            return null;
        }

        return [
            'kind' => $kind,
            'identity' => $uuid,
            'label' => trim((string) $row['label']) ?: 'Unlabelled ' . $kind,
            'sourceScope' => 'current-analysis',
        ];
    }

    /** @return array<string, string>|null */
    private function controlSnapshot(Anr $anr, string $uuid): ?array
    {
        $controlLabel = 'measure.label' . $anr->getLanguage();
        $referentialLabel = 'referential.label' . $anr->getLanguage();
        $row = $this->connection->fetchAssociative(
            sprintf(
                'SELECT %1$s AS control_label, %2$s AS referential_label, measure.code '
                . 'FROM measures measure LEFT JOIN referentials referential '
                . 'ON referential.anr_id = measure.anr_id AND referential.uuid = measure.referential_uuid '
                . 'WHERE measure.anr_id = ? AND measure.uuid = ? LIMIT 1',
                $controlLabel,
                $referentialLabel
            ),
            [(int) $anr->getId(), $uuid]
        );
        if ($row === false) {
            return null;
        }

        return [
            'kind' => 'control',
            'identity' => $uuid,
            'label' => sprintf('%s — %s', self::controlLabel($row), self::referentialLabel($row)),
            'sourceScope' => 'current-analysis',
        ];
    }

    /** @return array<string, mixed> */
    public function list(Anr $anr, string $kind, string $query, int $page, int $pageSize, string $parent = ''): array
    {
        if ($kind === 'asset-type') {
            return $this->listAssetTypes($anr, $query, $page, $pageSize);
        }
        if ($kind === 'asset') {
            return $this->listAssetObjects($anr, $query, $page, $pageSize);
        }
        if ($kind === 'referential') {
            return $this->listReferentials($anr, $query, $page, $pageSize);
        }
        if ($kind === 'control') {
            return $this->listControls($anr, $query, $page, $pageSize, $parent);
        }
        if ($kind === 'recommendation-set') {
            return $this->listRecommendationSets($anr, $query, $page, $pageSize);
        }
        if ($kind === 'recommendation') {
            return $this->listRecommendations($anr, $query, $page, $pageSize, $parent);
        }

        $labelField = 'label' . $anr->getLanguage();
        $nameField = 'name' . $anr->getLanguage();
        $definitions = [
            // Risk sources are legacy per-ANR records with one shared label;
            // they do not have the label1--label4 convention used elsewhere.
            'risk-source' => ['risk_sources', 'id', 'label'],
            'threat' => ['threats', 'uuid', $labelField],
            'vulnerability' => ['vulnerabilities', 'uuid', $labelField],
            // The legacy object table does not identify process/information/
            // supplier/objective subtypes.  Only the generic object projection
            // is truthful until MONARC exposes a typed source record.
            'object' => ['objects', 'uuid', $nameField],
        ];
        if (!isset($definitions[$kind])) {
            return [
                'items' => [], 'page' => $page, 'pageSize' => $pageSize, 'total' => 0,
                'availability' => 'unavailable',
                'message' => 'This reference type has no typed current-analysis source in MONARC.',
            ];
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
            'availability' => 'available',
        ];
    }

    private function exists(string $table, string $identifier, int $anrId, string $value): bool
    {
        return (bool) $this->connection->fetchOne(
            sprintf('SELECT 1 FROM %s WHERE anr_id = ? AND %s = ? LIMIT 1', $table, $identifier),
            [$anrId, $value]
        );
    }

    private function hasAssetObject(int $anrId, string $uuid): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM objects WHERE anr_id = ? AND uuid = ? AND asset_id IS NOT NULL LIMIT 1',
            [$anrId, $uuid]
        );
    }

    /** @return array<string, mixed> */
    private function listAssetTypes(Anr $anr, string $query, int $page, int $pageSize): array
    {
        $label = 'label' . $anr->getLanguage();
        $where = 'anr_id = ? AND status = 1';
        $parameters = [(int) $anr->getId()];
        if ($query !== '') {
            $where .= sprintf(' AND (%s LIKE ? OR code LIKE ?)', $label);
            $parameters[] = '%' . $query . '%';
            $parameters[] = '%' . $query . '%';
        }

        return $this->referencePage('assets', 'uuid', $label, 'asset-type', $where, $parameters, $page, $pageSize);
    }

    /** @return array<string, mixed> */
    private function listAssetObjects(Anr $anr, string $query, int $page, int $pageSize): array
    {
        $name = 'object.name' . $anr->getLanguage();
        $objectLabel = 'object.label' . $anr->getLanguage();
        $assetLabel = 'asset.label' . $anr->getLanguage();
        $display = sprintf(
            "CONCAT(COALESCE(NULLIF(%s, ''), NULLIF(%s, ''), 'Unlabelled asset'), ' — ', %s)",
            $name,
            $objectLabel,
            $assetLabel
        );
        $where = 'object.anr_id = ? AND object.asset_id IS NOT NULL';
        $parameters = [(int) $anr->getId()];
        if ($query !== '') {
            $where .= sprintf(' AND (%s LIKE ? OR %s LIKE ? OR %s LIKE ?)', $name, $objectLabel, $assetLabel);
            $parameters[] = '%' . $query . '%';
            $parameters[] = '%' . $query . '%';
            $parameters[] = '%' . $query . '%';
        }
        $join = ' FROM objects object INNER JOIN assets asset '
            . 'ON asset.anr_id = object.anr_id AND asset.uuid = object.asset_id';
        $total = (int) $this->connection->fetchOne(
            sprintf('SELECT COUNT(*)%s WHERE %s', $join, $where),
            $parameters
        );
        $rows = $this->connection->fetchAllAssociative(
            sprintf(
                'SELECT object.uuid AS reference_value, %s AS label%s WHERE %s ORDER BY %s ASC LIMIT %d OFFSET %d',
                $display,
                $join,
                $where,
                $display,
                $pageSize,
                ($page - 1) * $pageSize
            ),
            $parameters
        );

        return $this->page($rows, 'asset', $page, $pageSize, $total);
    }

    /** @return array<string, string>|null */
    private function assetSnapshot(Anr $anr, string $uuid): ?array
    {
        $name = 'object.name' . $anr->getLanguage();
        $objectLabel = 'object.label' . $anr->getLanguage();
        $assetLabel = 'asset.label' . $anr->getLanguage();
        $display = sprintf(
            "CONCAT(COALESCE(NULLIF(%s, ''), NULLIF(%s, ''), 'Unlabelled asset'), ' — ', %s)",
            $name,
            $objectLabel,
            $assetLabel
        );
        $row = $this->connection->fetchAssociative(
            sprintf(
                'SELECT %s AS label FROM objects object INNER JOIN assets asset '
                . 'ON asset.anr_id = object.anr_id AND asset.uuid = object.asset_id '
                . 'WHERE object.anr_id = ? AND object.uuid = ? AND object.asset_id IS NOT NULL LIMIT 1',
                $display
            ),
            [(int) $anr->getId(), $uuid]
        );
        if ($row !== false) {
            return [
                'kind' => 'asset',
                'identity' => $uuid,
                'label' => (string) $row['label'],
                'sourceScope' => 'current-analysis',
            ];
        }

        $legacyType = $this->snapshotAssetType($anr, $uuid);

        return $legacyType === null ? null : $legacyType + ['kind' => 'asset'];
    }

    /** @return array<string, string>|null */
    private function snapshotAssetType(Anr $anr, string $uuid): ?array
    {
        $label = 'label' . $anr->getLanguage();
        $row = $this->connection->fetchAssociative(
            sprintf('SELECT %s AS label FROM assets WHERE anr_id = ? AND uuid = ? LIMIT 1', $label),
            [(int) $anr->getId(), $uuid]
        );
        if ($row === false) {
            return null;
        }

        return [
            'identity' => $uuid,
            'label' => trim((string) $row['label']) ?: 'Unlabelled asset type',
            'sourceScope' => 'current-analysis',
        ];
    }

    /** @param array<int, mixed> $parameters @return array<string, mixed> */
    private function referencePage(
        string $table,
        string $identifier,
        string $label,
        string $type,
        string $where,
        array $parameters,
        int $page,
        int $pageSize
    ): array {
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

        return $this->page($rows, $type, $page, $pageSize, $total);
    }

    /** @param array<int, array<string, mixed>> $rows @return array<string, mixed> */
    private function page(array $rows, string $type, int $page, int $pageSize, int $total): array
    {
        return [
            'items' => array_map(static fn (array $row): array => [
                'value' => (string) $row['reference_value'],
                'label' => (string) $row['label'],
                'type' => $type,
                'available' => true,
            ], $rows),
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'availability' => 'available',
        ];
    }

    /** @return array<string, mixed> */
    private function listReferentials(Anr $anr, string $query, int $page, int $pageSize): array
    {
        $label = 'label' . $anr->getLanguage();
        $where = 'anr_id = ?';
        $parameters = [(int) $anr->getId()];
        if ($query !== '') {
            $where .= sprintf(' AND %s LIKE ?', $label);
            $parameters[] = '%' . $query . '%';
        }

        return $this->referencePage(
            'referentials',
            'uuid',
            $label,
            'referential',
            $where,
            $parameters,
            $page,
            $pageSize
        );
    }

    /** @return array<string, mixed> */
    private function listRecommendationSets(Anr $anr, string $query, int $page, int $pageSize): array
    {
        $where = 'anr_id = ?';
        $parameters = [(int) $anr->getId()];
        if ($query !== '') {
            $where .= ' AND label LIKE ?';
            $parameters[] = '%' . $query . '%';
        }

        return $this->referencePage(
            'recommandations_sets',
            'uuid',
            'label',
            'recommendation-set',
            $where,
            $parameters,
            $page,
            $pageSize
        );
    }

    /** @return array<string, mixed> */
    private function listRecommendations(
        Anr $anr,
        string $query,
        int $page,
        int $pageSize,
        string $recommendationSet
    ): array {
        if ($recommendationSet === '') {
            return $this->parentRequiredPage(
                $page,
                $pageSize,
                'Select a recommendation set to browse its recommendations.'
            );
        }
        $label = 'recommendation.label' . $anr->getLanguage();
        $where = 'recommendation.anr_id = ? AND recommendation.recommandation_set_uuid = ?';
        $parameters = [(int) $anr->getId(), $recommendationSet];
        if ($query !== '') {
            $where .= sprintf(' AND (%s LIKE ? OR recommendation.code LIKE ?)', $label);
            $parameters[] = '%' . $query . '%';
            $parameters[] = '%' . $query . '%';
        }
        $total = (int) $this->connection->fetchOne(
            sprintf('SELECT COUNT(*) FROM recommandations recommendation WHERE %s', $where),
            $parameters
        );
        $rows = $this->connection->fetchAllAssociative(
            sprintf(
                'SELECT recommendation.uuid AS reference_value, %s AS label FROM recommandations recommendation '
                . 'WHERE %s ORDER BY label ASC, recommendation.code ASC LIMIT %d OFFSET %d',
                $label,
                $where,
                $pageSize,
                ($page - 1) * $pageSize
            ),
            $parameters
        );

        return $this->page($rows, 'recommendation', $page, $pageSize, $total);
    }

    /** @return array<string, mixed> */
    private function parentRequiredPage(int $page, int $pageSize, string $message): array
    {
        return [
            'items' => [], 'page' => $page, 'pageSize' => $pageSize, 'total' => 0,
            'availability' => 'unavailable', 'message' => $message,
        ];
    }

    /** @return array<string, mixed> */
    private function listControls(Anr $anr, string $query, int $page, int $pageSize, string $referential): array
    {
        if ($referential === '') {
            return $this->parentRequiredPage($page, $pageSize, 'Select a referential to browse its controls.');
        }
        $controlLabel = 'measure.label' . $anr->getLanguage();
        $referentialLabel = 'referential.label' . $anr->getLanguage();
        $where = 'measure.anr_id = ? AND measure.referential_uuid = ?';
        $parameters = [$anr->getId(), $referential];
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
                'group' => self::referentialLabel($row),
                'type' => 'control',
                'available' => true,
            ], $rows),
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'availability' => 'available',
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
