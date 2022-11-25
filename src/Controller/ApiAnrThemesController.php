<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\InputFormatter\Theme\GetThemesInputFormatter;
use Monarc\Core\Validator\InputValidator\Theme\PostThemeDataInputValidator;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Service\AnrThemeService;

class ApiAnrThemesController extends AbstractRestfulController
{
    use ControllerRequestResponseHandlerTrait;

    private AnrThemeService $anrThemeService;

    private GetThemesInputFormatter $getThemesInputFormatter;

    private PostThemeDataInputValidator $postThemeDataInputValidator;

    public function __construct(
        GetThemesInputFormatter $getThemesInputFormatter,
        PostThemeDataInputValidator $postThemeDataInputValidator,
        AnrThemeService $anrThemeService
    ) {
        $this->anrThemeService = $anrThemeService;
        $this->getThemesInputFormatter = $getThemesInputFormatter;
        $this->postThemeDataInputValidator = $postThemeDataInputValidator;
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

        return $this->getPreparedJsonResponse([
            'status' => 'ok',
            'id' => $theme->getId(),
        ]);
    }


    public function update($id, $data)
    {
        $this->validatePostParams($this->postThemeDataInputValidator, $data);

        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->anrThemeService->update($anr, $id, $data);

        return $this->getPreparedJsonResponse([
            'status' => 'ok',
        ]);
    }

    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->anrThemeService->delete($anr, $id);

        return $this->getPreparedJsonResponse([
            'status' => 'ok',
        ]);
    }
}
