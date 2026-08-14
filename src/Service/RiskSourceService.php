<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Exception\Exception;
use Monarc\Core\InputFormatter\FormattedInputParams;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\RiskSource;
use Monarc\FrontOffice\Table\RiskSourceTable;

class RiskSourceService
{
    private UserSuperClass $connectedUser;

    public function __construct(
        private RiskSourceTable $riskSourceTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    /**
     * @return RiskSource[]
     */
    public function getList(FormattedInputParams $params): array
    {
        return $this->riskSourceTable->findByParams($params);
    }

    public function getCount(FormattedInputParams $params): int
    {
        return $this->riskSourceTable->countByParams($params, 'id');
    }

    public function get(Anr $anr, int $id): RiskSource
    {
        /** @var RiskSource $riskSource */
        $riskSource = $this->riskSourceTable->findByIdAndAnr($id, $anr);

        return $riskSource;
    }

    public function create(Anr $anr, array $data): RiskSource
    {
        $riskSource = (new RiskSource())
            ->setAnr($anr)
            ->setLabel(trim((string)$data['label']))
            ->setIsDefault(false)
            ->setIsActive((bool)($data['isActive'] ?? true))
            ->setCreator($this->connectedUser->getEmail());

        $this->riskSourceTable->save($riskSource);

        return $riskSource;
    }

    public function update(Anr $anr, int $id, array $data): RiskSource
    {
        $riskSource = $this->get($anr, $id);

        if (isset($data['label'])) {
            $riskSource->setLabel(trim((string)$data['label']));
        }
        if (isset($data['isActive'])) {
            $riskSource->setIsActive((bool)$data['isActive']);
        }

        $riskSource->setUpdater($this->connectedUser->getEmail());

        $this->riskSourceTable->save($riskSource);

        return $riskSource;
    }

    public function delete(Anr $anr, int $id): void
    {
        $riskSource = $this->get($anr, $id);

        if ($riskSource->isDefault()) {
            throw new Exception('Default risk sources cannot be removed.', 412);
        }
        if ($this->riskSourceTable->isUsedInRisks($riskSource)) {
            throw new Exception('Risk source linked to instance risks cannot be removed.', 412);
        }

        $this->riskSourceTable->remove($riskSource);
    }
}
