<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Tests\Unit\Scenario;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Monarc\FrontOffice\Scenario\Table\ScenarioTemplateSnapshotTable;
use PHPUnit\Framework\TestCase;

/** @covers \Monarc\FrontOffice\Scenario\Table\ScenarioTemplateSnapshotTable */
final class ScenarioTemplatePackageInstantiationTest extends TestCase
{
    public function testPackageExtendsTheExistingSnapshotAndCannotRefreshLocalEvidence(): void
    {
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $db->executeStatement('CREATE TABLE scenario_template_snapshots (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT, anr_id INT, scenario_analysis_id INT, catalog_template_uuid TEXT, catalog_template_version INT, catalog_release_uuid TEXT, catalog_release_checksum TEXT, catalog_provenance TEXT, payload_json TEXT, created_by INT)');
        $db->executeStatement('CREATE TABLE scenario_template_instance_records (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT, anr_id INT, snapshot_id INT, record_type TEXT, source_uuid TEXT, source_version INT, position INT, content_json TEXT, revision INT DEFAULT 1)');
        $db->executeStatement('CREATE TABLE scenario_template_local_overrides (anr_id INT, snapshot_id INT, instance_record_uuid TEXT, field_name TEXT, value_json TEXT, revision INT)');
        $em = $this->createMock(EntityManager::class);
        $em->method('getConnection')->willReturn($db);
        $table = new ScenarioTemplateSnapshotTable($em);
        $catalog = [
            'stable_uuid' => '00000000-0000-4000-8000-000000000001', 'version' => 1,
            'provenance' => 'catalog:template:v1', 'events' => [], 'suggestions' => [],
            'knowledgeBasePackage' => [[
                'type' => 'control', 'intendedUse' => 'default',
                'source' => ['identity' => 'source', 'label' => 'Published label', 'parent' => ['label' => 'Framework']],
            ]],
        ];
        $result = $table->create(7, 8, 9, $catalog);
        self::assertCount(1, $result['records']);
        self::assertSame('Published label', $result['payload']['knowledgeBasePackage'][0]['source']['label']);
        self::assertSame('Framework', $result['records'][0]['content']['knowledgeBasePackage'][0]['source']['parent']['label']);
        $db->insert('scenario_template_local_overrides', [
            'anr_id' => 7, 'snapshot_id' => 1, 'instance_record_uuid' => $result['records'][0]['uuid'],
            'field_name' => 'controls', 'value_json' => json_encode(['local-control']), 'revision' => 1,
        ]);
        $catalog['knowledgeBasePackage'][0]['source']['label'] = 'Later source content';
        $persisted = $table->findLatestByAnr(7);
        self::assertSame($result['payload'], $persisted['payload']);
        self::assertSame($result['catalogReleaseChecksum'], $persisted['catalogReleaseChecksum']);
        self::assertSame(['local-control'], $persisted['records'][0]['localOverrides']['controls']['value']);
        self::assertNull($table->findLatestByAnr(99));
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM scenario_template_snapshots'));
    }
}
