<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFrontOfficeTests\Unit\Entity;

use Monarc\Core\Entity\Anr as CoreAnr;
use Monarc\Core\Entity\Model;
use Monarc\FrontOffice\Entity\Anr;
use PHPUnit\Framework\TestCase;

/** @coversNothing */
class AnrTest extends TestCase
{
    /** @dataProvider modelDescriptions */
    public function testModelCreationAcceptsAnOptionalDescription(array $descriptionData, string $expected): void
    {
        $model = $this->createMock(Model::class);
        $model->method('getId')->willReturn(31);
        $source = (new CoreAnr())->setModel($model);

        $anr = Anr::constructFromObjectAndData($source, $descriptionData + [
            'label' => 'Created analysis',
            'language' => 2,
            'languageCode' => 'en',
        ]);

        self::assertSame($expected, $anr->getDescription());
        self::assertSame('Created analysis', $anr->getLabel());
        self::assertSame(2, $anr->getLanguage());
        self::assertSame('en', $anr->getLanguageCode());
        self::assertSame(31, $anr->getModelId());
    }

    public static function modelDescriptions(): iterable
    {
        yield 'omitted' => [[], ''];
        yield 'null' => [['description' => null], ''];
        yield 'empty' => [['description' => ''], ''];
        yield 'provided' => [['description' => 'Analysis description'], 'Analysis description'];
    }

    public function testAnalysisCopyPreservesItsSourceDescriptionWhenOmitted(): void
    {
        $source = (new Anr())
            ->setLabel('Source')
            ->setDescription('Source description')
            ->setLanguage(2)
            ->setLanguageCode('en');

        $copy = Anr::constructFromObjectAndData($source, ['label' => 'Copy']);

        self::assertSame('Copy', $copy->getLabel());
        self::assertSame('Source description', $copy->getDescription());
    }
}
