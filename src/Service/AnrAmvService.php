<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\ORMException;
use Monarc\Core\InputFormatter\FormattedInputParams;
use Monarc\Core\Model\Entity\AmvSuperClass;
use Monarc\Core\Model\Entity\InstanceRiskSuperClass;
use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity;
use Monarc\FrontOffice\Model\Table\InstanceRiskTable;
use Monarc\FrontOffice\Model\Table\InstanceTable;
use Monarc\FrontOffice\Model\Table\MeasureTable;
use Monarc\FrontOffice\Model\Table\MonarcObjectTable;
use Monarc\FrontOffice\Model\Table\ReferentialTable;
use Monarc\FrontOffice\Table;
use Ramsey\Uuid\Uuid;

// TODO: refactor after asset & threat is done.
class AnrAmvService
{
    private Table\AmvTable $amvTable;

    private MonarcObjectTable $monarcObjectTable;

    private InstanceTable $instanceTable;

    private InstanceRiskTable $instanceRiskTable;

    private MeasureTable $measureTable;

    private ReferentialTable $referentialTable;

    private Table\ThreatTable $threatTable;

    private Table\ThemeTable $themeTable;

    private UserSuperClass $connectedUser;

    public function __construct(
        Table\AmvTable $amvTable,
        MonarcObjectTable $monarcObjectTable,
        InstanceTable $instanceTable,
        InstanceRiskTable $instanceRiskTable,
        MeasureTable $measureTable,
        ReferentialTable $referentialTable,
        Table\ThreatTable $threatTable,
        Table\ThemeTable $themeTable,
        ConnectedUserService $connectedUserService,
    ) {
        $this->amvTable = $amvTable;
        $this->monarcObjectTable = $monarcObjectTable;
        $this->instanceTable = $instanceTable;
        $this->instanceRiskTable = $instanceRiskTable;
        $this->measureTable = $measureTable;
        $this->referentialTable = $referentialTable;
        $this->threatTable = $threatTable;
        $this->themeTable = $themeTable;
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }


    public function getList(FormattedInputParams $params): array
    {
        $result = [];

        /** @var Entity\Amv[] $amvs */
        $amvs = $this->amvTable->findByParams($params);
        foreach ($amvs as $amv) {
            $result[] = $this->prepareAmvDataResult($amv);
        }

        return $result;
    }

    public function getCount($params): int
    {
        return $this->amvTable->countByParams($params);
    }

    public function getAmvData(string $uuid): array
    {
        $amv = $this->amvTable->findByUuid($uuid);

        return $this->prepareAmvDataResult($amv);
    }

