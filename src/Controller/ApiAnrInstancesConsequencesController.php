<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Validator\InputValidator\InstanceConsequence\PatchConsequenceDataInputValidator;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service\AnrInstanceConsequenceService;

class ApiAnrInstancesConsequencesController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private AnrInstanceConsequenceService $anrInstanceConsequenceService,
        private PatchConsequenceDataInputValidator $patchConsequenceDataInputValidator
    ) {
    }

    /**
     * The patch endpoint is called only when hide/show consequence action is performed.
     * Possible data params: $data['isHidden'] = 0|1
     */
    public function patch($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->validatePostParams($this->patchConsequenceDataInputValidator, $data);

        $this->anrInstanceConsequenceService
            ->patchConsequence($anr, (int)$id, $this->patchConsequenceDataInputValidator->getValidData());

        return $this->getSuccessfulJsonResponse();
    }
}
