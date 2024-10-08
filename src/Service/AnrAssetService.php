<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\InputFormatter\FormattedInputParams;
use Monarc\Core\Entity as CoreEntity;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Table;

class AnrAssetService
{
    private CoreEntity\UserSuperClass $connectedUser;

    public function __construct(
        private Table\AssetTable $assetTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getList(FormattedInputParams $params): array
    {
        $result = [];
        /** @var Entity\Asset $asset */
        foreach ($this->assetTable->findByParams($params) as $asset) {
            $result[] = $this->prepareAssetDataResult($asset);
        }

        return $result;
    }

    public function getCount(FormattedInputParams $params): int
    {
        return $this->assetTable->countByParams($params);
    }

    public function getAssetData(Entity\Anr $anr, string $uuid): array
    {
        /** @var Entity\Asset $asset */
        $asset = $this->assetTable->findByUuidAndAnr($uuid, $anr);

        return $this->prepareAssetDataResult($asset);
    }

    public function create(Entity\Anr $anr, array $data, bool $saveInDb = true): Entity\Asset
    {
        /** @var Entity\Asset $asset */
        $asset = (new Entity\Asset())
            ->setAnr($anr)
            ->setCode($data['code'])
            ->setLabels($data)
            ->setDescriptions($data)
            ->setType($data['type'])
            ->setCreator($this->connectedUser->getEmail());
        if (isset($data['uuid'])) {
            $asset->setUuid($data['uuid']);
        }
        if (isset($data['status'])) {
            $asset->setStatus($data['status']);
        }

        $this->assetTable->save($asset, $saveInDb);

        return $asset;
    }

    public function createList(Entity\Anr $anr, array $data): array
    {
        $createdUuids = [];
        foreach ($data as $row) {
            $createdUuids[] = $this->create($anr, $row, false)->getUuid();
        }
        $this->assetTable->flush();

        return $createdUuids;
    }

    public function update(Entity\Anr $anr, string $uuid, array $data): Entity\Asset
    {
        /** @var Entity\Asset $asset */
        $asset = $this->assetTable->findByUuidAndAnr($uuid, $anr);

        $asset->setCode($data['code'])
            ->setLabels($data)
            ->setDescriptions($data)
            ->setType((int)$data['type'])
            ->setStatus($data['status'] ?? CoreEntity\AssetSuperClass::STATUS_ACTIVE)
            ->setUpdater($this->connectedUser->getEmail());

        $this->assetTable->save($asset);

        return $asset;
    }

    public function patch(Entity\Anr $anr, string $uuid, array $data): Entity\Asset
    {
        /** @var Entity\Asset $asset */
        $asset = $this->assetTable->findByUuidAndAnr($uuid, $anr);

        if (isset($data['status'])) {
            $asset->setStatus((int)$data['status'])
                ->setUpdater($this->connectedUser->getEmail());

            $this->assetTable->save($asset);
        }

        return $asset;
    }

    public function delete(Entity\Anr $anr, string $uuid): void
    {
        /** @var Entity\Asset $asset */
        $asset = $this->assetTable->findByUuidAndAnr($uuid, $anr);

        $this->assetTable->remove($asset);
    }

    public function deleteList(Entity\Anr $anr, array $data): void
    {
        $assets = $this->assetTable->findByUuidsAndAnr($data, $anr);

        $this->assetTable->removeList($assets);
    }

    public function prepareAssetDataResult(Entity\Asset $asset): array
    {
        return array_merge($asset->getLabels(), $asset->getDescriptions(), [
            'uuid' => $asset->getUuid(),
            'anr' => [
                'id' => $asset->getAnr()->getId(),
            ],
            'code' => $asset->getCode(),
            'type' => $asset->getType(),
            'status' => $asset->getStatus(),
            'mode' => $asset->getMode(),
        ]);
    }
}
