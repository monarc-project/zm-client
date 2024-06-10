<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Processor;

use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Service\AnrRolfTagService;
use Monarc\FrontOffice\Table\RolfTagTable;

class RolfTagImportProcessor
{
    public function __construct(
        private RolfTagTable $rolfTagTable,
        private ImportCacheHelper $importCacheHelper,
        private AnrRolfTagService $anrRolfTagService
    ) {
    }

    public function processRolfTagsData(Entity\Anr $anr, array $rolfTagsData): void
    {
        $this->prepareRolfTagsCache($anr);
        foreach ($rolfTagsData as $rolfTagData) {
            $this->processRolfTagData($anr, $rolfTagData);
        }
    }

    public function processRolfTagData(Entity\Anr $anr, array $rolfTagData): Entity\RolfTag
    {
        $rolfTag = $this->getRolfTagFromCache($rolfTagData['code']);
        if ($rolfTag !== null) {
            return $rolfTag;
        }

        /* In the new data structure there is only "label" field set. */
        if (isset($rolfTagData['label'])) {
            $rolfTagData['label' . $anr->getLanguage()] = $rolfTagData['label'];
        }

        $rolfTag = $this->anrRolfTagService->create($anr, $rolfTagData, false);
        $this->importCacheHelper->addItemToArrayCache('rolf_tags_by_code', $rolfTag, $rolfTag->getCode());

        return $rolfTag;
    }

    public function getRolfTagFromCache(string $code): ?Entity\RolfTag
    {
        return $this->importCacheHelper->getItemFromArrayCache('rolf_tags_by_code', $code);
    }

    public function prepareRolfTagsCache(Entity\Anr $anr): void
    {
        if (!$this->importCacheHelper->isCacheKeySet('rolf_tags_by_code')) {
            /** @var Entity\RolfTag $rolfTag */
            foreach ($this->rolfTagTable->findByAnr($anr) as $rolfTag) {
                $this->importCacheHelper->addItemToArrayCache('rolf_tags_by_code', $rolfTag, $rolfTag->getCode());
            }
        }
    }
}
