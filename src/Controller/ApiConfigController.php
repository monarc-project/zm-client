<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHL.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Service\ConfigService;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

class ApiConfigController extends AbstractRestfulController
{
    private ConfigService $configService;

    public function __construct(ConfigService $configService)
    {
        $this->configService = $configService;
    }

    public function getList()
    {
        return new JsonModel(array_merge(
            $this->configService->getLanguage(),
            $this->configService->getAppVersion(),
            $this->configService->getCheckVersion(),
            $this->configService->getAppCheckingURL(),
            $this->configService->getMospApiUrl(),
            $this->configService->getTerms(),
            $this->configService->getConfigOption('import', [])
        ));
    }
}
