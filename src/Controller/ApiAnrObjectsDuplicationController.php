<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Service\AnrObjectService;
use Monarc\FrontOffice\Validator\InputValidator\Object\DuplicateObjectDataInputValidator;

class ApiAnrObjectsDuplicationController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    private AnrObjectService $anrObjectService;
    private DuplicateObjectDataInputValidator $duplicateObjectDataInputValidator;

    public function __construct(
        AnrObjectService $anrObjectService,
        DuplicateObjectDataInputValidator $duplicateObjectDataInputValidator
    ) {
        $this->anrObjectService = $anrObjectService;
        $this->duplicateObjectDataInputValidator = $duplicateObjectDataInputValidator;
    }

    public function create($data)
    {
        $this->validatePostParams($this->duplicateObjectDataInputValidator, $data);

        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $object = $this->anrObjectService->duplicate($anr, $this->duplicateObjectDataInputValidator->getValidData());

        return $this->getSuccessfulJsonResponse(['id' => $object->getUuid()]);
    }
}
