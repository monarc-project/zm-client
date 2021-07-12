<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2021 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Entity\OperationalRiskScaleSuperClass;
use Monarc\Core\Model\Entity\OperationalRiskScaleTypeSuperClass;
use Monarc\Core\Model\Entity\TranslationSuperClass;
use Monarc\Core\Service\ConfigService;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\OperationalRiskScaleService as CoreOperationalRiskScaleService;
use Monarc\FrontOffice\Model\Entity\OperationalRiskScaleComment;
use Monarc\FrontOffice\Model\Entity\OperationalRiskScaleType;
use Monarc\FrontOffice\Model\Entity\Translation;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\OperationalRiskScaleCommentTable;
use Monarc\FrontOffice\Model\Table\OperationalRiskScaleTable;
use Monarc\FrontOffice\Model\Table\OperationalRiskScaleTypeTable;
use Monarc\FrontOffice\Model\Table\TranslationTable;
use Ramsey\Uuid\Uuid;

class OperationalRiskScaleService extends CoreOperationalRiskScaleService
{
    public function __construct(
        AnrTable $anrTable,
        ConnectedUserService $connectedUserService,
        OperationalRiskScaleTable $operationalRiskScaleTable,
        OperationalRiskScaleTypeTable $operationalRiskScaleTypeTable,
        OperationalRiskScaleCommentTable $operationalRiskScaleCommentTable,
        TranslationTable $translationTable,
        ConfigService $configService
    ) {
        parent::__construct(
            $anrTable,
            $connectedUserService,
            $operationalRiskScaleTable,
            $operationalRiskScaleTypeTable,
            $operationalRiskScaleCommentTable,
            $translationTable,
            $configService
        );
    }

    protected function createOperationalRiskScaleTypeObject(
        AnrSuperClass $anr,
        OperationalRiskScaleSuperClass $operationalRiskScale
    ): OperationalRiskScaleTypeSuperClass {
        return (new OperationalRiskScaleType())
            ->setAnr($anr)
            ->setOperationalRiskScale($operationalRiskScale)
            ->setLabelTranslationKey((string)Uuid::uuid4())
            ->setIsSystem(0)
            ->setCreator($this->connectedUser->getEmail());
    }

    protected function createTranslationObject(
        string $type,
        string $key,
        string $lang,
        string $value
    ): TranslationSuperClass {
        return (new Translation())
            ->setCreator($this->connectedUser->getEmail())
            ->setType($type)
            ->setKey($key)
            ->setLang($lang)
            ->setValue($value);
    }

    protected function createScaleComment(
        AnrSuperClass $anr,
        OperationalRiskScaleSuperClass $operationalRiskScale,
        OperationalRiskScaleTypeSuperClass $operationalRiskScaleType,
        int $scaleIndex,
        int $maxScaleValue,
        array $anrLanguageCodes
    ): void {
        $scaleComment = (new OperationalRiskScaleComment())
            ->setAnr($anr)
            ->setOperationalRiskScale($operationalRiskScale)
            ->setOperationalRiskScaleType($operationalRiskScaleType)
            ->setScaleIndex($scaleIndex)
            ->setScaleValue($maxScaleValue)
            ->setCommentTranslationKey((string)Uuid::uuid4())
            ->setCreator($this->connectedUser->getEmail());

        $this->operationalRiskScaleCommentTable->save($scaleComment, false);

        foreach ($anrLanguageCodes as $anrLanguageCode) {
            // Create a translation for the scaleComment (init with blank value).
            $translation = $this->createTranslationObject(
                OperationalRiskScaleComment::TRANSLATION_TYPE_NAME,
                $scaleComment->getCommentTranslationKey(),
                $anrLanguageCode,
                ''
            );

            $this->translationTable->save($translation, false);
        }
    }
}
