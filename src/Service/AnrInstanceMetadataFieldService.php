<?php declare(strict_types=1);

/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\AnrInstanceMetadataField;
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

    public function create(Anr $anr, array $data, bool $saveInDb = true): AnrInstanceMetadataField
    {
        $metadataFieldData = current($data);

        return $this->createAnrInstanceMetadataField(
            $anr,
            $metadataFieldData[$anr->getLanguageCode()],
            isset($metadataFieldData['isDeletable']) ? (bool)$metadataFieldData['isDeletable'] : null,
            $saveInDb
        );
    }

    public function createAnrInstanceMetadataField(
        Anr $anr,
        string $label,
        ?bool $isDeletable,
        bool $saveInDb
    ): AnrInstanceMetadataField {
        $metadataField = (new AnrInstanceMetadataField())
            ->setLabel($label)
            ->setAnr($anr)
            ->setCreator($this->connectedUser->getEmail());
        if ($isDeletable !== null) {
            $metadataField->setIsDeletable($isDeletable);
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
