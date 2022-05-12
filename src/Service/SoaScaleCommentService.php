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
}
