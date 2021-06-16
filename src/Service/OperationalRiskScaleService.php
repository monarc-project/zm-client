<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Service;

use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\Core\Service\ConfigService;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\OperationalRiskScale;
use Monarc\FrontOffice\Model\Entity\OperationalRiskScaleComment;
use Monarc\FrontOffice\Model\Entity\Translation;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\OperationalInstanceRiskScaleTable;
use Monarc\FrontOffice\Model\Table\OperationalRiskScaleCommentTable;
use Monarc\FrontOffice\Model\Table\OperationalRiskScaleTable;
use Monarc\FrontOffice\Model\Table\TranslationTable;
use Ramsey\Uuid\Uuid;

class OperationalRiskScaleService
{
    private AnrTable $anrTable;

    private UserSuperClass $connectedUser;

    private OperationalRiskScaleTable $operationalRiskScaleTable;

    private OperationalRiskScaleCommentTable $operationalRiskScaleCommentTable;

    private OperationalInstanceRiskScaleTable $operationalInstanceRiskScaleTable;

    private TranslationTable $translationTable;

    private ConfigService $configService;

    public function __construct(
        AnrTable $anrTable,
        ConnectedUserService $connectedUserService,
        OperationalRiskScaleTable $operationalRiskScaleTable,
        OperationalRiskScaleCommentTable $operationalRiskScaleCommentTable,
        OperationalInstanceRiskScaleTable $operationalInstanceRiskScaleTable,
        TranslationTable $translationTable,
        ConfigService $configService
    ) {
        $this->anrTable = $anrTable;
        $this->connectedUser = $connectedUserService->getConnectedUser();
        $this->operationalRiskScaleTable = $operationalRiskScaleTable;
        $this->operationalRiskScaleCommentTable = $operationalRiskScaleCommentTable;
        $this->operationalInstanceRiskScaleTable = $operationalInstanceRiskScaleTable;
        $this->translationTable = $translationTable;
        $this->configService = $configService;
    }

