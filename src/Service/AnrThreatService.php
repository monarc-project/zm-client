<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\InputFormatter\FormattedInputParams;
use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Theme;
use Monarc\FrontOffice\Entity\Threat;
use Monarc\FrontOffice\Table;

class AnrThreatService
{
    private UserSuperClass $connectedUser;

    public function __construct(
        private Table\ThreatTable $threatTable,
        private Table\InstanceRiskTable $instanceRiskTable,
        private AnrInstanceRiskService $anrInstanceRiskService,
        private Table\ThemeTable $themeTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getList(FormattedInputParams $params): array
    {
        $result = [];

        /** @var Threat $threat */
        foreach ($this->threatTable->findByParams($params) as $threat) {
            $result[] = $this->prepareThreatDataResult($threat);
        }

        return $result;
    }

    public function getCount(FormattedInputParams $params): int
    {
        return $this->threatTable->countByParams($params);
    }

    public function getThreatData(Anr $anr, string $uuid): array
    {
        /** @var Threat $threat */
        $threat = $this->threatTable->findByUuidAndAnr($uuid, $anr);

        return $this->prepareThreatDataResult($threat);
    }

    public function create(Anr $anr, array $data, bool $saveInDb = true): Threat
    {
        $threat = (new Threat())
            ->setAnr($anr)
            ->setCode($data['code'])
            ->setLabels($data)
            ->setDescriptions($data)
            ->setComment($threatData['comment'] ?? '')
            ->setCreator($this->connectedUser->getEmail());
        if (isset($data['uuid'])) {
            $threat->setUuid($data['uuid']);
        }
        if (isset($data['c']) || isset($data['confidentiality'])) {
            $threat->setConfidentiality(isset($data['c']) ? (int)$data['c'] : (int)$data['confidentiality']);
        }
        if (isset($data['i']) || isset($data['integrity'])) {
            $threat->setIntegrity(isset($data['i']) ? (int)$data['i'] : (int)$data['integrity']);
        }
        if (isset($data['a']) || isset($data['availability'])) {
            $threat->setAvailability(isset($data['a']) ? (int)$data['a'] : (int)$data['availability']);
        }
        if (isset($data['mode'])) {
            $threat->setMode((int)$data['mode']);
        }
        if (isset($data['status'])) {
            $threat->setStatus($data['status']);
        }
        if (isset($data['trend'])) {
            $threat->setTrend((int)$data['trend']);
        }
        if (isset($data['qualification'])) {
            $threat->setTrend((int)$data['qualification']);
        }

        $this->validateCia($threat);

        if (!empty($data['theme'])) {
            /** @var Theme $theme */
            $theme = $data['theme'] instanceof Theme
                ? $data['theme']
                : $this->themeTable->findById((int)$data['theme']);

            $threat->setTheme($theme);
        }

        $this->threatTable->save($threat, $saveInDb);

        return $threat;
    }

    public function createList(Anr $anr, array $data): array
    {
        $createdUuids = [];
        foreach ($data as $row) {
            $createdUuids[] = $this->create($anr, $row, false)->getUuid();
        }
        $this->threatTable->flush();

        return $createdUuids;
    }

    public function update(Anr $anr, string $uuid, array $data): Threat
    {
        /** @var Threat $threat */
        $threat = $this->threatTable->findByUuidAndAnr($uuid, $anr);

        $this->manageQualification($threat, $data);

        $threat->setCode($data['code'])
            ->setLabels($data)
            ->setDescriptions($data)
            ->setConfidentiality((int)$data['c'])
            ->setIntegrity((int)$data['i'])
            ->setAvailability((int)$data['a'])
            ->setUpdater($this->connectedUser->getEmail());
        if (isset($data['status'])) {
            $threat->setStatus($data['status']);
        }
        if (isset($data['trend'])) {
            $threat->setTrend((int)$data['trend']);
        }
        if (isset($data['comment'])) {
            $threat->setComment($data['comment']);
        }
        if (!empty($data['theme']) && (
            $threat->getTheme() === null || $threat->getTheme()->getId() !== (int)$data['theme']
        )) {
            /** @var Theme $theme */
            $theme = $this->themeTable->findById((int)$data['theme']);
            $threat->setTheme($theme);
        }

        $this->threatTable->save($threat);

        return $threat;
    }

    public function patch(Anr $anr, string $uuid, array $data): Threat
    {
        /** @var Threat $threat */
        $threat = $this->threatTable->findByUuidAndAnr($uuid, $anr);

        if (isset($data['status'])) {
            $threat->setStatus((int)$data['status'])
                ->setUpdater($this->connectedUser->getEmail());

            $this->threatTable->save($threat);
        }

        return $threat;
    }

    public function delete(Anr $anr, string $uuid): void
    {
        /** @var Threat $threat */
        $threat = $this->threatTable->findByUuidAndAnr($uuid, $anr);

        $this->threatTable->remove($threat);
    }

    public function deleteList(Anr $anr, array $data): void
    {
        $threats = $this->threatTable->findByUuidsAndAnr($data, $anr);

        $this->threatTable->removeList($threats);
    }

    /**
     * Can be set from 1st step, 2nd sub-step "Threats assessment".
     * Updates the qualifications for the specified threat whenever they are created or updated.
     * This noticeably handles qualification values inheritance.
     */
    private function manageQualification(Threat $threat, array $data): void
    {
        if (isset($data['qualification'])) {
            $threat->setQualification($data['qualification']);

            $instancesRisks = $this->instanceRiskTable->findByAnrAndThreatExcludeLocallySet(
                $threat->getAnr(),
                $threat,
                empty($data['forceQualification'])
            );
            foreach ($instancesRisks as $instanceRisk) {
                $instanceRisk->setThreatRate((int)$data['qualification']);

                /* If qualification is forced, the instances risks threatRate's value is set from outside. */
                if (!empty($data['forceQualification'])) {
                    $instanceRisk->setIsThreatRateNotSetOrModifiedExternally(true);
                }

                $this->anrInstanceRiskService->recalculateRiskRatesAndUpdateRecommendationsPositions($instanceRisk);

                $this->instanceRiskTable->save($instanceRisk, false);
            }
        }
    }

    private function prepareThreatDataResult(Threat $threat): array
    {
        $themeData = null;
        if ($threat->getTheme() !== null) {
            $themeData = array_merge([
                'id' => $threat->getTheme()->getId(),
            ], $threat->getTheme()->getLabels());
        }

        return array_merge($threat->getLabels(), $threat->getDescriptions(), [
            'uuid' => $threat->getUuid(),
            'anr' => [
                'id' => $threat->getAnr()->getId(),
            ],
            'code' => $threat->getCode(),
            'c' => $threat->getConfidentiality(),
            'i' => $threat->getIntegrity(),
            'a' => $threat->getAvailability(),
            'theme' => $themeData,
            'trend' => $threat->getTrend(),
            'qualification' => $threat->getQualification(),
            'mode' => $threat->getMode(),
            'comment' => $threat->getComment(),
            'status' => $threat->getStatus(),
        ]);
    }

    private function validateCia(Threat $threat): void
    {
        if ($threat->getConfidentiality() === 0 && $threat->getAvailability() === 0 && $threat->getIntegrity() === 0) {
            throw new \Exception(
                \sprintf('One of the CIA criteria is required to be set for the threat "%s".', $threat->getUuid())
            );
        }
    }
}
