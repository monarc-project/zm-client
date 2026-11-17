<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Tests\Unit\Scenario;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use Monarc\Core\Exception\ActionForbiddenException;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\AnrSupervisor;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Entity\UserAnr;
use Monarc\Core\Scenario\Table\CatalogTable;
use Monarc\Core\Scenario\Table\PackageSourceTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Scenario\Service\ScenarioAnalysisService;
use Monarc\FrontOffice\Scenario\Service\ScenarioTemplateInstantiationService;
use Monarc\FrontOffice\Scenario\Table\ScenarioAnalysisTable;
use Monarc\FrontOffice\Scenario\Table\ScenarioKnowledgeBaseTable;
use Monarc\FrontOffice\Scenario\Table\ScenarioTemplateSnapshotTable;
use Monarc\FrontOffice\Service\AnrService;
use Monarc\FrontOffice\Service\AnrSupervisorService;
use Monarc\FrontOffice\Table\AnrTable;
use Monarc\FrontOffice\Table\UserAnrTable;
use Monarc\FrontOffice\Table\UserTable;
use PHPUnit\Framework\TestCase;

/** @covers \Monarc\FrontOffice\Scenario\Table\ScenarioKnowledgeBaseTable */
final class ScenarioKnowledgeBaseTableTest extends TestCase
{
    private Connection $db;
    private ScenarioKnowledgeBaseTable $table;
    private Anr $anr;
    private int $blankInitializations = 0;

    protected function setUp(): void
    {
        $this->db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $labels = 'label1 TEXT, label2 TEXT, label3 TEXT, label4 TEXT';
        $descriptions = 'description1 TEXT, description2 TEXT, description3 TEXT, description4 TEXT';
        $metadata = 'creator TEXT, created_at TEXT';
        foreach ([
            'assets' => "uuid TEXT, anr_id INT, $labels, $descriptions, status INT, code TEXT, $metadata, PRIMARY KEY (uuid, anr_id)",
            'objects' => "uuid TEXT, anr_id INT, $labels, name1 TEXT, name2 TEXT, name3 TEXT, name4 TEXT, asset_id TEXT, $metadata, PRIMARY KEY (uuid, anr_id)",
            'threats' => "uuid TEXT, anr_id INT, $labels, $descriptions, status INT, code TEXT, $metadata, PRIMARY KEY (uuid, anr_id)",
            'vulnerabilities' => "uuid TEXT, anr_id INT, $labels, $descriptions, status INT, code TEXT, $metadata, PRIMARY KEY (uuid, anr_id)",
            'referentials' => "uuid TEXT, anr_id INT, $labels, $metadata, PRIMARY KEY (uuid, anr_id)",
            'measures' => "uuid TEXT, anr_id INT, $labels, referential_uuid TEXT, status INT, code TEXT, $metadata, PRIMARY KEY (uuid, anr_id)",
            'risk_sources' => "id INTEGER PRIMARY KEY AUTOINCREMENT, anr_id INT, label TEXT, is_active INT, is_default INT, $metadata",
        ] as $table => $columns) {
            $this->db->executeStatement("CREATE TABLE $table ($columns)");
        }
        $em = $this->createMock(EntityManager::class);
        $em->method('getConnection')->willReturn($this->db);
        $this->table = new ScenarioKnowledgeBaseTable($em);
        $this->anr = $this->createMock(Anr::class);
        $this->anr->method('getId')->willReturn(7);
        $this->anr->method('getCreator')->willReturn('analyst@example.test');
    }

