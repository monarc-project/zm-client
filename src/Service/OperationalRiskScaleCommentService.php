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
use Monarc\FrontOffice\Model\Entity\User;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\OperationalRiskScaleCommentTable;
use Monarc\FrontOffice\Model\Table\OperationalRiskScaleTable;
use Monarc\FrontOffice\Model\Table\TranslationTable;
use Ramsey\Uuid\Uuid;

class OperationalRiskScaleCommentService
{
    private AnrTable $anrTable;

    private UserSuperClass $connectedUser;

    private OperationalRiskScaleTable $operationalRiskScaleTable;

    private OperationalRiskScaleCommentTable $operationalRiskScaleCommentTable;

    private TranslationTable $translationTable;

    private ConfigService $configService;

    public function __construct(
        AnrTable $anrTable,
        ConnectedUserService $connectedUserService,
        OperationalRiskScaleTable $operationalRiskScaleTable,
        OperationalRiskScaleCommentTable $operationalRiskScaleCommentTable,
        TranslationTable $translationTable,
        ConfigService $configService
    ) {
        $this->anrTable = $anrTable;
        $this->connectedUser = $connectedUserService->getConnectedUser();
        $this->operationalRiskScaleTable = $operationalRiskScaleTable;
        $this->operationalRiskScaleCommentTable = $operationalRiskScaleCommentTable;
        $this->translationTable = $translationTable;
        $this->configService = $configService;
    }

    public function update($id, $data):int
    {
      $anr = $this->anrTable->findById($data['anr']);
      $anrLanguageCode = strtolower($this->configService->getLanguageCodes()[$anr->getLanguage()]);
      $operationalRiskScaleComment = $this->operationalRiskScaleCommentTable->findById((int)$id);

      if(isset($data['scaleValue'])){
        $operationalRiskScaleComment->setScaleValue((int)$data['scaleValue']);
      }
      if(isset($data['comment'])){
          $translationKey = $operationalRiskScaleComment->getCommentTranslationKey();
          $translation = $this->translationTable->findByAnrKeyAndLanguage($anr, $translationKey,$anrLanguageCode);
          $translation->setValue($data['comment']);
          $this->translationTable->save($translation,false);
      }
      $this->operationalRiskScaleCommentTable->save($operationalRiskScaleComment);

      return  $operationalRiskScaleComment->getId();
    }
}
