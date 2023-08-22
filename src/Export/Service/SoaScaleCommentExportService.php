<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Service;

use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\SoaScaleComment;
use Monarc\FrontOffice\Table\SoaScaleCommentTable;

class SoaScaleCommentExportService
{
    protected SoaScaleCommentTable $soaScaleCommentTable;

    public function __construct(SoaScaleCommentTable $soaScaleCommentTable)
    {
        $this->soaScaleCommentTable = $soaScaleCommentTable;
    }

    public function generateExportArray(Anr $anr): array
    {
        $result = [];
        /** @var SoaScaleComment[] $soaScaleComments */
        $soaScaleComments = $this->soaScaleCommentTable->findByAnrOrderByIndex($anr);
        foreach ($soaScaleComments as $soaScaleComment) {
            if (!$soaScaleComment->isHidden()) {
                $result[$soaScaleComment->getId()] = [
                    'scaleIndex' => $soaScaleComment->getScaleIndex(),
                    'isHidden' => $soaScaleComment->isHidden(),
                    'colour' => $soaScaleComment->getColour(),
                    'comment' => $soaScaleComment->getComment(),
                ];
            }
        }

        return $result;
    }
}