    public function testOnlyPackagedSourcesAndTheirParentsBecomeEditableLocalRecords(): void
    {
        $assetType = $this->source('type', 'Packaged type');
        $referential = $this->source('framework', 'Packaged framework');
        $this->db->insert('vulnerabilities', ['uuid' => 'unrelated', 'anr_id' => 99, 'label1' => 'Other analysis']);
        $this->table->seedPackage($this->anr, [
            ['type' => 'asset', 'source' => $this->source('asset', 'Packaged asset') + ['parent' => $assetType]],
            ['type' => 'asset-type', 'source' => $assetType],
            ['type' => 'control', 'source' => $this->source('control', 'Packaged control') + ['parent' => $referential]],
            ['type' => 'referential', 'source' => $referential],
            ['type' => 'threat', 'source' => $this->source('threat', 'Packaged threat')],
            ['type' => 'vulnerability', 'source' => $this->source('vulnerability', 'Packaged vulnerability')],
            ['type' => 'risk-source', 'source' => $this->source('999', 'Packaged risk source')],
        ]);
        foreach (['assets', 'objects', 'referentials', 'measures', 'threats', 'vulnerabilities', 'risk_sources'] as $table) {
            self::assertSame(1, (int) $this->db->fetchOne("SELECT COUNT(*) FROM $table WHERE anr_id=7"));
        }
        self::assertSame('type', $this->db->fetchOne('SELECT asset_id FROM objects'));
        self::assertSame('framework', $this->db->fetchOne('SELECT referential_uuid FROM measures'));
        self::assertSame('Packaged asset', $this->db->fetchOne('SELECT name2 FROM objects'));
        self::assertSame('Description', $this->db->fetchOne('SELECT label2 FROM objects'));
        self::assertSame('Packaged vulnerability', $this->db->fetchOne('SELECT label2 FROM vulnerabilities WHERE anr_id=7'));
        self::assertNotSame(999, (int) $this->db->fetchOne('SELECT id FROM risk_sources'));
        self::assertTrue($this->table->hasLocalEntries($this->anr));
    }

    public function testRetryOrLaterPackageCannotOverwriteLocalEditsOrDuplicateRiskSources(): void
    {
        $links = [
            ['type' => 'vulnerability', 'source' => $this->source('vulnerability', 'Published label')],
            ['type' => 'risk-source', 'source' => $this->source('1', 'Risk source')],
        ];
        $this->table->seedPackage($this->anr, $links);
        $this->db->update('vulnerabilities', ['label1' => 'Local edit'], ['anr_id' => 7]);
        $this->table->seedPackage($this->anr, $links);
        self::assertSame('Local edit', $this->db->fetchOne('SELECT label1 FROM vulnerabilities'));
        self::assertSame(1, (int) $this->db->fetchOne('SELECT COUNT(*) FROM risk_sources'));
    }

    public function testAnExplicitEmptyPackageDoesNotFallBackToTheCommonKnowledgeBase(): void
    {
        $this->table->seedPackage($this->anr, []);
        self::assertFalse($this->table->hasLocalEntries($this->anr));
    }

