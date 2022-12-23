<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\ConfigService;
use Monarc\FrontOffice\Helper\FileUploadHelper;
use Monarc\FrontOffice\Import\Service\InstanceImportService;

class ApiAnrInstancesImportController extends AbstractRestfulController
{
    private InstanceImportService $instanceImportService;

    private FileUploadHelper $fileUploadHelper;

    private array $importConfig;

    public function __construct(
        InstanceImportService $instanceImportService,
        FileUploadHelper $fileUploadHelper,
        ConfigService $configService
    ) {
        $this->instanceImportService = $instanceImportService;
        $this->importConfig = $configService->getConfigOption('import') ? : [];
        $this->fileUploadHelper = $fileUploadHelper;
    }

    public function create($data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }

        $files = $this->params()->fromFiles('file');
        if (empty($files)) {
            throw new Exception('File missing', 412);
        }
        $data['file'] = $files;

        if (!empty($this->importConfig['isBackgroundProcessActive'])) {
            // 1. Upload file
            $tmpFile = current($files);
            $fileName = $anrId . '-' . $tmpFile['name'];
            $this->fileUploadHelper->moveTmpFile(current($files), $this->importConfig['uploadFolder'], $fileName);
            // 2. Create a job to process it later.

            // 3. result with empty IDS and no err.
            // 4. Maybe additional value of status or prop tels that in progress.
        } else {
            [$ids, $errors] = $this->instanceImportService->importFromFile($anrId, $data);
        }

        return new JsonModel([
            'status' => 'ok',
            'id' => $ids,
            'errors' => $errors,
        ]);
    }
}
