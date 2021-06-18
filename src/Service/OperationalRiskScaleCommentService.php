<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Service;

use Doctrine\ORM\EntityNotFoundException;
use Monarc\Core\Service\ConfigService;
use Monarc\FrontOffice\Model\Entity\OperationalRiskScaleComment;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\OperationalRiskScaleCommentTable;
use Monarc\FrontOffice\Model\Table\TranslationTable;

class OperationalRiskScaleCommentService
{
    private AnrTable $anrTable;

    private OperationalRiskScaleCommentTable $operationalRiskScaleCommentTable;

    private TranslationTable $translationTable;

    private ConfigService $configService;

    public function __construct(
        AnrTable $anrTable,
        OperationalRiskScaleCommentTable $operationalRiskScaleCommentTable,
        TranslationTable $translationTable,
        ConfigService $configService
    ) {
        $this->anrTable = $anrTable;
        $this->operationalRiskScaleCommentTable = $operationalRiskScaleCommentTable;
        $this->translationTable = $translationTable;
        $this->configService = $configService;
    }

    public function update(int $id, array $data): int
    {
        $anr = $this->anrTable->findById($data['anr']);
        $anrLanguageCode = strtolower($this->configService->getLanguageCodes()[$anr->getLanguage()]);
        /** @var OperationalRiskScaleComment|null $operationalRiskScaleComment */
        $operationalRiskScaleComment = $this->operationalRiskScaleCommentTable->findById($id);
        if ($operationalRiskScaleComment === null) {
            throw new EntityNotFoundException(sprintf('Operational risk scale comment ID (%d) does not exist,', $id));
        }

        if (isset($data['scaleValue'])) {
            $operationalRiskScaleComment->setScaleValue((int)$data['scaleValue']);
        }
        if (!empty($data['comment'])) {
            $translationKey = $operationalRiskScaleComment->getCommentTranslationKey();
            $translation = $this->translationTable->findByAnrKeyAndLanguage($anr, $translationKey, $anrLanguageCode);
            $translation->setValue($data['comment']);
            $this->translationTable->save($translation, false);
        }
        $this->operationalRiskScaleCommentTable->save($operationalRiskScaleComment);

        return $operationalRiskScaleComment->getId();
    }
}
