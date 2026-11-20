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
use Monarc\FrontOffice\Table\RecordProcessorTable;
use Monarc\FrontOffice\Table\RecordActorTable;
use Monarc\FrontOffice\Entity\RecordProcessor;
use Monarc\FrontOffice\Entity\Anr;

class AnrRecordProcessorService
{
    private UserSuperClass $connectedUser;

    public function __construct(
        private RecordProcessorTable $recordProcessorTable,
        private RecordActorTable $recordActorTable,
        private AnrRecordActorService $anrRecordActorService,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getList(FormattedInputParams $params): array
    {
        return array_map(
            fn (RecordProcessor $recordProcessor): array => $this->serialize($recordProcessor),
            $this->recordProcessorTable->findByParams($params)
        );
    }

    public function getCount(FormattedInputParams $params): int
    {
        return $this->recordProcessorTable->countByParams($params);
    }

    public function getRecordProcessorData(Anr $anr, int $id): array
    {
        return $this->serialize($this->recordProcessorTable->findByIdAndAnr($id, $anr));
    }

    public function serialize(RecordProcessor $recordProcessor): array
    {
        return [
            'id' => $recordProcessor->getId(),
            'label' => $recordProcessor->getLabel(),
            'contact' => $recordProcessor->getContact(),
            'activities' => $recordProcessor->getActivities(),
            'secMeasures' => $recordProcessor->getSecMeasures(),
            'representative' => $recordProcessor->getRepresentative() === null ? null
                : $this->anrRecordActorService->serialize($recordProcessor->getRepresentative()),
            'dpo' => $recordProcessor->getDpo() === null ? null
                : $this->anrRecordActorService->serialize($recordProcessor->getDpo()),
            'creator' => $recordProcessor->getCreator(),
            'createdAt' => $recordProcessor->getCreatedAt(),
            'updater' => $recordProcessor->getUpdater(),
            'updatedAt' => $recordProcessor->getUpdatedAt(),
        ];
    }

    public function create(Anr $anr, array $data, bool $flush = true): RecordProcessor
    {
        $recordProcessor = (new RecordProcessor())
            ->setAnr($anr)
            ->setCreator($this->connectedUser->getEmail());
        $this->applyData($recordProcessor, $data);
        $this->recordProcessorTable->save($recordProcessor, $flush);

        return $recordProcessor;
    }

    public function update(Anr $anr, int $id, array $data): void
    {
        $recordProcessor = $this->recordProcessorTable->findByIdAndAnr($id, $anr);
        $this->applyData($recordProcessor, $data);
        $recordProcessor->setUpdater($this->connectedUser->getEmail());
        $this->recordProcessorTable->save($recordProcessor);
    }

    private function applyData(RecordProcessor $recordProcessor, array $data): void
    {
        $representative = !empty($data['representative'])
            ? $this->recordActorTable->findByIdAndAnr((int)$data['representative'], $recordProcessor->getAnr()) : null;
        $dpo = !empty($data['dpo'])
            ? $this->recordActorTable->findByIdAndAnr((int)$data['dpo'], $recordProcessor->getAnr()) : null;
        if (array_key_exists('label', $data)) {
            $recordProcessor->setLabel($data['label']);
        }
        if (array_key_exists('contact', $data)) {
            $recordProcessor->setContact($data['contact']);
        }
        if (array_key_exists('activities', $data)) {
            $recordProcessor->setActivities($data['activities']);
        }
        if (array_key_exists('secMeasures', $data)) {
            $recordProcessor->setSecMeasures($data['secMeasures']);
        }
        if (array_key_exists('representative', $data)) {
            $recordProcessor->setRepresentative($representative);
        }
        if (array_key_exists('dpo', $data)) {
            $recordProcessor->setDpo($dpo);
        }
    }
}
