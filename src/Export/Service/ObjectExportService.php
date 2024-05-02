<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Service;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Helper\EncryptDecryptHelperTrait;
use Monarc\Core\Service\ConfigService;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Export\Service\Traits as ExportTrait;
use Monarc\FrontOffice\Table;

class ObjectExportService
{
    use EncryptDecryptHelperTrait;
    use ExportTrait\InformationRiskExportTrait;
    use ExportTrait\OperationalRiskExportTrait;
    use ExportTrait\ObjectExportTrait;

    public function __construct(
        private Table\MonarcObjectTable $monarcObjectTable,
        private ConfigService $configService
    ) {
    }

    /**
     * @return array Result contains:
     * [
     *     'filename' => {the generated filename},
     *     'content' => {json encoded string, encrypted if password is set}
     * ]
     */
    public function export(Entity\Anr $anr, array $exportParams): array
    {
        if (empty($exportParams['id'])) {
            throw new Exception('Object ID is required for the export operation.', 412);
        }

        /** @var Entity\MonarcObject $monarcObject */
        $monarcObject = $this->monarcObjectTable->findByUuidAndAnr($exportParams['id'], $anr);

        $isForMosp = !empty($exportParams['mosp']);
        $jsonResult = json_encode(
            $isForMosp ? $this->prepareExportDataForMosp($monarcObject) : $this->prepareExportData($monarcObject),
            JSON_THROW_ON_ERROR
        );

        return [
            'filename' => preg_replace(
                "/[^a-z0-9._-]+/i", '', $monarcObject->getName($anr->getLanguage()) . ($isForMosp ? '_MOSP' : '')
            ),
            'content' => empty($exportParams['password'])
                ? $jsonResult
                : $this->encrypt($jsonResult, $exportParams['password']),
        ];
    }

    private function prepareExportData(Entity\MonarcObject $monarcObject)
    {
        $anr = $monarcObject->getAnr();

        return [
            'type' => 'object',
            'monarc_version' => $this->configService->getAppVersion()['appVersion'],
            'languageCode' => $anr->getLanguageCode(),
            'languageIndex' => $anr->getLanguage(),
            'object' => $this->prepareObjectData($monarcObject, $anr->getLanguage(), true),
        ];
    }

    private function prepareExportDataForMosp(Entity\MonarcObject $monarcObject): array
    {
        $languageIndex = $monarcObject->getAnr()->getLanguage();
        $languageCode = $monarcObject->getAnr()->getLanguageCode();
        /** @var Entity\Asset $asset */
        $asset = $monarcObject->getAsset();
        $rolfTag = $monarcObject->getRolfTag();
        $rolfRisksData = [];
        if ($rolfTag !== null) {
            foreach ($rolfTag->getRisks() as $rolfRisk) {
                $rolfRisksData[] = $this->prepareOperationalRiskDataForMosp($rolfRisk, $languageIndex);
            }
        }
        $childrenObjects = [];
        foreach ($monarcObject->getChildren() as $childObject) {
            $childrenObjects[] = $this->prepareExportDataForMosp($childObject);
        }

        return ['object' => [
            'object' => [
                'uuid' => $monarcObject->getUuid(),
                'scope' => $monarcObject->getScopeName(),
                'name' => $monarcObject->getName($languageIndex),
                'label' => $monarcObject->getLabel($languageIndex),
                'language' => $languageCode,
                'version' => 1,
            ],
            'asset' => $asset !== null ? $this->prepareAssetDataForMosp($asset) : null,
            'rolfRisks' => $rolfRisksData,
            'rolfTags' => $rolfTag !== null
                ? [['code' => $rolfTag->getCode(), 'label' => $rolfTag->getLabel($languageIndex)]]
                : [],
            'children' => $childrenObjects,
        ]];
    }