    public function update(Entity\Anr $anr, $id, $data)
    {
        // TODO: anr obj is passed here
        /** @var Entity\Amv $amv */
        $amv = $this->amvTable->findByUuidAndAnr($id['uuid'], $anr);

        $linkedMeasuresUuids = array_column($data['measures'], 'uuid');
        foreach ($amv->getMeasures() as $measure) {
            $linkedMeasuresUuidKey = array_search($measure->getUuid(), $linkedMeasuresUuids, true);
            if ($linkedMeasuresUuidKey === false) {
                $amv->removeMeasure($measure);
                continue;
            }

            unset($data['measures'][$linkedMeasuresUuidKey]);
        }
        /** @var MeasureTable $measureTable */
        $measureTable = $this->get('measureTable');
        foreach ($data['measures'] as $measure) {
            $amv->addMeasure($measureTable->getEntity($measure));
        }

        $amv->setUpdater($this->getConnectedUser()->getFirstname() . ' ' . $this->getConnectedUser()->getLastname());

        $isThreatChanged = $this->isThreatChanged($data, $amv);
        $isVulnerabilityChanged = $this->isVulnerabilityChanged($data, $amv);
        if ($isThreatChanged || $isVulnerabilityChanged) {
            $newAmv = (new Amv())
                ->setUuid(Uuid::uuid4())
                ->setAnr($amv->getAnr())
                ->setAsset($amv->getAsset())
                ->setThreat($amv->getThreat())
                ->setVulnerability($amv->getVulnerability())
                ->setMeasures($amv->getMeasures())
                ->setPosition($amv->getPosition())
                ->setStatus($amv->getStatus())
                ->setCreator($amv->getUpdater());

            if ($isThreatChanged) {
                $threat = $this->threatTable->findByUuidAndAnr($data['threat'], $amv->getAnr());
                $newAmv->setThreat($threat);
            }
            if ($isVulnerabilityChanged) {
                /** @var VulnerabilityTable $vulnerabilityTable */
                $vulnerabilityTable = $this->get('vulnerabilityTable');
                $vulnerability = $vulnerabilityTable->findByUuidAndAnr($data['vulnerability'], $amv->getAnr());
                $newAmv->setVulnerability($vulnerability);
            }

            /** @var InstanceRisk[] $instancesRisks */
            /** @var InstanceRiskTable $instanceRiskTable */
            $instanceRiskTable = $this->get('instanceRiskTable');
            $instancesRisks = $instanceRiskTable->findByAmv($amv);
            foreach ($instancesRisks as $instanceRisk) {
                $instanceRisk->setThreat($newAmv->getThreat());
                $instanceRisk->setVulnerability($newAmv->getVulnerability());
                $instanceRisk->setAmv($newAmv);
            }

            $amvTable->deleteEntity($amv, false);
            $amv = $newAmv;
        }

        $amvTable->saveEntity($amv);
    }

    public function patch($id, $data)
    {
        /** @var AmvTable $amvTable */
        $amvTable = $this->get('table');
        $amv = $amvTable->findByUuidAndAnrId($id['uuid'], (int)$data['anr']);

        if (isset($data['status'])) {
            $amv->setStatus((int)$data['status']);
        }
        $amv->setUpdater($this->getConnectedUser()->getFirstname() . ' ' . $this->getConnectedUser()->getLastname());

        $amvTable->saveEntity($amv);
    }

    /**
     * @inheritdoc
     */
    public function create(Entity\Anr $anr, $data, $last = true)
    {
        $amv = new Entity\Amv();
        // TODO: ...
        // TODO: create instance_risks as on Core done.

        //manage the measures separately because it's the slave of the relation amv<-->measures
        if (!empty($data['measures'])) {
            foreach ($data['measures'] as $measure) {
                $measureEntity = $this->get('measureTable')->getEntity($measure);
                $measureEntity->addAmv($amv);
            }
            unset($data['measures']);
        }
        $amv->exchangeArray($data);
        unset($data['measures']);
        $dependencies = (property_exists($this, 'dependencies')) ? $this->dependencies : [];
        $this->setDependencies($amv, $dependencies);

        $amv->setCreator(
            $this->getConnectedUser()->getFirstname() . ' ' . $this->getConnectedUser()->getLastname()
        );

        /** @var AmvTable $table */
        $table = $this->get('table');
        $id = $table->save($amv, $last);

        // Create instances risks
        /** @var MonarcObjectTable $MonarcObjectTable */
        $MonarcObjectTable = $this->get('MonarcObjectTable');
        $objects = $MonarcObjectTable->getEntityByFields([
            'anr' => $data['anr'],
            'asset' => [
                'uuid' => $amv->getAsset()->getUuid(),
                'anr' => $data['anr']
            ]
        ]);

        foreach ($objects as $object) {
            $instances = $this->instanceTable->findByAnrAndObject($anr, $object);
            foreach ($instances as $instance) {
                $instanceRisk = (new Entity\InstanceRisk())
                    ->setAnr($anr)
                    ->setAmv($amv)
                    ->setAsset($amv->getAsset())
                    ->setInstance($instance)
                    ->setThreat($amv->getThreat())
                    ->setVulnerability($amv->getVulnerability());

                $this->instanceRiskTable->saveEntity($instanceRisk, false);
            }
            $this->instanceRiskTable->getDb()->flush();
        }

        return $id;
    }

