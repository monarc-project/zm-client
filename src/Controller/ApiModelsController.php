<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\InputFormatter\FormattedInputParams;
use Monarc\Core\Service\ModelService;
use Laminas\View\Model\JsonModel;
use Monarc\FrontOffice\Table\ClientTable;

class ApiModelsController extends AbstractRestfulController
{
    use ControllerRequestResponseHandlerTrait;

    private ModelService $modelService;

    private ClientTable $clientTable;

    public function __construct(ModelService $modelService, ClientTable $clientTable)
    {
        $this->modelService = $modelService;
        $this->clientTable = $clientTable;
    }

    public function getList()
    {
        // TODO: support multiple models...
        $formattedParams = (new FormattedInputParams())->addFilter('isGeneric', ['value' => 0]);
        $models = $this->modelService->getList($formattedParams);

        $formattedParams->addFilter('isGeneric', ['value' => 1]);
        $client = $this->clientTable->findFirstClient();
        if ($client->getModelId()) {
            $formattedParams->addFilter('id', ['value' => $client->getModelId()]);
        }

        return new JsonModel([
            'models' => array_merge($models, $this->modelService->getList($formattedParams)),
        ]);
    }
}
