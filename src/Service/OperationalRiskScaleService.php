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

    public function getOperationalRiskScales(int $anrId): array
    {
        $anr = $this->anrTable->findById($anrId);
        $operationalRiskScales = $this->operationalRiskScaleTable->findWithCommentsByAnr($anr);
        $result = [];
        //TODO: fetch all the translations by anr + types OpRisksScales and OpRisksScalesComments and set them as array.
        foreach ($operationalRiskScales as $operationalRiskScale) {
            $comments = [];
            foreach ($operationalRiskScale->getOperationalRiskScaleComments() as $operationalRiskScaleComment) {
                $comments[] = [
                    'scaleIndex' => $operationalRiskScaleComment->getScaleIndex(),
                    'scaleValue' => $operationalRiskScaleComment->getScaleValue(),
                    'comments' => [], // TODO: get translations and get by key here.
                ];
            }
            $result[] = [
                'id' => $operationalRiskScale->getId(),
                'max' => $operationalRiskScale->getMax(),
                'min' => $operationalRiskScale->getMin(),
                'type' => $operationalRiskScale->getType(),
                'labels' => [],  // TODO: get translations and get by key here.
                'comments' => $comments,
            ];
        }

        return $result;
    }
}
