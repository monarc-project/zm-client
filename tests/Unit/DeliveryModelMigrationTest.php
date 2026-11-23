<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFrontOfficeTests\Unit;

use Phinx\Db\Adapter\SQLiteAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\NullOutput;

/** @coversNothing */
class DeliveryModelMigrationTest extends TestCase
{
    public function testTemplateCleanupPreservesDataAndLeavesGeneratedDeliveryOwnershipIntact(): void
    {
        require_once dirname(__DIR__, 2) . '/migrations/db/'
            . '20261029112000_drop_unused_frontoffice_delivery_model_anr_column.php';
        $adapter = new SQLiteAdapter(['name' => ':memory:'], null, new NullOutput());
        $pdo = $adapter->getConnection();
        $pdo->exec('CREATE TABLE deliveries_models (
            id INTEGER PRIMARY KEY, anr_id INTEGER, category INTEGER, description1 TEXT, path1 TEXT
        )');
        $pdo->exec('CREATE INDEX templates_anr ON deliveries_models (anr_id)');
        $pdo->exec('CREATE TABLE deliveries (id INTEGER PRIMARY KEY, anr_id INTEGER, name TEXT)');
        $pdo->exec("INSERT INTO deliveries_models VALUES (1, NULL, 1, 'Default template', './default.docx')");
        $pdo->exec("INSERT INTO deliveries_models VALUES (2, 7, 2, 'Custom template', './custom.docx')");
        $pdo->exec("INSERT INTO deliveries VALUES (3, 7, 'Existing generated document')");
        $pdo->exec('CREATE TABLE questions (id INTEGER PRIMARY KEY)');
        $pdo->exec('CREATE TABLE questions_choices (id INTEGER PRIMARY KEY, question_id INTEGER)');
        $migration = new \DropUnusedFrontofficeDeliveryModelAnrColumn(
            'testing',
            20261029112000,
            null,
            new NullOutput()
        );
        $migration->setAdapter($adapter);
        $migration->up();
        $migration->up();
        self::assertFalse($adapter->hasColumn('deliveries_models', 'anr_id'));
        self::assertTrue($adapter->hasColumn('deliveries', 'anr_id'));
        self::assertSame([
            ['id' => 1, 'category' => 1, 'description1' => 'Default template', 'path1' => './default.docx'],
            ['id' => 2, 'category' => 2, 'description1' => 'Custom template', 'path1' => './custom.docx'],
        ], $pdo->query('SELECT * FROM deliveries_models ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC));
        self::assertSame(
            ['id' => 3, 'anr_id' => 7, 'name' => 'Existing generated document'],
            $pdo->query('SELECT * FROM deliveries')->fetch(\PDO::FETCH_ASSOC)
        );
    }
}
