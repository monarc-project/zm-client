<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2025 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Service\CaptchaService;

class ApiCaptchaController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(private CaptchaService $captchaService)
    {
    }

    public function getList()
    {
        if ($this->captchaService->isActivated()) {
            return $this->getPreparedJsonResponse(
                array_merge(['isCaptchaActivated' => true], $this->captchaService->generate())
            );
        }

        return $this->getPreparedJsonResponse(['isCaptchaActivated' => false]);
    }

    /**
     * @param array $data
     */
    public function create($data)
    {
        return $this->getSuccessfulJsonResponse(
            ['isCaptchaValid' => $this->captchaService->isValid($data['captchaId'], $data['captchaInput'])]
        );
    }
}