    /**
     * @throws EntityNotFoundException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function createOperationalRiskScale(int $anrId, array $data): int
    {
        $anr = $this->anrTable->findById($anrId);
        $anrLanguageCode = strtolower($this->configService->getLanguageCodes()[$anr->getLanguage()]);

        $operationalRiskScale = (new OperationalRiskScale())
            ->setAnr($anr)
            ->setType($data['type'])
            ->setMin($data['min'])
            ->setMax($data['max'])
            ->setLabelTranslationKey((string)Uuid::uuid4())
            ->setCreator($this->connectedUser->getEmail());

        // Create a translation for the scale.
        $translation = (new Translation())
            ->setAnr($anr)
            ->setCreator($this->connectedUser->getEmail())
            ->setType(OperationalRiskScale::class)
            ->setKey($operationalRiskScale->getLabelTranslationKey())
            ->setLang($anrLanguageCode)
            ->setValue($data['Label']);

        $this->translationTable->save($translation, false);

        // Process the scale comments.
        if (!empty($data['comments'])) {
            foreach ($data['comments'] as $scaleCommentData) {
                $scaleComment = (new OperationalRiskScaleComment())
                    ->setCreator($this->connectedUser->getEmail())
                    ->setAnr($anr)
                    ->setScaleIndex($scaleCommentData['scaleIndex'])
                    ->setScaleValue($scaleCommentData['scaleValue'])
                    ->setCommentTranslationKey((string)Uuid::uuid4())
                    ->setOperationalRiskScale($operationalRiskScale);

                $this->operationalRiskScaleCommentTable->save($scaleComment, false);

                // Create a translation for the scaleComment (init with blank value).
                $translation = (new Translation())
                    ->setAnr($anr)
                    ->setCreator($this->connectedUser->getEmail())
                    ->setType(OperationalRiskScaleComment::class)
                    ->setKey($scaleComment->getCommentTranslationKey())
                    ->setLang($anrLanguageCode)
                    ->setValue('');

                $this->translationTable->save($translation, false);
            }
        }

        $this->operationalRiskScaleTable->save($operationalRiskScale);

        return $operationalRiskScale->getId();
    }

    /**
     * @throws EntityNotFoundException
     */
    public function getOperationalRiskScales(int $anrId): array
    {
        $anr = $this->anrTable->findById($anrId);
        $operationalRiskScales = $this->operationalRiskScaleTable->findWithCommentsByAnr($anr);
        $result = [];
        $translations = $this->translationTable->findByAnrTypesAndLanguageIndexedByKey(
            $anr,
            [OperationalRiskScale::class, OperationalRiskScaleComment::class],
            strtolower($this->configService->getLanguageCodes()[$anr->getLanguage()])
        );

        foreach ($operationalRiskScales as $operationalRiskScale) {
            $comments = [];
            foreach ($operationalRiskScale->getOperationalRiskScaleComments() as $operationalRiskScaleComment) {
                $translationComment = $translations[$operationalRiskScaleComment->getCommentTranslationKey()] ?? null;
                $comments[] = [
                    'id' => $operationalRiskScaleComment->getId(),
                    'scaleId' => $operationalRiskScale->getId(),
                    'scaleIndex' => $operationalRiskScaleComment->getScaleIndex(),
                    'scaleValue' => $operationalRiskScaleComment->getScaleValue(),
                    'comment' => $translationComment !== null ? $translationComment->getValue() : '',
                ];
            }

            $translationLabel = '';
            if (!empty($operationalRiskScale->getLabelTranslationKey())) {
                $translationScale = $translations[$operationalRiskScale->getLabelTranslationKey()] ?? null;
                $translationLabel = $translationScale ? $translationScale->getValue() : '';
            }

            $result[] = [
                'id' => $operationalRiskScale->getId(),
                'max' => $operationalRiskScale->getMax(),
                'min' => $operationalRiskScale->getMin(),
                'type' => $operationalRiskScale->getType(),
                'label' => $translationLabel,
                'comments' => $comments,
            ];
        }

        return $result;
    }

    public function deleteOperationalRiskScales($data): void
    {
        $translationsKeys = [];

        foreach ($data as $id) {
            /** @var OperationalRiskScale $scaleToDelete */
            $scaleToDelete = $this->operationalRiskScaleTable->findById($id);
            if ($scaleToDelete === null) {
                throw new EntityNotFoundException(sprintf('Scale with ID %d is not found', $id));
            }
            $translationsKeys[] = $scaleToDelete->getLabelTranslationKey();

            foreach ($scaleToDelete->getOperationalRiskScaleComments() as $operationalRiskScaleComment) {
                $translationsKeys[] = $operationalRiskScaleComment->getCommentTranslationKey();
            }

            $this->operationalRiskScaleTable->remove($scaleToDelete, true);

        }

        if (!empty($translationsKeys)) {
            $this->translationTable->deleteListByKeys($translationsKeys);
        }
    }

    public function update($id, $data): int
    {
        $anr = $this->anrTable->findById((int)$data['anr']);

        /** @var OperationalRiskScale $operationalRiskScale */
        $operationalRiskScale = $this->operationalRiskScaleTable->findById((int)$id);
        $anrLanguageCode = strtolower($this->configService->getLanguageCodes()[$anr->getLanguage()]);

        $operationalRiskScale->setIsHidden(!empty($data['isHidden']));

        if (!empty($data['label'])) {
            $translationKey = $operationalRiskScale->getLabelTranslationKey();
            if (empty($translationKey)) {
                //TODO:
            } else {
                $translation = $this->translationTable->findByAnrKeyAndLanguage($anr, $translationKey, $anrLanguageCode);
                $translation->setValue($data['label']);
                $this->translationTable->save($translation, false);
            }
        }
        $this->operationalRiskScaleTable->save($operationalRiskScale);

        return $operationalRiskScale->getId();
    }
}
