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
use Monarc\FrontOffice\Table\RecordDataCategoryTable;
use Monarc\FrontOffice\Entity\RecordDataCategory;
use Monarc\FrontOffice\Entity\Anr;

class AnrRecordDataCategoryService
{
    private UserSuperClass $connectedUser;

    public function __construct(
        private RecordDataCategoryTable $recordDataCategoryTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getList(FormattedInputParams $params): array
    {
        return array_map(
            fn (RecordDataCategory $recordDataCategory): array => $this->serialize($recordDataCategory),
            $this->recordDataCategoryTable->findByParams($params)
        );
    }

    public function getCount(FormattedInputParams $params): int
    {
        return $this->recordDataCategoryTable->countByParams($params);
    }

    public function getRecordDataCategoryData(Anr $anr, int $id): array
    {
        return $this->serialize($this->recordDataCategoryTable->findByIdAndAnr($id, $anr));
    }

    public function serialize(RecordDataCategory $recordDataCategory): array
    {
        return [
            'id' => $recordDataCategory->getId(),
            'label' => $recordDataCategory->getLabel(),
            'creator' => $recordDataCategory->getCreator(),
            'createdAt' => $recordDataCategory->getCreatedAt(),
            'updater' => $recordDataCategory->getUpdater(),
            'updatedAt' => $recordDataCategory->getUpdatedAt(),
        ];
    }

    public function create(Anr $anr, array $data, bool $flush = true): RecordDataCategory
    {
        $recordDataCategory = (new RecordDataCategory())
            ->setAnr($anr)
            ->setCreator($this->connectedUser->getEmail());
        $this->applyData($recordDataCategory, $data);
        $this->recordDataCategoryTable->save($recordDataCategory, $flush);

        return $recordDataCategory;
    }

    public function update(Anr $anr, int $id, array $data): void
    {
        $recordDataCategory = $this->recordDataCategoryTable->findByIdAndAnr($id, $anr);
        $this->applyData($recordDataCategory, $data);
        $recordDataCategory->setUpdater($this->connectedUser->getEmail());
        $this->recordDataCategoryTable->save($recordDataCategory);
    }

    private function applyData(RecordDataCategory $recordDataCategory, array $data): void
    {
        if (array_key_exists('label', $data)) {
            $recordDataCategory->setLabel($data['label']);
        }
    }

    public function findOrCreate(Anr $anr, array $data, bool $flush = true): RecordDataCategory
    {
        return $this->recordDataCategoryTable->findNamedCategory($anr, $data['label'] ?? null)
            ?? $this->create($anr, $data, $flush);
    }
}
