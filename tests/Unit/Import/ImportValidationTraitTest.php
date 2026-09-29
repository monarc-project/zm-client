<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Tests\Unit\Import;

use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Import\Traits\ImportValidationTrait;
use PHPUnit\Framework\TestCase;

final class ImportValidationTraitTest extends TestCase
{
    public function testMissingVersionIsRejected(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Import of files exported from MONARC v2.8.1 or lower are not supported.');

        $this->adapter()->setVersion([]);
    }

    public function testMasterVersionUsesTheLatestSupportedImportStructure(): void
    {
        self::assertSame('999', $this->adapter()->setVersion(['monarc_version' => 'master']));
    }

    public function testVersionedLegacyImportsKeepTheirVersion(): void
    {
        self::assertSame('2.13.0', $this->adapter()->setVersion(['monarc_version' => '2.13.0']));
    }

    private function adapter(): object
    {
        return new class {
            use ImportValidationTrait;

            public function setVersion(array $data): string
            {
                $this->setAndValidateImportingDataVersion($data);

                return $this->importingDataVersion;
            }
        };
    }
}
