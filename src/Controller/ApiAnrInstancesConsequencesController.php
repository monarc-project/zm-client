<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Model\Entity\Anr;
use Monarc\FrontOffice\Service\AnrInstanceConsequenceService;

class ApiAnrInstancesConsequencesController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    private AnrInstanceConsequenceService $anrInstanceConsequenceService;

    public function __construct(AnrInstanceConsequenceService $anrInstanceConsequenceService)
    {
        $this->anrInstanceConsequenceService = $anrInstanceConsequenceService;
    }

    /**
     * The patch endpoint is called only when we hide/show a consequence.
     * Possible data params: $data['isHidden'] = 0|1
     */
    public function patch($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrInstanceConsequenceService->patchConsequence($anr, $id, $data);

        return $this->getSuccessfulJsonResponse();
    }
}