    public function testInvalidParentRollsBackEverySeededRecord(): void
    {
        try {
            $this->table->seedPackage($this->anr, [
                ['type' => 'threat', 'source' => $this->source('threat', 'Threat')],
                ['type' => 'control', 'source' => $this->source('control', 'Control')],
            ]);
            self::fail('A missing referential must be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('parent snapshot', $exception->getMessage());
        }
        self::assertFalse($this->table->hasLocalEntries($this->anr));
    }

    /** @covers \Monarc\FrontOffice\Scenario\Service\ScenarioTemplateInstantiationService */
    public function testTemplateSelectionSeedsOnlyItsSnapshotAndRetryPreservesLocalEdits(): void
    {
        $analysisService = $this->analysisService();
        $common = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        require_once dirname(__DIR__, 4) . '/zm-core/tests/Fixture/ScenarioPackageDatabase.php';
        createScenarioPackageDatabase($common);
        $commonEm = $this->createMock(EntityManager::class);
        $commonEm->method('getConnection')->willReturn($common);
        $catalog = new CatalogTable($commonEm);
        $uuid = '00000000-0000-4000-8000-000000000001';
        $common->insert('scenario_catalog_templates', ['stable_uuid' => $uuid, 'version' => 1, 'lifecycle' => 'published']);
        $common->insert('scenario_catalog_template_translations', ['template_id' => 1, 'locale' => 'en', 'title' => 'Curated template']);
        $catalog->replaceTemplatePackage(1, [
            ['type' => 'vulnerability', 'sourceIdentity' => $uuid],
        ]);
        $catalog->captureTemplatePackage(1, new PackageSourceTable($commonEm), true);
        $em = $this->createMock(EntityManager::class);
        $em->method('getConnection')->willReturn($this->db);
        $users = $this->createMock(ConnectedUserService::class);
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(9);
        $users->method('getConnectedUser')->willReturn($user);
        $service = new ScenarioTemplateInstantiationService(
            $catalog,
            new ScenarioAnalysisTable($em),
            new ScenarioTemplateSnapshotTable($em),
            $analysisService,
            $users
        );
        $data = ['templateUuid' => $uuid, 'templateVersion' => 1];
        $result = $service->instantiate($this->anr, $data, null, 'initial-selection');
        self::assertNotNull($result);
        self::assertSame(0, $this->blankInitializations);
        self::assertSame(1, (int) $this->db->fetchOne('SELECT COUNT(*) FROM vulnerabilities'));
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM assets'));
        self::assertSame('Vulnerability', $this->db->fetchOne('SELECT label1 FROM vulnerabilities'));
        $this->db->update('vulnerabilities', ['label1' => 'Local evidence'], ['anr_id' => 7]);
        $common->update('vulnerabilities', ['label1' => 'Later BO edit'], ['uuid' => $uuid]);
        $retry = $service->instantiate($this->anr, $data, null, 'retry');
        self::assertSame($result, $retry);
        self::assertSame('Local evidence', $this->db->fetchOne('SELECT label1 FROM vulnerabilities'));
        self::assertSame(1, (int) $this->db->fetchOne('SELECT COUNT(*) FROM scenario_template_snapshots'));
        self::assertSame(2, (int) $this->db->fetchOne('SELECT COUNT(*) FROM scenario_audit_events'));
    }

    /** @covers \Monarc\FrontOffice\Scenario\Service\ScenarioAnalysisService */
    public function testBlankSelectionUsesTheGenericInitializerWhileAnEmptyPackageDoesNot(): void
    {
        $service = $this->analysisService();
        $service->create($this->anr, ['languageCode' => 'en'], 'blank');
        self::assertSame(1, $this->blankInitializations);
        $this->db->delete('scenario_analyses', ['anr_id' => 7]);
        $service->create($this->anr, ['languageCode' => 'en'], 'empty-package', null, []);
        self::assertSame(1, $this->blankInitializations);
        self::assertFalse($this->table->hasLocalEntries($this->anr));
    }

    public function testBlankSelectionPreservesAnExistingLocalKnowledgeBase(): void
    {
        $service = $this->analysisService();
        $this->table->seedPackage($this->anr, [
            ['type' => 'threat', 'source' => $this->source('local-threat', 'Local evidence')],
        ]);
        $service->create($this->anr, ['languageCode' => 'en'], 'existing-library');
        self::assertSame(0, $this->blankInitializations);
        self::assertSame('Local evidence', $this->db->fetchOne('SELECT label1 FROM threats'));
    }

    /** @dataProvider sourceAccessCases */
    public function testSourceAnalysisAuthorization(bool $exists, bool $direct, bool $supervisor): void
    {
        $anrs = $this->createMock(AnrTable::class);
        $source = $exists ? $this->createMock(Anr::class) : null;
        $anrs->expects(self::once())->method('findById')->with(42, false)->willReturn($source);
        $links = $this->createMock(UserAnrTable::class);
        $links->expects($exists ? self::once() : self::never())->method('findByAnrAndUser')
            ->willReturn($direct ? $this->createMock(UserAnr::class) : null);
        $supervisors = $this->createMock(AnrSupervisorService::class);
        $supervisors->expects($exists ? self::once() : self::never())->method('findLinkedSupervisor')
            ->willReturn($supervisor ? $this->createMock(AnrSupervisor::class) : null);
        $service = $this->analysisService($anrs, $links, $supervisors);
        $allowed = $exists && ($direct || $supervisor);
        try {
            $result = $service->create($this->anr, ['languageCode' => 'en', 'sourceAnrId' => 42], 'source');
            self::assertTrue($allowed, 'An inaccessible source must be rejected.');
            self::assertSame(42, $result['sourceAnrId']);
        } catch (ActionForbiddenException $exception) {
            self::assertFalse($allowed);
            self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM scenario_analyses'));
            self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM scenario_audit_events'));
            self::assertSame(0, $this->blankInitializations);
        }
    }

    public static function sourceAccessCases(): array
    {
        return [
            'direct access' => [true, true, false],
            'supervisor access' => [true, false, true],
            'unrelated user' => [true, false, false],
            'missing source' => [false, false, false],
        ];
    }

    /** @covers \Monarc\FrontOffice\Scenario\Table\ScenarioAnalysisTable */
    public function testInitializationFailureRollsBackTheAnalysisAndItsAudit(): void
    {
        $service = $this->analysisService();
        try {
            $service->create($this->anr, ['languageCode' => 'en'], 'bad-package', null, [
                ['type' => 'threat', 'source' => $this->source('temporary-threat', 'Rolled back')],
                ['type' => 'asset', 'source' => $this->source('asset', 'Missing parent')],
            ]);
            self::fail('Incomplete source snapshots must fail atomically.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('parent snapshot', $exception->getMessage());
        }
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM scenario_analyses'));
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM scenario_audit_events'));
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM threats'));
    }

    private function analysisService(
        ?AnrTable $anrs = null,
        ?UserAnrTable $links = null,
        ?AnrSupervisorService $supervisors = null
    ): ScenarioAnalysisService {
        foreach ([
            'scenario_analyses' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT, anr_id INT UNIQUE, lifecycle TEXT, owner_id INT, language_code TEXT, next_review_at TEXT, source_anr_id INT, revision INT DEFAULT 1, created_at TEXT, updated_at TEXT',
            'scenario_audit_events' => 'uuid TEXT, anr_id INT, actor_id INT, correlation_id TEXT, target_uuid TEXT, action TEXT, change_patch TEXT, justification TEXT',
            'scenario_template_snapshots' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT, anr_id INT, scenario_analysis_id INT, catalog_template_uuid TEXT, catalog_template_version INT, catalog_release_uuid TEXT, catalog_release_checksum TEXT, catalog_provenance TEXT, payload_json TEXT, created_by INT',
            'scenario_template_instance_records' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT, anr_id INT, snapshot_id INT, record_type TEXT, source_uuid TEXT, source_version INT, position INT, content_json TEXT, revision INT DEFAULT 1',
            'scenario_template_local_overrides' => 'anr_id INT, snapshot_id INT, instance_record_uuid TEXT, field_name TEXT, value_json TEXT, revision INT',
        ] as $table => $columns) {
            $this->db->executeStatement("CREATE TABLE $table ($columns)");
        }
        $em = $this->createMock(EntityManager::class);
        $em->method('getConnection')->willReturn($this->db);
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(9);
        $users = $this->createMock(ConnectedUserService::class);
        $users->method('getConnectedUser')->willReturn($user);
        $userTable = $this->createMock(UserTable::class);
        $userTable->method('findById')->willReturn($user);
        $anrService = $this->createMock(AnrService::class);
        $anrService->method('copyGenericScenarioKnowledgeBase')->willReturnCallback(function (): void {
            ++$this->blankInitializations;
        });

        return new ScenarioAnalysisService(
            new ScenarioAnalysisTable($em),
            $anrs ?? $this->createMock(AnrTable::class),
            $links ?? $this->createMock(UserAnrTable::class),
            $userTable,
            $users,
            $supervisors ?? $this->createMock(AnrSupervisorService::class),
            $anrService,
            $this->table
        );
    }

    private function source(string $identity, string $label): array
    {
        return ['identity' => $identity, 'label' => $label, 'description' => 'Description', 'code' => 'CODE'];
    }
}
