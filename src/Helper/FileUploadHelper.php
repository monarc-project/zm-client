<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Helper;

use Monarc\Core\Exception\Exception;

class FileUploadHelper
{
    public function moveTmpFile(array $tmpFile, $destinationPath, $filename): void
    {
        if ($tmpFile['error'] !== UPLOAD_ERR_OK) {
            throw new Exception(sprintf(
                'An error occurred during the file upload. Error code: %d',
                (int)$tmpFile['error']
            ));
        }

        if (!move_uploaded_file(basename($tmpFile['tmp_name']), $destinationPath . DIRECTORY_SEPARATOR . $filename)) {
            throw new Exception(
                'The file cant be saved, please check if the destination directory exists and has write permissions.',
            );
        }
    }
}
