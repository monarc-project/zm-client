<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Tests\Unit\Scenario;

use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Scenario\Service\OperationalRiskCriteriaAdapter;
use Monarc\FrontOffice\Service\OperationalRiskScaleService;
use PHPUnit\Framework\TestCase;

final class OperationalRiskCriteriaAdapterTest extends TestCase
{
    public function testItMapsCurrentOperationalScalesWithoutWritingThem(): void
    {
        $service = $this->createMock(OperationalRiskScaleService::class);
        $service->expects(self::once())->method('getOperationalRiskScales')->willReturn([
            [
                'id' => 10,
                'type' => 2,
                'min' => 0,
                'max' => 4,
                'comments' => $this->comments(10),
                'scaleTypes' => [],
            ],
            [
                'id' => 11,
                'type' => 1,
                'min' => 0,
                'max' => 4,
                'comments' => [],
                'scaleTypes' => [[
                    'id' => 12,
                    'label' => 'Availability',
                    'isHidden' => false,
                    'comments' => $this->comments(11, 12),
                ]],
            ],
        ]);
        $anr = $this->createMock(Anr::class);
        $anr->method('getId')->willReturn(78);
        $anr->method('getLanguageCode')->willReturn('en');
        $anr->method('getSeuilRolf1')->willReturn(6);
        $anr->method('getSeuilRolf2')->willReturn(12);

        $source = (new OperationalRiskCriteriaAdapter($service))->source($anr);

        self::assertTrue($source['complete']);
        self::assertSame(78, $source['sourceAnrId']);
        self::assertSame([0, 1, 2, 3, 4], array_column($source['likelihoodLevels'], 'value'));
        self::assertSame(10, $source['likelihoodLevels'][0]['source']['scaleId']);
        self::assertSame(0, $source['likelihoodLevels'][0]['source']['scaleIndex']);
        self::assertSame(0, $source['likelihoodLevels'][0]['source']['scaleValue']);
        self::assertSame('Availability', $source['dimensions'][0]['label']);
        self::assertSame(12, $source['dimensions'][0]['source']['scaleTypeId']);
        self::assertSame(['low' => 6, 'high' => 12], $source['thresholds']);

        $draft = (new OperationalRiskCriteriaAdapter($service))->draftInput($source);
        self::assertSame(['low' => 6, 'high' => 12], $draft['bands']['thresholds']);
    }

    public function testItGuidesTheWriterWhenTheOperationalScaleIsIncomplete(): void
    {
        $service = $this->createMock(OperationalRiskScaleService::class);
        $service->method('getOperationalRiskScales')->willReturn([
            [
                'id' => 10,
                'type' => 2,
                'min' => 0,
                'max' => 4,
                'comments' => array_slice($this->comments(10), 0, 4),
                'scaleTypes' => [],
            ],
        ]);
        $anr = $this->createMock(Anr::class);
        $anr->method('getId')->willReturn(78);
        $anr->method('getLanguageCode')->willReturn('en');

        $source = (new OperationalRiskCriteriaAdapter($service))->source($anr);

        self::assertFalse($source['complete']);
        self::assertNotEmpty($source['guidance']);
    }

    /** @return array<int, array<string, int|string|null>> */
    private function comments(int $scaleId, ?int $scaleTypeId = null): array
    {
        return array_map(static fn (int $value): array => [
            'id' => $scaleId * 100 + $value,
            'scaleId' => $scaleId,
            'scaleTypeId' => $scaleTypeId,
            'scaleIndex' => $value,
            'scaleValue' => $value,
            'comment' => 'Level ' . $value,
        ], [0, 1, 2, 3, 4]);
    }
}
