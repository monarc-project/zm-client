<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\InputFormatter\Soa\GetSoasInputFormatter;
use Monarc\FrontOffice\Service\SoaService;

class ApiSoaController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(private SoaService $soaService, private GetSoasInputFormatter $getSoasInputFormatter)
    {
    }

    public function getList()
    {
        return $this->getPreparedJsonResponse([
            'soaMeasures' => $this->soaService->getList($this->getFormattedInputParams($this->getSoasInputFormatter)),
            'count' => $this->soaService->getCount($this->getFormattedInputParams($this->getSoasInputFormatter))
        ]);
    }

    public function patch($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->soaService->patchSoa($anr, (int)$id, $data);

        return $this->getSuccessfulJsonResponse();
    }

    public function patchList($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getSuccessfulJsonResponse(['id' => $this->soaService->patchList($anr, $data)]);
    }
}
