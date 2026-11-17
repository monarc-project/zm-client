<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use LogicException;
use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\InputFormatter\FormattedInputParams;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Interview;
use Monarc\FrontOffice\Table\InterviewTable;

class AnrInterviewService
{
    private UserSuperClass $connectedUser;

    public function __construct(private InterviewTable $interviewTable, ConnectedUserService $connectedUserService)
    {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getList(FormattedInputParams $params): array
    {
        if (!$params->hasFilterFor('anr')) {
            throw new LogicException('Analysis filter is required');
        }

        return array_map(
            fn (Interview $interview): array => $this->serialize($interview),
            $this->interviewTable->findByParams($params)
        );
    }

    public function getCount(FormattedInputParams $params): int
    {
        if (!$params->hasFilterFor('anr')) {
            throw new LogicException('Analysis filter is required');
        }

        return $this->interviewTable->countByParams($params);
    }

    public function getInterviewData(Anr $anr, int $id): array
    {
        return $this->serialize($this->interviewTable->findByIdAndAnr($id, $anr));
    }

    public function serialize(Interview $interview): array
    {
        return [
            'id' => $interview->getId(),
            'anr' => new \stdClass(),
            'date' => $interview->getDate(),
            'service' => $interview->getService(),
            'content' => $interview->getContent(),
            'creator' => $interview->getCreator(),
            'createdAt' => $interview->getCreatedAt(),
            'updater' => $interview->getUpdater(),
            'updatedAt' => $interview->getUpdatedAt(),
        ];
    }

    public function create(Anr $anr, array $data, bool $flush = true): Interview
    {
        $interview = (new Interview())
            ->setAnr($anr)
            ->setDate($data['date'])
            ->setService($data['service'])
            ->setContent($data['content'])
            ->setCreator($this->connectedUser->getEmail());
        $this->interviewTable->save($interview, $flush);

        return $interview;
    }

    public function update(Anr $anr, int $id, array $data): void
    {
        $interview = $this->interviewTable->findByIdAndAnr($id, $anr);
        if (array_key_exists('date', $data)) {
            $interview->setDate($data['date']);
        }
        if (array_key_exists('service', $data)) {
            $interview->setService($data['service']);
        }
        if (array_key_exists('content', $data)) {
            $interview->setContent($data['content']);
        }
        $interview->setUpdater($this->connectedUser->getEmail());
        $this->interviewTable->save($interview);
    }

    public function delete(Anr $anr, int $id): void
    {
        $this->interviewTable->remove($this->interviewTable->findByIdAndAnr($id, $anr));
    }

    public function copy(Anr $sourceAnr, Anr $targetAnr): void
    {
        foreach ($this->interviewTable->findByAnr($sourceAnr) as $source) {
            $interview = (new Interview())
                ->setAnr($targetAnr)
                ->setDate($source->getDate())
                ->setService($source->getService())
                ->setContent($source->getContent())
                ->setCreator($source->getCreator())
                ->setUpdater($source->getUpdater());
            $this->interviewTable->save($interview, false);
        }
    }
}
