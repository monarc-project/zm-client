<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\Translation;
use Monarc\FrontOffice\Model\Entity\OperationalRiskScale;
use Monarc\FrontOffice\Model\Entity\OperationalRiskScaleComment;
use Monarc\Core\Model\Entity\User;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\OperationalInstanceRiskScaleTable;
use Monarc\FrontOffice\Model\Table\OperationalRiskScaleCommentTable;
use Monarc\FrontOffice\Model\Table\OperationalRiskScaleTable;
use Monarc\FrontOffice\Model\Table\TranslationTable;
use Monarc\Core\Service\ConfigService;
use Ramsey\Uuid\Uuid;

class OperationalRiskScaleService
{
    private AnrTable $anrTable;

    private User $connectedUser;

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

    public function createOperationalRiskScale(int $anrId, array $data): int
    {
        $anr = $this->anrTable->findById($anrId);
        $this->connectedUser->getEmail();

        $operationalRiskScale = (new OperationalRiskScale())
            ->setAnr($anr)
            ->setCreator($this->connectedUser->getEmail())
            ->setType($data['type'])
            ->setMin($data['min'])
            ->setMax($data['max'])
            ->setLabelTranslationKey(Uuid::uuid4()->toString());

        $this->operationalRiskScaleTable->save($operationalRiskScale,false);

        //save the translation
        $translation = (new Translation())
            ->setAnr($anr)
            ->setCreator($this->connectedUser->getEmail())
            ->setType(OperationalRiskScale::class)
            ->setKey($operationalRiskScale->getLabelTranslationKey())
            ->setLang(strtolower($this->configService->getLanguageCodes()[$anr->getLanguage()]))
            ->setValue($data['Label']);

        $this->translationTable->save($translation);

        return $operationalRiskScale->getId();
    }

    public function getOperationalRiskScales(int $anrId): array
    {
        $anr = $this->anrTable->findById($anrId);
        $operationalRiskScales = $this->operationalRiskScaleTable->findWithCommentsByAnr($anr);
        $result = [];
        $translations = $this->translationTable->findByAnrAndTypesAndLanguageIndexedByKey(
            $anr,
            [OperationalRiskScale::class, OperationalRiskScaleComment::class],
            strtolower($this->configService->getLanguageCodes()[$anr->getLanguage()]) //fetch the language
        );
        foreach ($operationalRiskScales as $operationalRiskScale) {
            $comments = [];
            foreach ($operationalRiskScale->getOperationalRiskScaleComments() as $operationalRiskScaleComment) {
                $translationComment = $translations[$operationalRiskScaleComment->getCommentTranslationKey()];
                $comments[] = [
                    'scaleIndex' => $operationalRiskScaleComment->getScaleIndex(),
                    'scaleValue' => $operationalRiskScaleComment->getScaleValue(),
                    'comments' => $translationComment->getValue(),
                ];
            }
            $translationLabel = null;
            if (!empty($operationalRiskScale->getLabelTranslationKey())) {
                $translationScale = $translations[$operationalRiskScale->getLabelTranslationKey()];
                $translationLabel = $translationScale->getValue();
            }
            $result[] = [
                'id' => $operationalRiskScale->getId(),
                'max' => $operationalRiskScale->getMax(),
                'min' => $operationalRiskScale->getMin(),
                'type' => $operationalRiskScale->getType(),
                'labels' => $translationLabel,
                'comments' => $comments,
            ];
        }

        return $result;
    }
}
