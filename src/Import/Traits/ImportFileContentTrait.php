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
    private function getArrayDataOfJsonFileContent(
        string $fileName,
        ?string $password,
        bool $allowLegacyEncoding = false
    ) {
        $content = file_get_contents($fileName);
        $json = empty($password) ? $content : $this->decrypt($content, $password);

        // Older record exports wrapped encrypted content in an additional base64 encoding.
        if ($allowLegacyEncoding && !is_array(json_decode((string)$json, true))) {
            $json = $this->decrypt(base64_decode($content), empty($password) ? '' : $password);
        }

        if ($json === false) {
            throw new Exception('Password is not correct.', 412);
        }

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
}
