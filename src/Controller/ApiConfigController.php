<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Service\ConfigService;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

/**
 * Api Config Controller
 *
 * Class ApiConfigController
 * @package Monarc\FrontOffice\Controller
 */
class ApiConfigController extends AbstractRestfulController
{
    /** @var ConfigService */
    private $configService;

    // TODO: remove all the core dependencies -> move to core-lib if there is no other way.
    public function __construct(ConfigService $configService)
    {
        $this->configService = $configService;
    }


    /**
     * @inheritdoc
     */
    public function getList()
    {
        return new JsonModel(array_merge(
            $this->configService->getLanguage(),
            $this->configService->getAppVersion(),
            $this->configService->getCheckVersion(),
            $this->configService->getAppCheckingURL(),
            $this->configService->getMospApiUrl(),
            $this->configService->getTerms())
        );
    }
}
