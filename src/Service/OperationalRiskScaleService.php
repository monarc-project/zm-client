<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\OperationalRiskScale;
use Monarc\FrontOffice\Model\Entity\User;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\OperationalInstanceRiskScaleTable;
use Monarc\FrontOffice\Model\Table\OperationalRiskScaleCommentTable;
use Monarc\FrontOffice\Model\Table\OperationalRiskScaleTable;

class OperationalRiskScaleService
{
    /** @var AnrTable */
    private $anrTable;

    /** @var User */
    private $connectedUser;

    /** @var OperationalRiskScaleTable */
    private $operationalRiskScaleTable;

    /** @var OperationalRiskScaleCommentTable */
    private $operationalRiskScaleCommentTable;

    /** @var OperationalInstanceRiskScaleTable */
    private $operationalInstanceRiskScaleTable;

    public function __construct(
        AnrTable $anrTable,
        ConnectedUserService $connectedUserService,
        OperationalRiskScaleTable $operationalRiskScaleTable,
        OperationalRiskScaleCommentTable $operationalRiskScaleCommentTable,
        OperationalInstanceRiskScaleTable $operationalInstanceRiskScaleTable
    ) {
        $this->anrTable = $anrTable;
        $this->connectedUser = $connectedUserService->getConnectedUser();
        $this->operationalRiskScaleTable = $operationalRiskScaleTable;
        $this->operationalRiskScaleCommentTable = $operationalRiskScaleCommentTable;
        $this->operationalInstanceRiskScaleTable = $operationalInstanceRiskScaleTable;
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
}
