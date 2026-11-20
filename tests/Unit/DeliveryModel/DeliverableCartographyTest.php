<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFrontOfficeTests\Unit\DeliveryModel;

use Monarc\Core\Service\TranslateService;
use Monarc\FrontOffice\Service\DeliverableGenerationService;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/** @coversNothing */
class DeliverableCartographyTest extends TestCase
{
    public function testMissingRiskCountersRenderAsEmptyCellsWithoutPhp84Deprecations(): void
    {
        $reflection = new ReflectionClass(DeliverableGenerationService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $translator = $this->createMock(TranslateService::class);
        $translator->method('translate')->willReturnArgument(0);
        $reflection->getProperty('translateService')->setValue($service, $translator);
        $reflection->getProperty('currentLangAnrIndex')->setValue($service, 1);
        $reflection->getMethod('setStyles')->invoke($service);
        $word = new PhpWord();
        $reflection->getMethod('generateCartographyMap')->invoke($service, [
            'MxV' => [1, 2],
            'Impact' => [1],
            'riskInfo' => ['counters' => [1 => [1 => 1]]],
        ], $word->addSection(), [
            'riskType' => 'riskInfo',
            'axisX' => 'MxV',
            'axisY' => 'Impact',
            'labelAxisX' => 'TxV',
            'thresholds' => [1, 2],
        ]);
        $xml = $reflection->getMethod('getWordXmlFromWordObject')->invoke($service, $word);

        self::assertStringContainsString('Low risks', $xml);
        self::assertStringContainsString('<w:tbl>', $xml);
    }
}
