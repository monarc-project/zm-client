<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\OperationalRiskScale;
use Monarc\FrontOffice\Model\Entity\OperationalRiskScaleComment;
use Monarc\FrontOffice\Model\Entity\User;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\OperationalInstanceRiskScaleTable;
use Monarc\FrontOffice\Model\Table\OperationalRiskScaleCommentTable;
use Monarc\FrontOffice\Model\Table\OperationalRiskScaleTable;
use Monarc\FrontOffice\Model\Table\TranslationTable;

class OperationalRiskScaleService
{
    private AnrTable $anrTable;

    private User $connectedUser;

    private OperationalRiskScaleTable $operationalRiskScaleTable;

    private OperationalRiskScaleCommentTable $operationalRiskScaleCommentTable;

    private OperationalInstanceRiskScaleTable $operationalInstanceRiskScaleTable;

    private TranslationTable $translationTable;

    public function __construct(
        AnrTable $anrTable,
        ConnectedUserService $connectedUserService,
        OperationalRiskScaleTable $operationalRiskScaleTable,
        OperationalRiskScaleCommentTable $operationalRiskScaleCommentTable,
        OperationalInstanceRiskScaleTable $operationalInstanceRiskScaleTable,
        TranslationTable $translationTable
    ) {
        $this->anrTable = $anrTable;
        $this->connectedUser = $connectedUserService->getConnectedUser();
        $this->operationalRiskScaleTable = $operationalRiskScaleTable;
        $this->operationalRiskScaleCommentTable = $operationalRiskScaleCommentTable;
        $this->operationalInstanceRiskScaleTable = $operationalInstanceRiskScaleTable;
        $this->translationTable = $translationTable;
    }

    public function createOperationalRiskScale(int $anrId, array $data): int
    {
        $anr = $this->anrTable->findById($anrId);
        $this->connectedUser->getEmail();

        $operationalRiskScale = (new OperationalRiskScale())
            ->setAnr($anr)
            ->setCreator($this->connectedUser->getEmail());

        $this->operationalRiskScaleTable->save($operationalRiskScale);
    }

    public function getOperationalRiskScales(int $anrId): array
    {
        $anr = $this->anrTable->findById($anrId);
        $operationalRiskScales = $this->operationalRiskScaleTable->findWithCommentsByAnr($anr);
        $result = [];
        $translations = $this->translationTable->findByAnrAndTypesIndexedByKey(
            $anr,
            [OperationalRiskScale::class, OperationalRiskScaleComment::class]
        );
        foreach ($operationalRiskScales as $operationalRiskScale) {
            $comments = [];
            foreach ($operationalRiskScale->getOperationalRiskScaleComments() as $operationalRiskScaleComment) {
                $translationComments = $translations[$operationalRiskScaleComment->getCommentTranslationKey()];
                $translationCommentValues = [];
                foreach ($translationComments as $translationComment) {
                    $translationCommentValues[] = $translationComment->getValue();
                }
                $comments[] = [
                    'scaleIndex' => $operationalRiskScaleComment->getScaleIndex(),
                    'scaleValue' => $operationalRiskScaleComment->getScaleValue(),
                    'comments' => $translationCommentValues,
                ];
            }
            $translationLabels = [];
            if (!empty($operationalRiskScale->getLabelTranslationKey())) {
                $translationScales = $translations[$operationalRiskScale->getLabelTranslationKey()];
                foreach ($translationScales as $translationScale) {
                    $translationLabels[] = $translationScale->getValue();
                }
            }
            $result[] = [
                'id' => $operationalRiskScale->getId(),
                'max' => $operationalRiskScale->getMax(),
                'min' => $operationalRiskScale->getMin(),
                'type' => $operationalRiskScale->getType(),
                'labels' => $translationLabels,
                'comments' => $comments,
            ];
        }

        return $result;
    }
}
