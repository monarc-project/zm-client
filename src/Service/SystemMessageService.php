<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\SystemMessage;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Table\SystemMessageTable;

class SystemMessageService
{
    private User $connectedUser;

    public function __construct(
        private SystemMessageTable $systemMessageTable,
        ConnectedUserService $connectedUserService
    ) {
        /** @var User $connectedUser */
        $connectedUser = $connectedUserService->getConnectedUser();
        $this->connectedUser = $connectedUser;
    }

    public function getListOfActiveMessages(): array
    {
        $result = [];
        foreach ($this->systemMessageTable->findAllActiveByUser($this->connectedUser) as $systemMessage) {
            $result[] = [
                'id' => $systemMessage->getId(),
                'title' => $systemMessage->getTitle(),
                'description' => $systemMessage->getDescription(),
            ];
        }

        return $result;
    }

    public function inactivateMessage(int $id): void
    {
        $systemMessage = $this->systemMessageTable->findByIdAndUser($id, $this->connectedUser);
        if ($systemMessage->getStatus() === SystemMessage::STATUS_ACTIVE) {
            $this->systemMessageTable->save(
                $systemMessage->setStatus(SystemMessage::STATUS_INACTIVE)->setUpdater($this->connectedUser->getEmail())
            );
        }
    }
}
