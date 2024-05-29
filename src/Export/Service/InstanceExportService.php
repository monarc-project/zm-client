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

class InstanceExportService
{
    use EncryptDecryptHelperTrait;
    use ExportTrait\ObjectExportTrait;
    use ExportTrait\InstanceExportTrait;
    use ExportTrait\ScaleExportTrait;
    use ExportTrait\OperationalRiskScaleExportTrait;

    public function __construct(
        private Table\InstanceTable $instanceTable,
        private Table\ScaleTable $scaleTable,
        private Table\OperationalRiskScaleTable $operationalRiskScaleTable,
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
            throw new Exception('Instance ID is required for the export operation.', 412);
        }

        /** @var Entity\Instance $instance */
        $instance = $this->instanceTable->findByIdAndAnr((int)$exportParams['id'], $anr);

        $jsonResult = json_encode($this->prepareExportData($instance, $exportParams), JSON_THROW_ON_ERROR);

        return [
            'filename' => preg_replace("/[^a-z0-9\._-]+/i", '', $instance->getName($anr->getLanguage())),
            'content' => empty($exportParams['password'])
                ? $jsonResult
                : $this->encrypt($jsonResult, $exportParams['password']),
        ];
    }

    private function prepareExportData(Entity\Instance $instance, array $exportParams): array
    {
        $withEval = !empty($exportParams['assessments']);
        $withControls = $withEval && !empty($exportParams['controls']);
        $withRecommendations = $withEval && !empty($exportParams['recommendations']);
        /** @var Entity\Anr $anr */
        $anr = $instance->getAnr();
        $languageIndex = $anr->getLanguage();

        return [
            'type' => 'instance',
            'monarc_version' => $this->configService->getAppVersion()['appVersion'],
            'export_datetime' => (new \DateTime())->format('Y-m-d H:i:s'),
            'languageCode' => $anr->getLanguageCode(),
            'languageIndex' => $anr->getLanguage(),
            'with_eval' => $withEval,
            'with_controls' => $withControls,
            'with_recommendations' => $withRecommendations,
            'instance' => $this->prepareInstanceData(
                $instance,
                $languageIndex,
                true,
                true,
                $withEval,
                $withControls,
                $withRecommendations
            ),
            'scales' => $withEval ? $this->prepareScalesData($anr, $languageIndex) : [],
            'operationalRiskScales' => $withEval ? $this->prepareOperationalRiskScalesData($anr) : [],
        ];
    }

    private function prepareScalesData(Entity\Anr $anr, int $languageIndex): array
    {
        $result = [];
        /** @var Entity\Scale $scale */
        foreach ($this->scaleTable->findByAnr($anr) as $scale) {
            $result[$scale->getType()] = $this->prepareScaleData($scale, $languageIndex);
        }

        return $result;
    }

    private function prepareOperationalRiskScalesData(Entity\Anr $anr): array
    {
        $result = [];
        /** @var Entity\OperationalRiskScale $operationalRiskScale */
        foreach ($this->operationalRiskScaleTable->findByAnr($anr) as $operationalRiskScale) {
            $result[$operationalRiskScale->getType()] = $this->prepareOperationalRiskScaleData($operationalRiskScale);
        }

        return $result;
    }
}
