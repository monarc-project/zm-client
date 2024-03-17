<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\InputFormatter\Theme\GetThemesInputFormatter;
use Monarc\Core\Validator\InputValidator\Theme\PostThemeDataInputValidator;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service\AnrThemeService;

class ApiAnrThemesController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private GetThemesInputFormatter $getThemesInputFormatter,
        private PostThemeDataInputValidator $postThemeDataInputValidator,
        private AnrThemeService $anrThemeService
    ) {
    }

    public function getList()
    {
        $formatterParams = $this->getFormattedInputParams($this->getThemesInputFormatter);

        return $this->getPreparedJsonResponse([
            'themes' => $this->anrThemeService->getList($formatterParams),
        ]);
    }

    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getPreparedJsonResponse($this->anrThemeService->getThemeData($anr, (int)$id));
    }

    public function create($data)
    {
        $this->validatePostParams($this->postThemeDataInputValidator, $data);

        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $theme = $this->anrThemeService->create($anr, $data);

        return $this->getSuccessfulJsonResponse(['id' => $theme->getId()]);
    }


    public function update($id, $data)
    {
        $this->validatePostParams($this->postThemeDataInputValidator, $data);

        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->anrThemeService->update($anr, $id, $data);

        return $this->getSuccessfulJsonResponse();
    }

    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->anrThemeService->delete($anr, $id);

        return $this->getSuccessfulJsonResponse();
    }
}
