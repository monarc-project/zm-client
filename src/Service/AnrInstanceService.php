<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Model\Entity\InstanceSuperClass;
use Monarc\Core\Service\InstanceService;
use Monarc\FrontOffice\Model\Entity\InstanceMetadata;
use Monarc\FrontOffice\Model\Table\InstanceMetadataTable;
use Monarc\FrontOffice\Model\Table\InstanceTable;
use Monarc\FrontOffice\Model\Table\RecommandationRiskTable;
use Monarc\FrontOffice\Model\Table\RecommandationSetTable;
use Monarc\FrontOffice\Model\Table\RecommandationTable;
use Monarc\FrontOffice\Table\UserAnrTable;
use Monarc\FrontOffice\Service\Traits\RecommendationsPositionsUpdateTrait;
use Monarc\FrontOffice\Model\Entity\Anr;

// TODO: ...
class AnrInstanceService extends InstanceService
{
    use RecommendationsPositionsUpdateTrait;

    /** @var UserAnrTable */
    protected $userAnrTable;
    protected $instanceRiskTable;
    protected $instanceRiskOpTable;

    /** @var RecommandationRiskTable */
    protected $recommendationRiskTable;

    /** @var RecommandationTable */
    protected $recommendationTable;

    /** @var RecommandationSetTable */
    protected $recommendationSetTable;

    /** @var TranslationTable */
    protected $translationTable;

    /** @var InstanceMetadataTable */
    protected $instanceMetadataTable;

    public function delete($id)
    {
        /** @var InstanceTable $instanceTable */
        $instanceTable = $this->get('table');
        $anr = $instanceTable->findById($id)->getAnr();

        parent::delete($id);

        // Reset related recommendations positions to 0.
        $unlinkedRecommendations = $this->recommendationTable->findUnlinkedWithNotEmptyPositionByAnr($anr);
        $recommendationsToResetPositions = [];
        foreach ($unlinkedRecommendations as $unlinkedRecommendation) {
            $recommendationsToResetPositions[$unlinkedRecommendation->getUuid()] = $unlinkedRecommendation;
        }
        if (!empty($recommendationsToResetPositions)) {
            $this->resetRecommendationsPositions($anr, $recommendationsToResetPositions);
        }
    }

    protected function updateInstanceMetadataFromBrothers(InstanceSuperClass $instance): void
    {
        /** @var InstanceTable $table */
        $instanceMetadataTable = $this->get('instanceMetadataTable');
        $table = $this->get('table');
        $anr = $instance->getAnr();
        $brothers = $table->findGlobalSiblingsByAnrAndInstance($anr, $instance);
        if (!empty($brothers)) {
            $instanceBrother = current($brothers);
            $instancesMetadatasFromBrother = $instanceBrother->getInstanceMetadata();
            foreach ($instancesMetadatasFromBrother as $instanceMetadataFromBrother) {
                $metadata = $instanceMetadataFromBrother->getMetadata();
                $instanceMetadata = $instanceMetadataTable->findByInstanceAndMetadataField($instance, $metadata);
                if ($instanceMetadata === null) {
                    $instanceMetadata = (new InstanceMetadata())
                        ->setInstance($instance)
                        ->setAnrInstanceMetadataField($metadata)
                        ->setCommentTranslationKey($instanceMetadataFromBrother->getCommentTranslationKey())
                        ->setCreator($this->getConnectedUser()->getEmail());
                    $instanceMetadataTable->save($instanceMetadata, false);
                }
            }
            $instanceMetadataTable->flush();
        }
    }

    protected function getAnrLanguageCode(Anr $anr): string
    {
        return $this->get('configService')->getActiveLanguageCodes()[$anr->getLanguage()];
    }
}
