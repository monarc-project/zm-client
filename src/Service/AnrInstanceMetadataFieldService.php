<?php declare(strict_types=1);

/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\AnrInstanceMetadataField;
use Monarc\FrontOffice\Table\AnrInstanceMetadataFieldTable;

class AnrInstanceMetadataFieldService
{
    private UserSuperClass $connectedUser;

    public function __construct(
        private AnrInstanceMetadataFieldTable $anrInstanceMetadataFieldTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getList(Anr $anr): array
    {
        $result = [];
        /** @var AnrInstanceMetadataField $metadataField */
        foreach ($this->anrInstanceMetadataFieldTable->findByAnr($anr) as $index => $metadataField) {
            $result[] = [
                'id' => $metadataField->getId(),
                'index' => $index + 1,
                $anr->getLanguageCode() => $metadataField->getLabel(),
            ];
        }

        return $result;
    }

    public function getAnrInstanceMetadataFieldData(Anr $anr, int $id): array
    {
        /** @var AnrInstanceMetadataField $metadataField */
        $metadataField = $this->anrInstanceMetadataFieldTable->findByIdAndAnr($id, $anr);

        return [
            'id' => $metadataField->getId(),
            $anr->getLanguageCode() => $metadataField->getLabel(),
        ];
    }

    public function create(Anr $anr, array $data, bool $saveInDb = true): AnrInstanceMetadataField
    {
        $metadataFieldData = current($data);
        $metadataField = (new AnrInstanceMetadataField())
            ->setLabel($metadataFieldData[$anr->getLanguageCode()])
            ->setAnr($anr)
            ->setCreator($this->connectedUser->getEmail());
        if (isset($metadataFieldData['isDeletable'])) {
            $metadataField->setIsDeletable((bool)$metadataFieldData['isDeletable']);
        }

        $this->anrInstanceMetadataFieldTable->save($metadataField, $saveInDb);

        return $metadataField;
    }

    public function update(Anr $anr, int $id, array $data): AnrInstanceMetadataField
    {
        /** @var AnrInstanceMetadataField $metadataField */
        $metadataField = $this->anrInstanceMetadataFieldTable->findByIdAndAnr($id, $anr);
        if (!$metadataField->isDeletable()) {
            throw new Exception('Predefined instance metadata fields can\'t be modified.', 412);
        }

        $metadataField->setLabel($data[$anr->getLanguageCode()])->setUpdater($this->connectedUser->getEmail());

        return $metadataField;
    }

    public function delete(Anr $anr, int $id): void
    {
        /** @var AnrInstanceMetadataField $metadataField */
        $metadataField = $this->anrInstanceMetadataFieldTable->findByIdAndAnr($id, $anr);
        if (!$metadataField->isDeletable()) {
            throw new Exception('Predefined instance metadata fields can\'t be deleted.', 412);
        }

        $this->anrInstanceMetadataFieldTable->remove($metadataField);
    }
}
