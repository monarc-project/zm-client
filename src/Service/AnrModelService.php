<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Table\ModelTable;
use Monarc\FrontOffice\Table\ClientTable;

class AnrModelService
{
    public function __construct(private ModelTable $modelTable, private ClientTable $clientTable)
    {
    }

    public function getModelsListOfClient(): array
    {
        $result = [];
        $modelIds = [];
        foreach ($this->clientTable->findFirstClient()->getClientModels() as $clientModel) {
            $modelIds[] = $clientModel->getModelId();
        }
        foreach ($this->modelTable->fundGenericsAndSpecificsByIds($modelIds) as $model) {
            $result[] = array_merge(['id' => $model->getId()], $model->getLabels());
        }

        return $result;
    }
}
