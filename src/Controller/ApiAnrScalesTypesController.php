<?php
declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service\AnrScaleImpactTypeService;

class ApiAnrScalesTypesController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(private AnrScaleImpactTypeService $scaleImpactTypeService)
    {
    }

    public function getList()
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $scaleImpactTypesList = $this->scaleImpactTypeService->getList($anr);

        return $this->getPreparedJsonResponse([
            'count' => \count($scaleImpactTypesList),
            'types' => $scaleImpactTypesList,
        ]);
    }

    public function create($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $scaleType = $this->scaleImpactTypeService->create($anr, $data);

        return $this->getSuccessfulJsonResponse(['id' => $scaleType->getId()]);
    }

    public function patch($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->scaleImpactTypeService->patch($anr, $id, $data);

        return $this->getSuccessfulJsonResponse();
    }

    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->scaleImpactTypeService->delete($anr, (int)$id);

        return $this->getSuccessfulJsonResponse();
    }
}
