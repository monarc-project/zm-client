<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Monarc\Core\InputFormatter\Referential\GetReferentialInputFormatter;
use Monarc\Core\Service\ReferentialService;

class ApiCoreReferentialsController extends AbstractRestfulController
{
    public function __construct(
        private ReferentialService $referentialService,
        private GetReferentialInputFormatter $getReferentialInputFormatter
    ) {
    }

    /**
     * Used in create or edit analysis actions.
     */
    public function getList()
    {
        $frameworks = $this->referentialService->getList(
            $this->getFormattedInputParams($this->getReferentialInputFormatter)
        );

        return new JsonModel([
            'count' => \count($frameworks),
            'referentials' => $frameworks,
        ]);
    }
}
