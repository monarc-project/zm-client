<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Tests\Unit\Import;

use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Import\Traits\ImportDataStructureAdapterTrait;
use PHPUnit\Framework\TestCase;

final class ImportDataStructureAdapterTraitTest extends TestCase
{
    public function testBuildsAnAcyclicCategoryParentHierarchy(): void
    {
        $result = $this->adapter()->adaptOldObjectDataStructureToNewFormat($this->oldObjectData([
            10 => ['id' => 10, 'label' => 'Child', 'parent' => 20],
            20 => ['id' => 20, 'label' => 'Root', 'parent' => null],
        ], 10), 1);

        self::assertSame('Child', $result['object']['category']['label']);
        self::assertSame('Root', $result['object']['category']['parent']['label']);
        self::assertNull($result['object']['category']['parent']['parent']);
    }

    public function testKeepsRootAndMissingParentsAsNull(): void
    {
        $rootCategory = $this->adapter()->adaptOldObjectDataStructureToNewFormat($this->oldObjectData([
            10 => ['id' => 10, 'label' => 'Root', 'parent' => 0],
        ], 10), 1);
        $orphanCategory = $this->adapter()->adaptOldObjectDataStructureToNewFormat($this->oldObjectData([
            10 => ['id' => 10, 'label' => 'Orphan', 'parent' => 20],
        ], 10), 1);

        self::assertNull($rootCategory['object']['category']['parent']);
        self::assertNull($orphanCategory['object']['category']['parent']);
    }

    public function testRejectsACyclicCategoryParentHierarchy(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(412);
        $this->expectExceptionMessage('category hierarchy contains a cycle');

        $this->adapter()->adaptOldObjectDataStructureToNewFormat($this->oldObjectData([
            10 => ['id' => 10, 'label' => 'First', 'parent' => 20],
            20 => ['id' => 20, 'label' => 'Second', 'parent' => 10],
        ], 10), 1);
    }

    public function testRejectsASelfReferencingCategory(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(412);

        $this->adapter()->adaptOldObjectDataStructureToNewFormat($this->oldObjectData([
            10 => ['id' => 10, 'label' => 'Self-referencing', 'parent' => 10],
        ], 10), 1);
    }

    private function adapter(): object
    {
        return new class {
            use ImportDataStructureAdapterTrait;
        };
    }

    private function oldObjectData(array $categories, int $categoryId): array
    {
        return [
            'object' => [
                'uuid' => '00000000-0000-0000-0000-000000000001',
                'name1' => 'Object name',
                'label1' => 'Object label',
                'mode' => 0,
                'scope' => 0,
                'category' => $categoryId,
            ],
            'categories' => $categories,
            'asset' => [
                'asset' => [],
                'amvs' => [],
            ],
        ];
    }
}