    private function prepareAssetDataForMosp(Entity\Asset $asset): array
    {
        $languageIndex = $asset->getAnr()->getLanguage();
        $languageCode = $asset->getAnr()->getLanguageCode();

        $assetData = [
            'asset' => [
                'uuid' => $asset->getUuid(),
                'label' => $asset->getLabel($languageIndex),
                'description' => $asset->getDescription($languageIndex),
                'type' => $asset->getTypeName(),
                'code' => $asset->getCode(),
                'language' => $languageCode,
                'version' => 1,
            ],
            'amvs' => [],
            'threats' => [],
            'vuls' => [],
            'measures' => [],
        ];

        foreach ($asset->getAmvs() as $amv) {
            $amvResult = $this->prepareAmvsDataForMosp($amv, $languageIndex, $languageCode);
            $assetData['amvs'] += $amvResult['amv'];
            $assetData['threats'] += $amvResult['threat'];
            $assetData['vuls'] += $amvResult['vulnerability'];
            $assetData['measures'] += $amvResult['measures'];
        }
        $assetData['amvs'] = array_values($assetData['amvs']);
        $assetData['threats'] = array_values($assetData['threats']);
        $assetData['vuls'] = array_values($assetData['vuls']);
        $assetData['measures'] = array_values($assetData['measures']);

        return $assetData;
    }

    private function prepareAmvsDataForMosp(Entity\Amv $amv, int $languageIndex, string $languageCode): array
    {
        $measuresData = [];
        foreach ($amv->getMeasures() as $measure) {
            $measureUuid = $measure->getUuid();
            $measuresData[] = [
                'uuid' => $measureUuid,
                'code' => $measure->getCode(),
                'label' => $measure->getLabel($languageIndex),
                'category' => $measure->getCategory()?->getLabel($languageIndex),
                'referential' => $measure->getReferential()->getUuid(),
                'referential_label' => $measure->getReferential()->getLabel($languageIndex),
            ];
        }
        $threat = $amv->getThreat();
        $vulnerability = $amv->getVulnerability();

        return [
            'amv' => [
                $amv->getUuid() => [
                    'uuid' => $amv->getUuid(),
                    'asset' => $amv->getAsset()->getUuid(),
                    'threat' => $threat->getUuid(),
                    'vulnerability' => $vulnerability->getUuid(),
                    'measures' => array_keys($measuresData),
                ],
            ],
            'threat' => [
                $threat->getUuid() => [
                    'uuid' => $threat->getUuid(),
                    'label' => $threat->getLabel($languageIndex),
                    'description' => $threat->getDescription($languageIndex),
                    'theme' => $threat->getTheme() !== null
                        ? $threat->getTheme()->getLabel($languageIndex)
                        : '',
                    'code' => $threat->getCode(),
                    'c' => (bool)$threat->getConfidentiality(),
                    'i' => (bool)$threat->getIntegrity(),
                    'a' => (bool)$threat->getAvailability(),
                    'language' => $languageCode,
                ],
            ],
            'vulnerability' => [
                $vulnerability->getUuid() => [
                    'uuid' => $vulnerability->getUuid(),
                    'code' => $vulnerability->getCode(),
                    'label' => $vulnerability->getLabel($languageIndex),
                    'description' => $vulnerability->getDescription($languageIndex),
                    'language' => $languageCode,
                ],
            ],
            'measures' => $measuresData,
        ];
    }

    private function prepareOperationalRiskDataForMosp(Entity\RolfRisk $rolfRisk, int $languageIndex): array
    {
        $measuresData = [];
        foreach ($rolfRisk->getMeasures() as $measure) {
            $measuresData[] = [
                'uuid' => $measure->getUuid(),
                'code' => $measure->getCode(),
                'label' => $measure->getLabel($languageIndex),
                'category' => $measure->getCategory()?->getLabel($languageIndex),
                'referential' => $measure->getReferential()->getUuid(),
                'referential_label' => $measure->getReferential()->getLabel($languageIndex),
            ];
        }

        return [
            'code' => $rolfRisk->getCode(),
            'label' => $rolfRisk->getLabel($languageIndex),
            'description' => $rolfRisk->getDescription($languageIndex),
            'measures' => $measuresData,
        ];
    }
}
