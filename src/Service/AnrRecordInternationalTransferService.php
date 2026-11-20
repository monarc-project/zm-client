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
use Monarc\FrontOffice\Table\RecordInternationalTransferTable;
use Monarc\FrontOffice\Table\RecordTable;
use Monarc\FrontOffice\Entity\RecordInternationalTransfer;
use Monarc\FrontOffice\Entity\Anr;

class AnrRecordInternationalTransferService
{
    private UserSuperClass $connectedUser;

    public function __construct(
        private RecordInternationalTransferTable $recordInternationalTransferTable,
        private RecordTable $recordTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getList(FormattedInputParams $params): array
    {
        return array_map(
            fn (RecordInternationalTransfer $recordInternationalTransfer): array =>
                $this->serialize($recordInternationalTransfer),
            $this->recordInternationalTransferTable->findByParams($params)
        );
    }

    public function getCount(FormattedInputParams $params): int
    {
        return $this->recordInternationalTransferTable->countByParams($params);
    }

    public function getRecordInternationalTransferData(Anr $anr, int $id): array
    {
        return $this->serialize($this->recordInternationalTransferTable->findByIdAndAnr($id, $anr));
    }

    public function serialize(RecordInternationalTransfer $recordInternationalTransfer): array
    {
        return [
            'id' => $recordInternationalTransfer->getId(),
            'organisation' => $recordInternationalTransfer->getOrganisation(),
            'description' => $recordInternationalTransfer->getDescription(),
            'country' => $recordInternationalTransfer->getCountry(),
            'documents' => $recordInternationalTransfer->getDocuments(),
            'record' => $recordInternationalTransfer->getRecord()->getId(),
            'creator' => $recordInternationalTransfer->getCreator(),
            'createdAt' => $recordInternationalTransfer->getCreatedAt(),
            'updater' => $recordInternationalTransfer->getUpdater(),
            'updatedAt' => $recordInternationalTransfer->getUpdatedAt(),
        ];
    }

    public function create(Anr $anr, array $data, bool $flush = true): RecordInternationalTransfer
    {
        $recordInternationalTransfer = (new RecordInternationalTransfer())
            ->setAnr($anr)
            ->setCreator($this->connectedUser->getEmail());
        $this->applyData($recordInternationalTransfer, $data);
        $this->recordInternationalTransferTable->save($recordInternationalTransfer, $flush);

        return $recordInternationalTransfer;
    }

    public function update(Anr $anr, int $id, array $data): void
    {
        $recordInternationalTransfer = $this->recordInternationalTransferTable->findByIdAndAnr($id, $anr);
        $this->applyData($recordInternationalTransfer, $data);
        $recordInternationalTransfer->setUpdater($this->connectedUser->getEmail());
        $this->recordInternationalTransferTable->save($recordInternationalTransfer);
    }

    private function applyData(RecordInternationalTransfer $recordInternationalTransfer, array $data): void
    {
        $record = array_key_exists('record', $data)
            ? $this->recordTable->findByIdAndAnr((int)$data['record'], $recordInternationalTransfer->getAnr())
            : $recordInternationalTransfer->getRecord();
        if (array_key_exists('organisation', $data)) {
            $recordInternationalTransfer->setOrganisation($data['organisation']);
        }
        if (array_key_exists('description', $data)) {
            $recordInternationalTransfer->setDescription($data['description']);
        }
        if (array_key_exists('country', $data)) {
            $recordInternationalTransfer->setCountry($data['country']);
        }
        if (array_key_exists('documents', $data)) {
            $recordInternationalTransfer->setDocuments($data['documents']);
        }
        $recordInternationalTransfer->setRecord($record);
        if (!$record->getInternationalTransfers()->contains($recordInternationalTransfer)) {
            $record->getInternationalTransfers()->add($recordInternationalTransfer);
        }
    }
}
