<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Traits;

use Monarc\Core\Entity\InstanceSuperClass;
use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Instance;

trait ImportValidationTrait
{
    private string $importingDataVersion = '';

    /**
     * Validates if the data can be imported into the anr.
     */
    private function validateIfImportIsPossible(Anr $anr, ?Instance $parent, array $data): void
    {
        if ($parent !== null
            && ($parent->getLevel() === InstanceSuperClass::LEVEL_INTER || $parent->getAnr() !== $anr)
        ) {
            throw new Exception('Parent instance should be in the node tree and the analysis IDs are matched', 412);
        }

        if ((!empty($data['with_eval']) || !empty($data['withEval'])) && empty($data['scales'])) {
            throw new Exception('The importing file should include evaluation scales.', 412);
        }

        if (!$this->isImportingDataVersionLowerThan('2.13.1') && $anr->getLanguageCode() !== $data['languageCode']) {
            throw new Exception(sprintf(
                'The current analysis language "%s" should be the same as importing one "%s"',
                $anr->getLanguageCode(),
                $data['languageCode']
            ), 412);
        }
    }

    /**
     * @throws Exception
     */
    private function setAndValidateImportingDataVersion($data): void
    {
        if (isset($data['monarc_version'])) {
            $this->importingDataVersion = !str_contains($data['monarc_version'], 'master')
                ? $data['monarc_version']
                : '999';
        }

        if ($this->isImportingDataVersionLowerThan('2.8.2')) {
            throw new Exception('Import of files exported from MONARC v2.8.1 or lower are not supported.'
                . ' Please contact us for more details.');
        }
    }

    private function isImportingDataVersionLowerThan(string $version): bool
    {
        if ($this->importingDataVersion === '') {
            throw new \LogicException('The "monarc_version" parameter has to be defined in the file data structure.');
        }

        return version_compare($this->importingDataVersion, $version) < 0;
    }
}
