<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Processor;

use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Import\Traits\EvaluationConverterTrait;
use Monarc\FrontOffice\Service\SoaScaleCommentService;
use Monarc\FrontOffice\Service\SoaService;
use Monarc\FrontOffice\Table\SoaScaleCommentTable;
use Monarc\FrontOffice\Table\SoaTable;

class SoaImportProcessor
{
    use EvaluationConverterTrait;

    private UserSuperClass $connectedUser;

    public function __construct(
        private SoaTable $soaTable,
        private SoaScaleCommentTable $soaScaleCommentTable,
        private ImportCacheHelper $importCacheHelper,
        private SoaService $soaService,
        private SoaScaleCommentService $soaScaleCommentService,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function processSoasData(Entity\Anr $anr, array $soasData): void
    {
        foreach ($soasData as $soaData) {
            $this->processSoaData($anr, $soaData);
        }
    }

    public function mergeSoaScaleComments(Entity\Anr $anr, array $newSoaScaleCommentsData, bool $isOldStructure): void
    {
        $currentSoaScaleCommentsNumber = 0;
        $newSoaScaleCommentsNumber = \count($newSoaScaleCommentsData);
        /** @var Entity\SoaScaleComment $currentSoaScaleComment */
        foreach ($this->soaScaleCommentTable->findByAnrIndexedByScaleIndex($anr) as $currentSoaScaleComment) {
            $soaScaleCommentKey = array_search(
                $currentSoaScaleComment->getScaleIndex(),
                array_column($newSoaScaleCommentsData, 'scaleIndex'),
                true
            );
            if (!$currentSoaScaleComment->isHidden()) {
                $currentSoaScaleCommentsNumber++;
            }
            if ($soaScaleCommentKey === false && !$currentSoaScaleComment->isHidden()) {
                $currentSoaScaleComment->setIsHidden(true);
            } elseif ($soaScaleCommentKey !== false) {
                $newSoaScaleCommentData = $newSoaScaleCommentsData[$soaScaleCommentKey];
                if ($currentSoaScaleComment->getColour() !== $newSoaScaleCommentData['colour'] ||
                    $currentSoaScaleComment->getComment() !== $newSoaScaleCommentData['comment']
                ) {
                    $currentSoaScaleComment->setColour($newSoaScaleCommentData['colour'])
                        ->setComment($newSoaScaleCommentData['comment'])
                        ->setIsHidden(false)
                        ->setUpdater($this->connectedUser->getEmail());
                    $this->soaScaleCommentTable->save($currentSoaScaleComment, false);
                }
                $this->importCacheHelper->addItemToArrayCache(
                    'soa_scale_comments',
                    $currentSoaScaleComment,
                    $currentSoaScaleComment->getScaleIndex()
                );
            }
        }

        /* Create new scales if the new comments number is more than current ones. */
        if ($newSoaScaleCommentsNumber > $currentSoaScaleCommentsNumber) {
            for ($index = $currentSoaScaleCommentsNumber; $index < $newSoaScaleCommentsNumber; $index++) {
                $soaScaleCommentKey = array_search($index, array_column($newSoaScaleCommentsData, 'scaleIndex'), true);
                if ($soaScaleCommentKey !== false) {
                    $newSoaScaleComment = $this->soaScaleCommentService->createSoaScaleComment(
                        $anr,
                        $index,
                        $newSoaScaleCommentsData[$soaScaleCommentKey]['colour'],
                        $newSoaScaleCommentsData[$soaScaleCommentKey]['comment']
                    );
                    $this->importCacheHelper->addItemToArrayCache('soa_scale_comments', $newSoaScaleComment, $index);
                }
            }
        }

        /* Adjust the existing SOA's scales comments indexes to the new number of the comments' level. */
        if ($currentSoaScaleCommentsNumber !== $newSoaScaleCommentsNumber) {
            $this->adjustSoasWithScaleCommentsChanges(
                $anr,
                $currentSoaScaleCommentsNumber - 1,
                $newSoaScaleCommentsNumber - 1
            );
        }

        /* To make exported prior v2.13.1 files working, a map with soa_scale_comments_ids_to_indexes is created. */
        if ($isOldStructure) {
            foreach ($newSoaScaleCommentsData as $oldId => $newSoaScaleCommentData) {
                $this->importCacheHelper->addItemToArrayCache(
                    'soa_scale_comments_ids_to_indexes',
                    $newSoaScaleCommentData['scaleIndex'],
                    $oldId
                );
            }
        }
    }

    private function processSoaData(Entity\Anr $anr, array $soaData): Entity\Soa
    {
        /* Support the old structure field name prior v2.13.1. */
        $measureUuid = $soaData['measure_id'] ?? $soaData['measureUuid'];
        $soa = $this->getSoaFromCache($anr, $measureUuid);
        /* New SOAs were created and cached during the new measures process, and existed SOAs cache also initialised. */
        if ($soa !== null) {
            if (isset($soaData['soaScaleComment'])) {
                /* Support the old structure field name prior v2.13.1. */
                $soaData['soaScaleCommentIndex'] = $this->importCacheHelper->getItemFromArrayCache(
                    'soa_scale_comments_ids_to_indexes',
                    $soaData['soaScaleComment']
                );
            }
            if (isset($soaData['soaScaleCommentIndex']) && $soaData['soaScaleCommentIndex'] !== null) {
                /* New structure from v2.13.1 or set workaround for the old export. */
                $soaData['soaScaleComment'] = $this->importCacheHelper->getItemFromArrayCache(
                    'soa_scale_comments',
                    $soaData['soaScaleCommentIndex']
                );
            }
            $this->soaService->patchSoaObject($anr, $soa, $soaData, false);
        }

        return $soa;
    }

    private function adjustSoasWithScaleCommentsChanges(
        Entity\Anr $anr,
        int $currentMaxSoaScaleIndex,
        int $newMaxSoaScaleIndex
    ): void {
        foreach ($this->soaTable->findByAnrWithNotEmptySoaScaleComments($anr) as $soa) {
            /** @var Entity\SoaScaleComment $soaComment */
            $soaComment = $soa->getSoaScaleComment();
            $newScaleIndex = $this->convertValueWithinNewScalesRange(
                $soaComment->getScaleIndex(),
                0,
                $currentMaxSoaScaleIndex,
                0,
                $newMaxSoaScaleIndex,
                0
            );
            if ($soaComment->getScaleIndex() !== $newScaleIndex
                && $this->importCacheHelper->isItemInArrayCache('soa_scale_comments', $newScaleIndex)
            ) {
                $soa->setSoaScaleComment(
                    $this->importCacheHelper->getItemFromArrayCache('soa_scale_comments', $newScaleIndex)
                );
                $this->soaTable->save($soa, false);
            }
        }
    }

    private function getSoaFromCache(Entity\Anr $anr, string $measureUuid): ?Entity\Soa
    {
        if (!$this->importCacheHelper->isCacheKeySet('is_soa_cache_loaded')) {
            $this->importCacheHelper->addItemToArrayCache('is_soa_cache_loaded', true);
            /** @var Entity\Soa $soa */
            foreach ($this->soaTable->findByAnr($anr) as $soa) {
                $this->importCacheHelper->addItemToArrayCache(
                    'soas_by_measure_uuids',
                    $soa,
                    $soa->getMeasure()->getUuid()
                );
            }
        }

        return $this->importCacheHelper->getItemFromArrayCache('soas_by_measure_uuids', $measureUuid);
    }
}
