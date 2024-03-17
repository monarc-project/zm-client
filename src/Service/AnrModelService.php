<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\ConfigService;
use Monarc\Core\Table as CoreTable;
use Monarc\Core\Model\Table as CoreDeprecatedTable;
use Monarc\Core\Entity as CoreEntity;
use Monarc\FrontOffice\Table;

class AnrModelService
{
    public function __construct(
        private Table\ClientTable $clientTable,
        private CoreTable\ModelTable $modelTable,
        private CoreTable\AssetTable $coreAssetTable,
        private CoreTable\ThreatTable $coreThreatTable,
        private CoreTable\VulnerabilityTable $coreVulnerabilityTable,
        private CoreTable\ScaleImpactTypeTable $coreScaleImpactTypeTable,
        private CoreDeprecatedTable\MeasureTable $coreMeasureTable,
        private CoreDeprecatedTable\RolfRiskTable $coreRolfRiskTable,
        private CoreDeprecatedTable\RolfTagTable $coreRolfTagTable,
        private CoreDeprecatedTable\QuestionTable $coreQuestionTable,
        private CoreDeprecatedTable\QuestionChoiceTable $coreQuestionChoiceTable,
        private ConfigService $configService
    ) {
    }

    public function getModelsListOfClient(): array
    {
        $result = [];
        $modelIds = [];
        foreach ($this->clientTable->findFirstClient()->getClientModels() as $clientModel) {
            $modelIds[] = $clientModel->getModelId();
        }
        foreach ($this->modelTable->fundGenericsAndSpecificsByIds($modelIds) as $model) {
            $result[] = array_merge(['id' => $model->getId()], $model->getLabels());
        }

        return $result;
    }

    /**
     * Returns an array that specifies in which languages the model can be instantiated.
     *
     * @return array The array of languages that are available for the model.
     *                  e.g. [1 => true, 2 => true, 3 => false, 4 => true]
     */
    public function getAvailableLanguages(int $modelId): array
    {
        $languages = array_keys($this->configService->getLanguageCodes());
        $result = [];
        foreach ($languages as $languageIndex) {
            $result[$languageIndex] = true;
        }

        /** @var CoreEntity\Model $model */
        $model = $this->modelTable->findById($modelId);
        $this->validateEntityLanguages($model, $result);

        /* Validates measures, rolf tags, rolf risks, questions and questions choices. */
        /** @var CoreEntity\Measure $measure */
        foreach ($this->coreMeasureTable->fetchAllObject() as $measure) {
            $this->validateEntityLanguages($measure, $result);
        }
        /** @var CoreEntity\RolfRisk $rolfRisk */
        foreach ($this->coreRolfRiskTable->fetchAllObject() as $rolfRisk) {
            $this->validateEntityLanguages($rolfRisk, $result);
        }
        /** @var CoreEntity\RolfTag $rolfTag */
        foreach ($this->coreRolfTagTable->fetchAllObject() as $rolfTag) {
            $this->validateEntityLanguages($rolfTag, $result);
        }
        /** @var CoreEntity\Question $question */
        foreach ($this->coreQuestionTable->fetchAllObject() as $question) {
            $this->validateEntityLanguages($question, $result);
        }
        /** @var CoreEntity\QuestionChoice $questionChoice */
        foreach ($this->coreQuestionChoiceTable->fetchAllObject() as $questionChoice) {
            $this->validateEntityLanguages($questionChoice, $result);
        }

        /** @var CoreEntity\ScaleImpactType $scaleImpactType */
        foreach ($this->coreScaleImpactTypeTable->findByAnr($model->getAnr()) as $scaleImpactType) {
            $this->validateEntityLanguages($scaleImpactType, $result);
        }

        /* Generic assets validation. */
        foreach ($this->coreAssetTable->findByMode(CoreEntity\AssetSuperClass::MODE_GENERIC) as $asset) {
            $this->validateEntityLanguages($asset, $result);
        }
        /* Generic threats and themes validation. */
        foreach ($this->coreThreatTable->findByMode(CoreEntity\ThreatSuperClass::MODE_GENERIC) as $threat) {
            foreach ($languages as $languageIndex) {
                if (empty($threat->getLabel($languageIndex))
                    || ($threat->getTheme() !== null
                        && empty($threat->getTheme()->getLabel($languageIndex))
                    )
                ) {
                    $result[$languageIndex] = false;
                }
            }
        }
        /* Generic vulnerabilities validation. */
        $vulnerabilities = $this->coreVulnerabilityTable->findByMode(CoreEntity\VulnerabilitySuperClass::MODE_GENERIC);
        foreach ($vulnerabilities as $vulnerability) {
            $this->validateEntityLanguages($vulnerability, $result);
        }
        /* If the models is specific, the linked objects have to be validated as well. */
        if (!$model->isGeneric()) {
            foreach ($model->getAssets() as $specificAsset) {
                $this->validateEntityLanguages($specificAsset, $result);
            }
            foreach ($model->getThreats() as $specificThreat) {
                foreach ($languages as $languageIndex) {
                    if (empty($specificThreat->getLabel($languageIndex))
                        || ($specificThreat->getTheme() !== null
                            && empty($specificThreat->getTheme()->getLabel($languageIndex))
                        )
                    ) {
                        $result[$languageIndex] = false;
                    }
                }
            }
            foreach ($model->getVulnerabilities() as $specificVulnerability) {
                $this->validateEntityLanguages($specificVulnerability, $result);
            }
        }

        /* Validates monarc objects. */
        /** @var CoreEntity\MonarcObject $monarcObject */
        foreach ($model->getAnr() ? $model->getAnr()->getObjects() : [] as $monarcObject) {
            foreach ($languages as $languageIndex) {
                if (empty($monarcObject->getLabel($languageIndex))
                    || empty($monarcObject->getName($languageIndex))
                    || ($monarcObject->getCategory() !== null
                        && empty($monarcObject->getCategory()->getLabel($languageIndex))
                    )
                ) {
                    $result[$languageIndex] = false;
                }
            }
        }

        return $result;
    }

    /**
     * @param object $entity
     * @param array $resultLanguages e.g. [1 => true, 2 => false, 3 => true, 4 => false]
     */
    private function validateEntityLanguages(object $entity, array &$resultLanguages): void
    {
        foreach (array_keys($resultLanguages) as $languageIndex) {
            if (method_exists($entity, 'getLabel') && empty($entity->getLabel($languageIndex))) {
                $resultLanguages[$languageIndex] = false;
            }
        }
    }
}
