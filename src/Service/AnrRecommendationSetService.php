<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */
namespace Monarc\FrontOffice\Service;

use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\RecommendationSet;
use Monarc\FrontOffice\Table\RecommendationSetTable;
use Monarc\FrontOffice\Service\Traits\RecommendationsPositionsUpdateTrait;
use Monarc\FrontOffice\Table\RecommendationTable;

class AnrRecommendationSetService
{
    use RecommendationsPositionsUpdateTrait;

    private UserSuperClass $connectedUser;

    public function __construct(
        private RecommendationSetTable $recommendationSetTable,
        private RecommendationTable $recommendationTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getList(Anr $anr)
    {
        $recommendationSetsList = [];
        /** @var RecommendationSet $recommendationSet */
        foreach ($this->recommendationSetTable->findByAnr($anr) as $recommendationSet) {
            $recommendationSetsList[] = $this->getPreparedRecommendationSetData($recommendationSet);
        }

        return $recommendationSetsList;
    }

    public function getRecommendationSetData(Anr $anr, string $uuid): array
    {
        /** @var RecommendationSet $recommendationSet */
        $recommendationSet = $this->recommendationSetTable->findByUuidAndAnr($uuid, $anr);

        return $this->getPreparedRecommendationSetData($recommendationSet);
    }

    public function create(Anr $anr, array $data, bool $saveInDb = true): RecommendationSet
    {
        $recommendationSet = (new RecommendationSet())
            ->setAnr($anr)
            ->setLabel($data['label'])
            ->setCreator($this->connectedUser->getEmail());
        if (!empty($data['uuid'])) {
            /* The UUID is set only when it's imported from MOSP or duplicated on anr creation. */
            $recommendationSet->setUuid($data['uuid']);
        }

        $this->recommendationSetTable->save($recommendationSet, $saveInDb);

        return $recommendationSet;
    }

    public function getOrCreate(Anr $anr, array $data, bool $saveInDb = true): RecommendationSet
    {
        if (!empty($data['uuid'])) {
            /** @var RecommendationSet|null $recommendationSet */
            $recommendationSet = $this->recommendationSetTable->findByUuidAndAnr($data['uuid'], $anr, false);
            if ($recommendationSet !== null) {
                return $recommendationSet;
            }
        }

        return $this->create($anr, $data, $saveInDb);
    }

    public function patch(Anr $anr, string $uuid, array $data): RecommendationSet
    {
        /** @var RecommendationSet $recommendationSet */
        $recommendationSet = $this->recommendationSetTable->findByUuidAndAnr($uuid, $anr);
        if ($data['label'] !== $recommendationSet->getLabel()) {
            $recommendationSet->setLabel($data['label'])
                ->setUpdater($this->connectedUser->getEmail());

            $this->recommendationSetTable->save($recommendationSet);
        }

        return $recommendationSet;
    }

    public function delete(Anr $anr, string $uuid): void
    {
        /** @var RecommendationSet $recommendationSet */
        $recommendationSet = $this->recommendationSetTable->findByUuidAndAnr($uuid, $anr);

        $recommendationsToResetPositions = [];
        foreach ($recommendationSet->getRecommendations() as $recommendation) {
            if (!$recommendation->isPositionEmpty()) {
                $recommendationsToResetPositions[$recommendation->getUuid()] = $recommendation;
            }
        }
        if (!empty($recommendationsToResetPositions)) {
            $this->resetRecommendationsPositions($anr, $recommendationsToResetPositions);
        }

        $this->recommendationSetTable->remove($recommendationSet);
    }

    private function getPreparedRecommendationSetData($recommendationSet): array
    {
        return [
            'uuid' => $recommendationSet->getUuid(),
            'label' => $recommendationSet->getLabel(),
        ];
    }
}
