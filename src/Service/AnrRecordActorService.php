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
use Monarc\FrontOffice\Table\RecordActorTable;
use Monarc\FrontOffice\Entity\RecordActor;
use Monarc\FrontOffice\Entity\Anr;

class AnrRecordActorService
{
    private UserSuperClass $connectedUser;

    public function __construct(
        private RecordActorTable $recordActorTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getList(FormattedInputParams $params): array
    {
        return array_map(
            fn (RecordActor $recordActor): array => $this->serialize($recordActor),
            $this->recordActorTable->findByParams($params)
        );
    }

    public function getCount(FormattedInputParams $params): int
    {
        return $this->recordActorTable->countByParams($params);
    }

    public function getRecordActorData(Anr $anr, int $id): array
    {
        return $this->serialize($this->recordActorTable->findByIdAndAnr($id, $anr));
    }

    public function serialize(RecordActor $recordActor): array
    {
        return [
            'id' => $recordActor->getId(),
            'label' => $recordActor->getLabel(),
            'contact' => $recordActor->getContact(),
            'creator' => $recordActor->getCreator(),
            'createdAt' => $recordActor->getCreatedAt(),
            'updater' => $recordActor->getUpdater(),
            'updatedAt' => $recordActor->getUpdatedAt(),
        ];
    }

    public function create(Anr $anr, array $data, bool $flush = true): RecordActor
    {
        $recordActor = (new RecordActor())
            ->setAnr($anr)
            ->setCreator($this->connectedUser->getEmail());
        $this->applyData($recordActor, $data);
        $this->recordActorTable->save($recordActor, $flush);

        return $recordActor;
    }

    public function update(Anr $anr, int $id, array $data): void
    {
        $recordActor = $this->recordActorTable->findByIdAndAnr($id, $anr);

        $this->applyData($recordActor, $data);
        $recordActor->setUpdater($this->connectedUser->getEmail());
        $this->recordActorTable->save($recordActor);
    }

    private function applyData(RecordActor $recordActor, array $data): void
    {
        if (array_key_exists('label', $data)) {
            $recordActor->setLabel($data['label']);
        }
        if (array_key_exists('contact', $data)) {
            $recordActor->setContact($data['contact']);
        }
    }
}
