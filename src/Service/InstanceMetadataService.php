<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Doctrine\ORM\EntityNotFoundException;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\Core\Model\Entity\TranslationSuperClass;
use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\Core\Model\Entity\Translation;
use Monarc\Core\Service\ConfigService;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\TranslationTable;
use Monarc\FrontOffice\Model\Table\InstanceMetadataTable;
use Monarc\FrontOffice\Model\Table\InstanceTable;
use Ramsey\Uuid\Uuid;

class InstanceMetadataService
{
    protected AnrTable $anrTable;

    protected InstanceMetadataTable $instanceMetadataTable;

    protected InstanceTable $instanceTable;

    protected TranslationTable $translationTable;

    protected ConfigService $configService;

    protected UserSuperClass $connectedUser;

    public function __construct(
        AnrTable $anrTable,
        InstanceMetadataTable $instanceMetadataTable,
        InstanceTable $instanceTable,
        TranslationTable $translationTable,
        ConfigService $configService,
        ConnectedUserService $connectedUserService
    ) {
        $this->anrTable = $anrTable;
        $this->instanceMetadataTable = $instanceMetadataTable;
        $this->instanceTable = $instanceTable;
        $this->translationTable = $translationTable;
        $this->configService = $configService;
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    /**
     * @param int $anrId
     * @param int $instanceId
     * @param string $language
     *
     * @return array
     * @throws EntityNotFoundException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function getInstancesMetadatas(int $anrId, int $instanceId, string $language = null): array
    {
        $result = [];
        $anr = $this->anrTable->findById($anrId);
        $instance = $this->instanceTable->findById($instanceId);
        $instancesMetadatas = $this->instanceMetadataTable->findByInstance($instance);
        if ($language === null) {
            $language = $this->getAnrLanguageCode($anr);
        }

        $translations = $this->translationTable->findByAnrTypesAndLanguageIndexedByKey(
            $anr,
            [Translation::INSTANCE_METADATA],
            $language
        );

        foreach ($instancesMetadatas as $index => $instanceMetadata) {
            $translationComment = $translations[$instanceMetadata->getCommentTranslationKey()] ?? null;
            $result[]= [
                'id' => $instanceMetadata->getId(),
                'metadataId' => $instanceMetadata->getMetadata()->getId(),
                $language => $translationComment !== null ? $translationComment->getValue() : '',
            ];
        }

        return $result;
    }

    /**
     * @param int $id
     *
     * @throws EntityNotFoundException
     */
    public function deleteInstanceMetadata(int $id)
    {
        $instanceMetadataToDelete = $this->instanceMetadataTable->findById($id);
        if ($instanceMetadataToDelete === null) {
            throw new EntityNotFoundException(sprintf('Instance Metadata with ID %d is not found', $id));
        }

        $this->instanceMetadataTable->remove($instanceMetadataToDelete);

        $translationsKeys[] = $instanceMetadataToDelete->getCommentTranslationKey();

        if (!empty($translationsKeys)) {
            $this->translationTable->deleteListByKeys($translationsKeys);
        }
    }

    /**
     * @param int $anrId
     * @param int $id
     * @param string¦null $language
     *
     * @throws EntityNotFoundException
     */
    public function getInstanceMetadata(int $anrId, int $id, string $language = null): array
    {
        $anr = $this->anrTable->findById($anrId);
        $instanceMetadata = $this->instanceMetadataTable->findById($id);
        if ($language === null) {
            $language = $this->getAnrLanguageCode($anr);
        }

        $translations = $this->translationTable->findByAnrTypesAndLanguageIndexedByKey(
            $anr,
            [Translation::INSTANCE_METADATA],
            $language
        );

        $translationLabel = $translations[$instanceMetadata->getCommentTranslationKey()] ?? null;
        return [
            'id' => $instanceMetadata->getId(),
            $language => $translationLabel !== null ? $translationLabel->getValue() : '',
        ];
    }

    protected function getAnrLanguageCode(Anr $anr): string
    {
        return $this->configService->getActiveLanguageCodes()[$anr->getLanguage()];
    }
}
