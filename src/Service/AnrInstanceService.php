<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\Traits\PositionUpdateTrait;
use Monarc\Core\Table\TranslationTable;
use Monarc\FrontOffice\Model\Entity;
use Monarc\FrontOffice\Model\Table as DeprecatedTable;
use Monarc\FrontOffice\Service\Traits\RecommendationsPositionsUpdateTrait;
use Monarc\FrontOffice\Table;

class AnrInstanceService
{
    use RecommendationsPositionsUpdateTrait;

    use PositionUpdateTrait;

    protected $instanceRiskTable;
    protected $instanceRiskOpTable;

    /** @var Table\RecommendationRiskTable */
    protected $recommendationRiskTable;

    /** @var Table\RecommendationTable */
    protected $recommendationTable;

    /** @var Table\RecommendationSetTable */
    protected $recommendationSetTable;

    /** @var TranslationTable */
    protected $translationTable;

    /** @var Table\InstanceMetadataTable */
    protected $instanceMetadataTable;

    // TODO: copied from the core
    public function instantiateObjectToAnr(Entity\Anr $anr, array $data, bool $isRootLevel = false): Entity\Instance
    {
        /** @var Entity\MonarcObject $object */
        $object = $data['object'] instanceof Entity\MonarcObject
            ? $data['object']
            : $this->monarcObjectTable->findByUuid($data['object']);

        $instance = (new Entity\Instance)
            ->setAnr($anr)
            ->setObject($object)
            ->setAsset($object->getAsset())
            ->setNames($object->getNames())
            ->setLabels($object->getLabels())
            ->setCreator($this->connectedUser->getEmail());

        if (!empty($data['parent'])) {
            /** @var Entity\Instance $parentInstance */
            $parentInstance = $data['parent'] instanceof Entity\Instance
                ? $data['parent']
                : $this->instanceTable->findByIdAndAnr($data['parent'], $anr);

            $instance->setParent($parentInstance)->setRoot($parentInstance->getRootInstance());
        }

        $this->updateInstanceLevels($isRootLevel, $instance);

        $this->updatePositions($instance, $this->instanceTable, $this->getPreparedPositionData($instance, $data));

        $this->instanceTable->save($instance);

        /* TODO: Used only on FO side. Can be removed. Kept not to forget to add there. */
        $this->updateAnrInstanceMetadataFieldFromBrothers($instance);

        $this->instanceConsequenceService->createInstanceConsequences($instance, $anr, $object);
        $instance->updateImpactBasedOnConsequences()->refreshInheritedImpact();

        $this->instanceTable->save($instance, false);

        $this->instanceRiskService->createInstanceRisks($instance, $object);

        $this->instanceRiskOpService->createInstanceRisksOp($instance, $object);

        /* Check if the root element is not the same as current child element to avoid a circular dependency. */
        if ($instance->isRoot()
            || !$instance->hasParent()
            || $instance->getParent()->isRoot()
            || $instance->getParent()->getRoot()->getObject()->getUuid() !== $instance->getObject()->getUuid()
        ) {
            $this->createChildren($instance);
        }

        return $instance;
    }
    public function delete(Entity\Anr $anr, int $id): void
    {
        /** @var Table\InstanceTable $instanceTable */
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

    protected function updateAnrInstanceMetadataFieldFromBrothers(Entity\Instance $instance): void
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
                    $instanceMetadata = (new Entity\AnrInstanceMetadataField())
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
}
