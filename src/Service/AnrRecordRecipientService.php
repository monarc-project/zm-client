<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\InputFormatter\FormattedInputParams;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Table\RecordRecipientTable;
use Monarc\FrontOffice\Entity\RecordRecipient;
use Monarc\FrontOffice\Entity\Anr;

class AnrRecordRecipientService
{
    private UserSuperClass $connectedUser;

    public function __construct(
        private RecordRecipientTable $recordRecipientTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getList(FormattedInputParams $params): array
    {
        return array_map(
            fn (RecordRecipient $recordRecipient): array => $this->serialize($recordRecipient),
            $this->recordRecipientTable->findByParams($params)
        );
    }

    public function getCount(FormattedInputParams $params): int
    {
        return $this->recordRecipientTable->countByParams($params);
    }

    public function getRecordRecipientData(Anr $anr, int $id): array
    {
        return $this->serialize($this->recordRecipientTable->findByIdAndAnr($id, $anr));
    }

    public function serialize(RecordRecipient $recordRecipient): array
    {
        return [
            'id' => $recordRecipient->getId(),
            'label' => $recordRecipient->getLabel(),
            'type' => $recordRecipient->getType(),
            'description' => $recordRecipient->getDescription(),
            'creator' => $recordRecipient->getCreator(),
            'createdAt' => $recordRecipient->getCreatedAt(),
            'updater' => $recordRecipient->getUpdater(),
            'updatedAt' => $recordRecipient->getUpdatedAt(),
        ];
    }

    public function create(Anr $anr, array $data, bool $flush = true): RecordRecipient
    {
        $recordRecipient = (new RecordRecipient())
            ->setAnr($anr)
            ->setCreator($this->connectedUser->getEmail());
        $this->applyData($recordRecipient, $data);
        $this->recordRecipientTable->save($recordRecipient, $flush);

        return $recordRecipient;
    }

    public function update(Anr $anr, int $id, array $data): void
    {
        $recordRecipient = $this->recordRecipientTable->findByIdAndAnr($id, $anr);
        $this->applyData($recordRecipient, $data);
        $recordRecipient->setUpdater($this->connectedUser->getEmail());
        $this->recordRecipientTable->save($recordRecipient);
    }

    private function applyData(RecordRecipient $recordRecipient, array $data): void
    {
        if (array_key_exists('label', $data)) {
            $recordRecipient->setLabel($data['label']);
        }
        if (array_key_exists('type', $data)) {
            $recordRecipient->setType($data['type'] === null ? null : (string)$data['type']);
        }
        if (array_key_exists('description', $data)) {
            $recordRecipient->setDescription($data['description']);
        }
    }
}
