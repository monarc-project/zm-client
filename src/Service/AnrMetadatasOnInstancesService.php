<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Entity\TranslationSuperClass;
use Monarc\Core\Service\ConfigService;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\AnrMetadatasOnInstancesService as CoreAnrMetadatasOnInstancesService;
use Monarc\FrontOffice\Model\Entity\Translation;
use Monarc\FrontOffice\Model\Entity\AnrMetadatasOnInstances;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\TranslationTable;
use Monarc\FrontOffice\Model\Table\AnrMetadatasOnInstancesTable;
use Ramsey\Uuid\Uuid;

class AnrMetadatasOnInstancesService extends CoreAnrMetadatasOnInstancesService
{
    public function __construct(
        AnrTable $anrTable,
        AnrMetadatasOnInstancesTable $anrMetadatasOnInstancesTable,
        TranslationTable $translationTable,
        ConfigService $configService,
        ConnectedUserService $connectedUserService
    ) {
        parent::__construct(
            $anrTable,
            $anrMetadatasOnInstancesTable,
            $translationTable,
            $configService,
            $connectedUserService,
        );
    }

    /**
     * @param int $id
     *
     * @throws EntityNotFoundException
     */
    public function deleteMetadataOnInstances(int $id): void
    {
        $metadataToDelete = $this->anrMetadatasOnInstancesTable->findById($id);
        if ($metadataToDelete === null) {
            throw new EntityNotFoundException(sprintf('Metadata with ID %d is not found', $id));
        }

        if ($metadataToDelete->isDeletable()) {
            $this->anrMetadatasOnInstancesTable->remove($metadataToDelete);

            $translationsKeys[] = $metadataToDelete->getLabelTranslationKey();

            if (!empty($translationsKeys)) {
                $this->translationTable->deleteListByKeys($translationsKeys);
            }
        }
    }

    /**
     * @param int $anrId
     * @param array $data
     *
     * @return array
     * @throws EntityNotFoundException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function createAnrMetadatasOnInstances(int $anrId, array $data): array
    {
        $anr = $this->anrTable->findById($anrId);
        $returnValue = [];
        $data = (isset($data['metadatas']) ? $data['metadatas'] : $data);
        foreach ($data as $inputMetadata) {
            $metadata = (new AnrMetadatasOnInstances())
                ->setAnr($anr)
                ->setLabelTranslationKey((string)Uuid::uuid4())
                ->setCreator($this->connectedUser->getEmail())
                ->setIsDeletable(true);

            $this->anrMetadatasOnInstancesTable->save($metadata);
            $returnValue[] = $metadata->getId();

            foreach ($inputMetadata as $lang => $labelText) {
                $translation = $this->createTranslationObject(
                    $anr,
                    Translation::ANR_METADATAS_ON_INSTANCES,
                    $metadata->getLabelTranslationKey(),
                    $lang,
                    (string)$labelText
                );
                $this->translationTable->save($translation);
            }
        }

        return $returnValue;
    }

    protected function createTranslationObject(
        AnrSuperClass $anr,
        string $type,
        string $key,
        string $lang,
        string $value
    ): TranslationSuperClass {
        return (new Translation())
            ->setAnr($anr)
            ->setType($type)
            ->setKey($key)
            ->setLang($lang)
            ->setValue($value)
            ->setCreator($this->connectedUser->getEmail());
    }
}
