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
use Monarc\FrontOffice\Model\Entity\SoaScaleComment;
use Monarc\FrontOffice\Table\SoaScaleCommentTable;

class SoaScaleCommentService
{
    private SoaScaleCommentTable $soaScaleCommentTable;

    private UserSuperClass $connectedUser;

    public function __construct(SoaScaleCommentTable $soaScaleCommentTable, ConnectedUserService $connectedUserService)
    {
        $this->soaScaleCommentTable = $soaScaleCommentTable;
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getSoaScaleCommentsData(Anr $anr): array
    {
        $result = [];
        /** @var SoaScaleComment[] $soaScaleComments */
        $soaScaleComments = $this->soaScaleCommentTable->findByAnrOrderByIndex($anr);
        foreach ($soaScaleComments as $soaScaleComment) {
            $soaScaleCommentId = $soaScaleComment->getId();
            $result[$soaScaleCommentId] = [
                'id' => $soaScaleCommentId,
                'scaleIndex' => $soaScaleComment->getScaleIndex(),
                'colour' => $soaScaleComment->getColour(),
                'comment' => $soaScaleComment->getComment(),
                'isHidden' => $soaScaleComment->isHidden(),
            ];
        }

        return $result;
    }

    public function createOrHideSoaScaleComments(Anr $anr, array $data): void
    {
        $soaScaleComments = $this->soaScaleCommentTable->findByAnrOrderByIndex($anr);

        if (isset($data['numberOfLevels'])) {
            $levelsNumber = (int)$data['numberOfLevels'];
            foreach ($soaScaleComments as $soaScaleComment) {
                $soaScaleComment
                    ->setIsHidden($soaScaleComment->getScaleIndex() >= $levelsNumber)
                    ->setUpdater($this->connectedUser->getEmail());
                $this->soaScaleCommentTable->save($soaScaleComment, false);
            }
            $numberOfCurrentComments = \count($soaScaleComments);
            if ($levelsNumber > $numberOfCurrentComments) {
                for ($i = $numberOfCurrentComments; $i < $levelsNumber; $i++) {
                    $this->createSoaScaleComment($anr, $i);
                }
            }

            $this->soaScaleCommentTable->flush();
        }
    }

    public function update(Anr $anr, int $id, array $data): void
    {
        /** @var SoaScaleComment $soaScaleComment */
        $soaScaleComment = $this->soaScaleCommentTable->findByIdAndAnr($id, $anr);

        if (isset($data['comment'])) {
            $soaScaleComment->setComment($data['comment']);
        }
        if (!empty($data['colour'])) {
            $soaScaleComment->setColour($data['colour']);
        }

        $this->soaScaleCommentTable->save($soaScaleComment->setUpdater($this->connectedUser->getEmail()));
    }

    public function createSoaScaleComment(
        Anr $anr,
        int $scaleIndex,
        string $colour = '',
        string $comment = '',
        bool $isHidden = false
    ): SoaScaleComment {
        $soaScaleComment = (new SoaScaleComment())
            ->setAnr($anr)
            ->setScaleIndex($scaleIndex)
            ->setColour($colour)
            ->setComment($comment)
            ->setIsHidden($isHidden)
            ->setCreator($this->connectedUser->getEmail());
        $this->soaScaleCommentTable->save($soaScaleComment, false);

        return $soaScaleComment;
    }
}
