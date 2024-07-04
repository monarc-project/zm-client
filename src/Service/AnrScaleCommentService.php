<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\InputFormatter\FormattedInputParams;
use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Table;

class AnrScaleCommentService
{
    private UserSuperClass $connectedUser;

    public function __construct(
        private Table\ScaleCommentTable $scaleCommentTable,
        private Table\ScaleTable $scaleTable,
        private Table\ScaleImpactTypeTable $scaleImpactTypeTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getList(FormattedInputParams $formattedInputParams): array
    {
        $result = [];
        /** @var Entity\ScaleComment $scaleComment */
        foreach ($this->scaleCommentTable->findByParams($formattedInputParams) as $scaleComment) {
            $result[] = array_merge([
                'id' => $scaleComment->getId(),
                'scaleIndex' => $scaleComment->getScaleIndex(),
                'scaleValue' => $scaleComment->getScaleValue(),
                'scaleImpactType' => $scaleComment->getScaleImpactType() === null ? null : [
                    'id' => $scaleComment->getScaleImpactType()->getId(),
                    'type' => $scaleComment->getScaleImpactType()->getType(),
                ]
            ], $scaleComment->getComments());
        }

        return $result;
    }

    public function create(Entity\Anr $anr, array $data, bool $saveInDb = true): Entity\ScaleComment
    {
        /** @var Entity\Scale $scale */
        $scale = isset($data['scale']) && $data['scale'] instanceof Entity\Scale
            ? $data['scale']
            : $this->scaleTable->findByIdAndAnr($data['scaleId'], $anr);

        /** @var Entity\ScaleComment $scaleComment */
        $scaleComment = (new Entity\ScaleComment())
            ->setAnr($anr)
            ->setScale($scale)
            ->setComments($data)
            ->setScaleIndex($data['scaleIndex'])
            ->setScaleValue($data['scaleValue'])
            ->setCreator($this->connectedUser->getEmail());

        if (!empty($data['scaleImpactType'])) {
            /** @var Entity\ScaleImpactType $scaleImpactType */
            $scaleImpactType = $data['scaleImpactType'] instanceof Entity\ScaleImpactType
                ? $data['scaleImpactType']
                : $this->scaleImpactTypeTable->findByIdAndAnr($data['scaleImpactType'], $anr);
            $scaleComment->setScaleImpactType($scaleImpactType);
        }

        $this->scaleCommentTable->save($scaleComment, $saveInDb);

        return $scaleComment;
    }

    public function update(Entity\Anr $anr, int $id, array $data): Entity\ScaleComment
    {
        /** @var Entity\ScaleComment $scaleComment */
        $scaleComment = $this->scaleCommentTable->findByIdAndAnr($id, $anr);

        $this->scaleCommentTable->save($scaleComment->setComments($data)->setUpdater($this->connectedUser->getEmail()));

        return $scaleComment;
    }
}
