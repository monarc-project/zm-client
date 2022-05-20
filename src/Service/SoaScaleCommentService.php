<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\ConfigService;
use Monarc\Core\Service\SoaScaleCommentService as CoreSoaScaleCommentService;
use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Entity\TranslationSuperClass;
use Monarc\FrontOffice\Model\Entity\SoaScaleComment;
use Monarc\FrontOffice\Model\Entity\Translation;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\SoaScaleCommentTable;
use Monarc\FrontOffice\Model\Table\TranslationTable;
use Ramsey\Uuid\Uuid;

class SoaScaleCommentService extends CoreSoaScaleCommentService
{
    public function __construct(
        AnrTable $anrTable,
        ConnectedUserService $connectedUserService,
        SoaScaleCommentTable $soaScaleCommentTable,
        TranslationTable $translationTable,
        ConfigService $configService
    ) {
        $this->anrTable = $anrTable;
        $this->connectedUser = $connectedUserService->getConnectedUser();
        $this->soaScaleCommentTable = $soaScaleCommentTable;
        $this->translationTable = $translationTable;
        $this->configService = $configService;
    }

    protected function createSoaScaleComment(
        AnrSuperClass $anr,
        int $scaleIndex,
        array $languageCodes,
        bool $isFlushable = false
    ): void {
        $scaleComment = (new soaScaleComment())
            ->setAnr($anr)
            ->setScaleIndex($scaleIndex)
            ->setColour('')
            ->setIsHidden(false)
            ->setCommentTranslationKey((string)Uuid::uuid4())
            ->setCreator($this->connectedUser->getEmail());

        $this->soaScaleCommentTable->save($scaleComment, false);

        foreach ($languageCodes as $languageCode) {
            // Create a translation for the scaleComment (init with blank value).
            $translation = $this->createTranslationObject(
                $anr,
                Translation::SOA_SCALE_COMMENT,
                $scaleComment->getCommentTranslationKey(),
                $languageCode,
                ''
            );

            $this->translationTable->save($translation, false);
        }

        if ($isFlushable) {
            $this->soaScaleCommentTable->flush();
        }
    }

    protected function createTranslationObject(
        AnrSuperClass $anr,
        string $type,
        string $key,
        string $lang,
        string $value
    ): TranslationSuperClass {
        return (new Translation())
            ->setAnr($anr)
            ->setType($type)
            ->setKey($key)
            ->setLang($lang)
            ->setValue($value)
            ->setCreator($this->connectedUser->getEmail());
    }
}
