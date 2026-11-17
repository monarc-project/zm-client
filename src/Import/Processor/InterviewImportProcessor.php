<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Processor;

use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Interview;
use Monarc\FrontOffice\Table\InterviewTable;

class InterviewImportProcessor
{
    private UserSuperClass $connectedUser;

    public function __construct(
        private InterviewTable $interviewTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function processInterviewsData(Anr $anr, array $interviewsData): void
    {
        foreach ($interviewsData as $interviewData) {
            if (!is_array($interviewData)) {
                continue;
            }
            $interview = (new Interview())
                ->setAnr($anr)
                ->setDate($interviewData['date'] ?? null)
                ->setService($interviewData['service'] ?? null)
                ->setContent($interviewData['content'] ?? null)
                ->setCreator($this->connectedUser->getEmail());
            $this->interviewTable->save($interview, false);
        }
    }
}
