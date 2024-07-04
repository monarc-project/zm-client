<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Processor;

use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Table\SoaScaleCommentTable;

class SoaScaleCommentImportProcessor
{
    public function __construct(
        private SoaScaleCommentTable $soaScaleCommentTable,
        private ImportCacheHelper $importCacheHelper
    ) {
    }

    public function prepareCacheAndGetCurrentSoaScaleCommentsData(Entity\Anr $anr): array
    {
        if ($this->importCacheHelper->isCacheKeySet('soa_scale_comments_data')) {
            foreach ($this->soaScaleCommentTable->findByAnrOrderByIndex($anr, true) as $soaScaleComment) {
                $this->importCacheHelper->addItemToArrayCache('soa_scale_comments_data', [
                    'scaleIndex' => $soaScaleComment->getScaleIndex(),
                    'isHidden' => $soaScaleComment->isHidden(),
                    'colour' => $soaScaleComment->getColour(),
                    'object' => $soaScaleComment,
                ], $soaScaleComment->getScaleIndex());
            }
        }

        return $this->importCacheHelper->getItemFromArrayCache('soa_scale_comments_data');
    }

    public function mergeSoaScaleComment(Entity\Anr $anr, array $newScales)
    {
        $soaScaleCommentTranslations = $this->translationTable->findByAnrTypesAndLanguageIndexedByKey(
            $anr,
            [CoreEntity\TranslationSuperClass::SOA_SCALE_COMMENT],
            $anr->getLanguageCode()
        );
        $scales = $this->soaScaleCommentTable->findByAnrIndexedByScaleIndex($anr);
        // we have scales to create
        if (\count($newScales) > \count($scales)) {
            $anrLanguageCode = $anr->getLanguageCode();
            for ($i = \count($scales); $i < \count($newScales); $i++) {
                // todo: no translations anymore !
//                $translationKey = (string)Uuid::uuid4();
//                $translation = (new Translation())
//                    ->setAnr($anr)
//                    ->setType(TranslationSuperClass::SOA_SCALE_COMMENT)
//                    ->setKey($translationKey)
//                    ->setValue('')
//                    ->setLang($anrLanguageCode)
//                    ->setCreator($this->connectedUser->getEmail());
//                $this->translationTable->save($translation, false);
//                $soaScaleCommentTranslations[$translationKey]  = $translation;

                $scales[$i] = (new Entity\SoaScaleComment())
                    ->setScaleIndex($i)
                    ->setAnr($anr)
                    ->setCommentTranslationKey($translationKey)
                    ->setCreator($this->connectedUser->getEmail());
                $this->soaScaleCommentTable->save($scales[$i], false);
            }
        }
        //we have scales to hide
        if (\count($newScales) < \count($scales)) {
            for ($i = \count($newScales); $i < \count($scales); $i++) {
                $scales[$i]->setIsHidden(true);
                $this->soaScaleCommentTable->save($scales[$i], false);
            }
        }
        //we process the scales
        foreach ($newScales as $id => $newScale) {
            $scales[$newScale['scaleIndex']]
                ->setColour($newScale['colour'])
                ->setIsHidden($newScale['isHidden']);
            $this->soaScaleCommentTable->save($scales[$newScale['scaleIndex']], false);

            $translationKey = $scales[$newScale['scaleIndex']]->getCommentTranslationKey();
            $translation = $soaScaleCommentTranslations[$translationKey];
            $translation->setValue($newScale['comment']);

            $this->translationTable->save($translation, false);

            $this->importCacheHelper->addItemToArrayCache(
                'newSoaScaleCommentIndexedByScale',
                $scales[$newScale['scaleIndex']],
                $newScale['scaleIndex']
            );
            $this->importCacheHelper
                ->addItemToArrayCache('soaScaleCommentExternalIdMapToNewObject', $scales[$newScale['scaleIndex']], $id);
        }
        $this->soaScaleCommentTable->flush();
    }
}
