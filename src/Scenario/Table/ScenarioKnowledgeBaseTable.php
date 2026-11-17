<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Table;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use Monarc\FrontOffice\Entity\Anr;

/** Initializes editable local records from immutable package evidence, never live BO content. */
final class ScenarioKnowledgeBaseTable
{
    private Connection $connection;

    public function __construct(EntityManager $entityManager)
    {
        $this->connection = $entityManager->getConnection();
    }

    public function hasLocalEntries(Anr $anr): bool
    {
        $tables = ['assets', 'objects', 'threats', 'vulnerabilities', 'referentials', 'measures', 'risk_sources'];
        foreach ($tables as $table) {
            if ($this->connection->fetchOne("SELECT 1 FROM $table WHERE anr_id = ? LIMIT 1", [$anr->getId()])) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, array<string, mixed>> $links */
    public function seedPackage(Anr $anr, array $links): void
    {
        $this->connection->transactional(function () use ($anr, $links): void {
            foreach ($links as $link) {
                if (!isset($link['source']) || !is_array($link['source'])) {
                    throw new InvalidArgumentException('The template package has no source snapshot.');
                }
                $this->insertSource($anr, (string) $link['type'], $link['source']);
            }
        });
    }

    /** @param array<string, mixed> $source */
    private function insertSource(Anr $anr, string $type, array $source): void
    {
        $table = match ($type) {
            'asset-type' => 'assets',
            'asset' => 'objects',
            'threat' => 'threats',
            'vulnerability' => 'vulnerabilities',
            'referential' => 'referentials',
            'control' => 'measures',
            'risk-source' => 'risk_sources',
            default => throw new InvalidArgumentException('Unsupported template package source.'),
        };
        $parentType = match ($type) {
            'asset' => 'asset-type',
            'control' => 'referential',
            default => null,
        };
        if ($parentType !== null) {
            if (!is_array($source['parent'] ?? null)) {
                throw new InvalidArgumentException('The template package source has no parent snapshot.');
            }
            $this->insertSource($anr, $parentType, $source['parent']);
        }
        $identity = $type === 'risk-source'
            ? ['label' => (string) $source['label']]
            : ['uuid' => (string) $source['identity']];
        $identity['anr_id'] = (int) $anr->getId();
        $where = implode(' AND ', array_map(static fn (string $key): string => "$key = ?", array_keys($identity)));
        if ($this->connection->fetchOne("SELECT 1 FROM $table WHERE $where", array_values($identity))) {
            return; // Never overwrite an existing local choice or edit.
        }
        $row = $identity + ['creator' => $anr->getCreator(), 'created_at' => gmdate('Y-m-d H:i:s')];
        if ($type === 'risk-source') {
            $row += ['is_active' => 1, 'is_default' => 0];
        } else {
            // SRA-26 snapshots contain the default author representation only.
            // Populate every legacy language slot so this remains readable in the analysis language.
            foreach (range(1, 4) as $language) {
                $row['label' . $language] = (string) ($source['label'] ?? '');
                if (in_array($type, ['asset-type', 'threat', 'vulnerability'], true)) {
                    $row['description' . $language] = (string) ($source['description'] ?? '');
                }
                if ($type === 'asset') {
                    $row['name' . $language] = (string) $source['label'];
                    $row['label' . $language] = (string) ($source['description'] ?? '');
                }
            }
            if (in_array($type, ['asset-type', 'threat', 'vulnerability', 'control'], true)) {
                $row += ['status' => 1, 'code' => (string) ($source['code'] ?? '')];
            }
            if ($type === 'asset') {
                $row['asset_id'] = (string) $source['parent']['identity'];
            }
            if ($type === 'control') {
                $row['referential_uuid'] = (string) $source['parent']['identity'];
            }
        }
        $this->connection->insert($table, $row);
    }
}
