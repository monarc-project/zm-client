<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Monarc\FrontOffice\Service\AnrService;

class ApiModelVerifyLanguageController extends AbstractRestfulController
{
    private AnrService $anrService;

    public function __construct(AnrService $anrService)
    {
        $this->anrService = $anrService;
    }

    public function get($id)
    {
        return new JsonModel($this->anrService->getModelAvailableLanguages((int)$id));
    }
}
