<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\OperationalRiskScaleComment;
use Monarc\FrontOffice\Table\OperationalRiskScaleCommentTable;

class OperationalRiskScaleCommentService
{
    private OperationalRiskScaleCommentTable $operationalRiskScaleCommentTable;

    private UserSuperClass $connectedUser;

    public function __construct(
        OperationalRiskScaleCommentTable $operationalRiskScaleCommentTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->operationalRiskScaleCommentTable = $operationalRiskScaleCommentTable;
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function update(Anr $anr, int $id, array $data): OperationalRiskScaleComment
    {
        /** @var OperationalRiskScaleComment $operationalRiskScaleComment */
        $operationalRiskScaleComment = $this->operationalRiskScaleCommentTable->findByIdAndAnr($id, $anr);

        if (isset($data['scaleValue'])) {
            $operationalRiskScaleComment->setScaleValue((int)$data['scaleValue']);
        }
        if (isset($data['comment'])) {
            $operationalRiskScaleComment->setComment($data['comment']);
        }
        $operationalRiskScaleComment->setUpdater($this->connectedUser->getEmail());

        $this->operationalRiskScaleCommentTable->save($operationalRiskScaleComment);

        return $operationalRiskScaleComment;
    }
}
