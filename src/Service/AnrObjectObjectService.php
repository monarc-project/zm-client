<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\MonarcObject;
use Monarc\FrontOffice\Model\Entity\ObjectObject;
use Monarc\FrontOffice\Table\MonarcObjectTable;
use Monarc\FrontOffice\Table\ObjectObjectTable;

class AnrObjectObjectService
{
    private ObjectObjectTable $objectObjectTable;

    private MonarcObjectTable $monarcObjectTable;

    private AnrInstanceService $anrInstanceService;

    private UserSuperClass $connectedUser;

    public function __construct(
        ObjectObjectTable $objectObjectTable,
        MonarcObjectTable $monarcObjectTable,
        AnrInstanceService $anrInstanceService,
        ConnectedUserService $connectedUserService
    ) {
        $this->objectObjectTable = $objectObjectTable;
        $this->monarcObjectTable = $monarcObjectTable;
        $this->anrInstanceService = $anrInstanceService;
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function create(Anr $anr, array $data): ObjectObject
    {
        if ($data['parent'] === $data['child']) {
            throw new Exception('It\'s not allowed to compose the same child object as parent.', 412);
        }

        /** @var MonarcObject $parentObject */
        $parentObject = $this->monarcObjectTable->findById(['anr' => $anr, 'uuid' => $data['parent']]);
        /** @var MonarcObject $childObject */
        $childObject = $this->monarcObjectTable->findById(['anr' => $anr, 'uuid' => $data['child']]);
        if ($parentObject->hasChild($childObject)) {
            throw new Exception('The object is already presented in the composition.', 412);
        }

        /* Validate if one of the parents is the current child. */
    }
}
