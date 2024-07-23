<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Traits;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Helper\EncryptDecryptHelperTrait;

trait ImportFileContentTrait
{
    use EncryptDecryptHelperTrait;

    /**
     * @throws Exception
     * @throws \JsonException
     *
     * @return array|bool
     */
    private function getArrayDataOfJsonFileContent(string $fileName, ?string $password)
    {
        if (empty($password)) {
            return json_decode(file_get_contents($fileName), true, 512, JSON_THROW_ON_ERROR);
        }

        $decryptedResult = $this->decrypt(file_get_contents($fileName), $password);
        if ($decryptedResult === false) {
            throw new Exception('Password is not correct.', 412);
        }

        return json_decode($decryptedResult, true, 512, JSON_THROW_ON_ERROR);
    }
}
