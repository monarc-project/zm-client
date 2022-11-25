<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\InputFormatter\FormattedInputParams;
use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Theme;
use Monarc\FrontOffice\Model\Entity\Threat;
use Monarc\FrontOffice\Model\Table\InstanceRiskTable;
use Monarc\FrontOffice\Table\ThemeTable;
use Monarc\FrontOffice\Table\ThreatTable;

class AnrThreatService
{
    private ThreatTable $threatTable;

    private InstanceRiskTable $instanceRiskTable;

    private ThemeTable $themeTable;

    private AnrInstanceRiskService $anrInstanceRiskService;

    private UserSuperClass $connectedUser;

    public function __construct(
        ThreatTable $threatTable,
        InstanceRiskTable $instanceRiskTable,
        AnrInstanceRiskService $anrInstanceRiskService,
        ThemeTable $themeTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->threatTable = $threatTable;
        $this->instanceRiskTable = $instanceRiskTable;
        $this->anrInstanceRiskService = $anrInstanceRiskService;
        $this->themeTable = $themeTable;
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getList(FormattedInputParams $params): array
    {
        $result = [];

        /** @var Threat[] $threats */
        $threats = $this->threatTable->findByParams($params);
        foreach ($threats as $threat) {
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
            ->setConfidentiality((int)$data['c'])
            ->setIntegrity((int)$data['i'])
            ->setAvailability((int)$data['a'])
            ->setCreator($this->connectedUser->getEmail());
        if (isset($data['uuid'])) {
            $threat->setUuid($data['uuid']);
        }
        if (isset($data['mode'])) {
            $threat->setMode((int)$data['mode']);
        }
        if (isset($data['status'])) {
            $threat->setStatus($data['status']);
        }

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

    public function update(Anr $anr, string $uuid, array $data): void
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
        if (isset($data['mode'])) {
            $threat->setMode($data['mode']);
        }
        if (isset($data['status'])) {
            $threat->setStatus($data['status']);
        }
        if (isset($data['trend'])) {
            $threat->setTrend((int)$data['trend']);
        }
        if (isset($data['comment'])) {
            $threat->setComment($data['comment']);
        }
        if (!empty($data['theme'])
            && ($threat->getTheme() === null
                || $threat->getTheme()->getId() !== (int)$data['theme']
            )
        ) {
            /** @var Theme $theme */
            $theme = $this->themeTable->findById((int)$data['theme']);
            $threat->setTheme($theme);
        }

        $this->threatTable->save($threat);
    }

    public function patch(Anr $anr, string $uuid, array $data): void
    {
        /** @var Threat $threat */
        $threat = $this->threatTable->findByUuidAndAnr($uuid, $anr);

        $this->manageQualification($threat, $data);

        // todo: not only status is updated here.

        $this->threatTable->save($threat);
    }

    /**
     * Updates the qualifications for the specified threat whenever they are created or updated. This noticeably
     * handles qualification values inheritance.
     */
    public function manageQualification(Threat $threat, array $data): void
    {
        if (isset($data['qualification'])) {
            // todo: how we can set the qualification ???
            $filter = [
                'anr' => $threat->getAnr()->getId(),
                'threat' => $threat->getUuid(),
            ];

            // If qualification is not forced, retrieve only inherited instance risksx
            if (!isset($data['forceQualification']) || $data['forceQualification'] === 0) {
                $filter['mh'] = 1;
            }

            /** @var InstanceRiskTable $instanceRiskTable */
            $instanceRiskTable = $this->get('instanceRiskTable');
            $instancesRisks = $instanceRiskTable->getEntityByFields($filter);

            /** @var AnrInstanceRiskService $instanceRiskService */
            $instanceRiskService = $this->get('instanceRiskService');

            foreach ($instancesRisks as $i => $instanceRisk) {
                $instanceRisk->threatRate = $data['qualification'];

                // If qualification is forced, instances risks become inherited
                if (isset($data['forceQualification']) && $data['forceQualification'] === 1) {
                    $instanceRisk->mh = 1;
                }

                $instanceRiskTable->saveEntity($instanceRisk, false);

                $instanceRiskService->updateRisks($instanceRisk);
                $instanceRiskService->updateInstanceRiskRecommendationsPositions($instanceRisk);
            }

            $instanceRiskTable->getDb()->flush();
        }
    }

    private function prepareThreatDataResult(Threat $threat): array
    {
        $theme = null;
        if ($threat->getTheme() !== null) {
            $theme = [
                'id' => $threat->getTheme()->getId(),
                'label1' => $threat->getTheme()->getLabel(1),
                'label2' => $threat->getTheme()->getLabel(2),
                'label3' => $threat->getTheme()->getLabel(3),
                'label4' => $threat->getTheme()->getLabel(4),
            ];
        }

        return [
            'uuid' => $threat->getUuid(),
            'code' => $threat->getCode(),
            'label1' => $threat->getLabel(1),
            'label2' => $threat->getLabel(2),
            'label3' => $threat->getLabel(3),
            'label4' => $threat->getLabel(4),
            'description1' => $threat->getDescription(1),
            'description2' => $threat->getDescription(2),
            'description3' => $threat->getDescription(3),
            'description4' => $threat->getDescription(4),
            'c' => $threat->getConfidentiality(),
            'i' => $threat->getIntegrity(),
            'a' => $threat->getAvailability(),
            'theme' => $theme,
            'trend' => $threat->getTrend(),
            'qualification' => $threat->getQualification(),
            'comment' => $threat->getComment(),
            'status' => $threat->getStatus(),
        ];
    }
}