    /**
     * Creates the amv items (assets, threats, vulnerabilities) to use them for AMVs creation later.
     */
    public function createAmvItems(Entity\Anr $anr, array $data): array
    {
        // TODO: adjust the code as it is copied from Core!!!

        $createdItems = [];
        $extraCreationParams = ['anr' => $anrId];

        foreach ($data as $amvItem) {
            if (!empty($amvItem['asset']['uuid'])
                && !empty($amvItem['threat']['uuid'])
                && !empty($amvItem['vulnerability']['uuid'])
                && $this->amvTable->findByAmvItemsUuidsAndAnr(
                    $amvItem['asset']['uuid'],
                    $amvItem['threat']['uuid'],
                    $amvItem['vulnerability']['uuid'],
                    $anr
                )
            ) {
                continue;
            }

            if (isset($amvItem['threat']['theme']) && \is_array($amvItem['threat']['theme'])) {
                $labelKey = 'label' . $this->getLanguage();
                $labelValue = $amvItem['threat']['theme'][$labelKey];
                $theme = $this->themeTable->findByAnrAndLabel($anr, $labelKey, $labelValue);
                if ($theme === null) {
                    $theme = (new Entity\Theme())
                        ->setAnr($anr);
                    $theme->setLabels($amvItem['threat']['theme']);
                    $this->themeTable->save($theme);
                }

                $amvItem['threat']['theme'] = $theme->getId();
            }

            $createdItems[] = [
                'asset' => $this->createAmvItemOrGetUuid(
                    $assetService,
                    array_merge($amvItem['asset'], $extraCreationParams),
                    'asset'
                ),
                'threat' => $this->createAmvItemOrGetUuid(
                    $threatService,
                    array_merge($amvItem['threat'], $extraCreationParams),
                    'threat'
                ),
                'vulnerability' => $this->createAmvItemOrGetUuid(
                    $vulnerabilityService,
                    array_merge($amvItem['vulnerability'], $extraCreationParams),
                    'vulnerability'
                ),
            ];
        }

        return $createdItems;
    }


    /**
     * @param array $id
     *
     * @throws EntityNotFoundException|ORMException
     */
    public function delete($id)
    {
        $amv = $this->amvTable->findByUuidAndAnrId($id['uuid'], $id['anr']);
        $this->resetInstanceRisksRelations($amv);

        $amvTable->deleteEntity($amv);
    }

    public function deleteListFromAnr($data, $anrId = null)
    {
        // TODO: ...

        foreach ($data as $amvId) {
            $amv = $this->amvTable->findByUuidAndAnrId($amvId['uuid'], $amvId['anr']);
            $this->resetInstanceRisksRelations($amv);
        }

        return $this->get('table')->deleteList($anr, $data);
    }

    protected function isThreatChanged(array $data, AmvSuperClass $amv): bool
    {
        return $amv->getThreat()->getUuid() !== $data['threat'];
    }

    protected function isVulnerabilityChanged(array $data, AmvSuperClass $amv): bool
    {
        return $amv->getVulnerability()->getUuid() !== $data['vulnerability'];
    }

    private function resetInstanceRisksRelation(Entity\Amv $amv): void
    {
        foreach ($amv->getInstanceRisks() as $instanceRisk) {
            $instanceRisk->setAmv(null)
                ->setSpecific(InstanceRiskSuperClass::TYPE_SPECIFIC)
                ->setUpdater($this->connectedUser->getEmail());

            $this->instanceRiskTable->saveEntity($instanceRisk);

            // TODO: remove it when double fields relation is removed.
            $this->instanceRiskTable->getDb()->getEntityManager()->refresh($instanceRisk);
            $this->instanceRiskTable->saveEntity($instanceRisk->setAnr($amv->getAnr()));
        }
    }
}
