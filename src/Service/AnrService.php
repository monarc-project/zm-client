<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Entity\InstanceRiskOpSuperClass;
use Monarc\Core\Model\Entity\Model;
use Monarc\Core\Model\Entity\OperationalRiskScaleCommentSuperClass;
use Monarc\Core\Model\Entity\OperationalRiskScaleSuperClass;
use Monarc\Core\Model\Entity\OperationalRiskScaleTypeSuperClass;
use Monarc\Core\Model\Entity\ScaleCommentSuperClass;
use Monarc\Core\Model\Entity\ScaleImpactTypeSuperClass;
use Monarc\Core\Model\Entity\ScaleSuperClass;
use Monarc\Core\Model\Entity\TranslationSuperClass;
use Monarc\Core\Model\Entity\User as CoreUser;
use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\Core\Model\Table\InstanceConsequenceTable as CoreInstanceConsequenceTable;
use Monarc\Core\Model\Table\InstanceRiskOpTable as CoreInstanceRiskOpTable;
use Monarc\Core\Model\Table\InstanceRiskTable as CoreInstanceRiskTable;
use Monarc\Core\Model\Table\ModelTable;
use Monarc\Core\Model\Table\OperationalRiskScaleTable as CoreOperationalRiskScaleTable;
use Monarc\Core\Model\Table\ScaleTable as CoreScaleTable;
use Monarc\Core\Model\Table\TranslationTable as CoreTranslationTable;
use Monarc\Core\Service\AbstractService;
use Monarc\Core\Service\ConfigService;
use Monarc\FrontOffice\Model\Entity\Amv;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\AnrObjectCategory;
use Monarc\FrontOffice\Model\Entity\Instance;
use Monarc\FrontOffice\Model\Entity\InstanceConsequence;
use Monarc\FrontOffice\Model\Entity\InstanceRisk;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOp;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOwner;
use Monarc\FrontOffice\Model\Entity\Interview;
use Monarc\FrontOffice\Model\Entity\Measure;
use Monarc\FrontOffice\Model\Entity\MeasureMeasure;
use Monarc\FrontOffice\Model\Entity\ObjectCategory;
use Monarc\FrontOffice\Model\Entity\ObjectObject;
use Monarc\FrontOffice\Model\Entity\OperationalInstanceRiskScale;
use Monarc\FrontOffice\Model\Entity\OperationalRiskScaleType;
use Monarc\FrontOffice\Model\Entity\Question;
use Monarc\FrontOffice\Model\Entity\QuestionChoice;
use Monarc\FrontOffice\Model\Entity\Recommandation;
use Monarc\FrontOffice\Model\Entity\RecommandationSet;
use Monarc\FrontOffice\Model\Entity\RecommandationHistoric;
use Monarc\FrontOffice\Model\Entity\RecommandationRisk;
use Monarc\FrontOffice\Model\Entity\RecordActor;
use Monarc\FrontOffice\Model\Entity\RecordDataCategory;
use Monarc\FrontOffice\Model\Entity\RecordInternationalTransfer;
use Monarc\FrontOffice\Model\Entity\RecordPersonalData;
use Monarc\FrontOffice\Model\Entity\RecordProcessor;
use Monarc\FrontOffice\Model\Entity\Referential;
use Monarc\FrontOffice\Model\Entity\RolfRisk;
use Monarc\FrontOffice\Model\Entity\RolfTag;
use Monarc\FrontOffice\Model\Entity\Scale;
use Monarc\FrontOffice\Model\Entity\OperationalRiskScale;
use Monarc\FrontOffice\Model\Entity\OperationalRiskScaleComment;
use Monarc\FrontOffice\Model\Entity\Translation;
use Monarc\FrontOffice\Model\Entity\ScaleComment;
use Monarc\FrontOffice\Model\Entity\ScaleImpactType;
use Monarc\FrontOffice\Model\Entity\Soa;
use Monarc\FrontOffice\Model\Entity\SoaCategory;
use Monarc\FrontOffice\Model\Entity\Theme;
use Monarc\FrontOffice\Model\Entity\User;
use Monarc\FrontOffice\Model\Entity\UserAnr;
use Monarc\FrontOffice\Model\Entity\UserRole;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\InstanceConsequenceTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskOpTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskOwnerTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskTable;
use Monarc\FrontOffice\Model\Table\InstanceTable;
use Monarc\FrontOffice\Model\Table\OperationalInstanceRiskScaleTable;
use Monarc\FrontOffice\Model\Table\OperationalRiskScaleCommentTable;
use Monarc\FrontOffice\Model\Table\OperationalRiskScaleTable;
use Monarc\FrontOffice\Model\Table\OperationalRiskScaleTypeTable;
use Monarc\FrontOffice\Model\Table\RecommandationRiskTable;
use Monarc\FrontOffice\Model\Table\ScaleCommentTable;
use Monarc\FrontOffice\Model\Table\ScaleImpactTypeTable;
use Monarc\FrontOffice\Model\Table\ScaleTable;
use Monarc\FrontOffice\Model\Table\SnapshotTable;
use Monarc\FrontOffice\Model\Table\TranslationTable;
use Monarc\FrontOffice\Model\Table\UserAnrTable;
use Monarc\FrontOffice\Model\Entity\Asset;
use Monarc\FrontOffice\Model\Entity\MonarcObject;
use Monarc\FrontOffice\Model\Entity\Threat;
use Monarc\FrontOffice\Model\Entity\Vulnerability;
use Monarc\FrontOffice\Model\Table\UserTable;
use Monarc\FrontOffice\Model\Entity\Record;
use Monarc\FrontOffice\Model\Entity\RecordRecipient;
use Monarc\FrontOffice\Stats\Service\StatsAnrService;
use Throwable;

/**
 * This class is the service that handles ANR CRUD operations, and various actions on them.
 * @package Monarc\FrontOffice\Service
 */
class AnrService extends AbstractService
{
    protected $amvTable;
    protected $anrTable;
    protected $anrObjectCategoryTable;
    protected $assetTable;
    protected $instanceTable;
    protected $instanceConsequenceTable;
    protected $instanceRiskTable;
    protected $instanceRiskOpTable;
    protected $modelTable;
    protected $measureTable;
    protected $MonarcObjectTable;
    protected $objectCategoryTable;
    protected $objectObjectTable;
    protected $rolfRiskTable;
    protected $rolfTagTable;
    protected $scaleTable;
    protected $scaleCommentTable;
    protected $scaleImpactTypeTable;
    protected $threatTable;
    protected $themeTable;
    protected $vulnerabilityTable;
    protected $questionTable;
    protected $questionChoiceTable;
    protected $soaTable;
    protected $soaCategoryTable;
    protected $referentialTable;
    protected $operationalRiskScaleTable;
    protected $operationalRiskScaleCommentTable;
    protected $translationTable;


    protected $amvCliTable;
    protected $anrCliTable;
    protected $anrObjectCategoryCliTable;
    protected $assetCliTable;
    protected $instanceCliTable;
    protected $instanceConsequenceCliTable;
    protected $instanceRiskCliTable;
    protected $instanceRiskOpCliTable;
    protected $interviewCliTable;
    protected $measureCliTable;
    protected $objectCliTable;
    protected $objectCategoryCliTable;
    protected $objectObjectCliTable;
    protected $recommandationCliTable;
    protected $recommandationHistoricCliTable;
    protected $recommandationRiskCliTable;
    protected $recommandationSetCliTable;
    protected $rolfRiskCliTable;
    protected $rolfTagCliTable;
    protected $scaleCliTable;
    protected $scaleCommentCliTable;
    protected $scaleImpactTypeCliTable;
    protected $snapshotCliTable;
    protected $threatCliTable;
    protected $themeCliTable;
    protected $userCliTable;
    protected $userAnrCliTable;
    protected $userRoleTable;
    protected $vulnerabilityCliTable;
    protected $questionCliTable;
    protected $questionChoiceCliTable;
    protected $soaCategoryCliTable;
    protected $recordCliTable;
    protected $recordActorCliTable;
    protected $recordDataCategoryCliTable;
    protected $recordPersonalDataCliTable;
    protected $recordInternationalTransferCliTable;
    protected $recordProcessorCliTable;
    protected $recordRecipientCliTable;
    protected $referentialCliTable;
    protected $measureMeasureCliTable;
    protected $operationalRiskScaleCliTable;
    protected $operationalRiskScaleTypeCliTable;
    protected $operationalRiskScaleCommentCliTable;
    protected $operationalInstanceRiskScaleCliTable;
    protected $translationCliTable;

    protected $instanceService;
    protected $recordService;
    protected $recordProcessorService;

    protected $statsAnrService;
    protected $configService;

    /** @var array */
    private $cachedData;

    /**
     * @inheritdoc
     */
    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null, $filterJoin = null)
    {
        /** @var UserTable $userCliTable */
        $userCliTable = $this->get('userCliTable');

        /** @var UserSuperClass $connectedUser */
        $connectedUser = $userCliTable->getConnectedUser();

        $isSuperAdmin = $connectedUser->hasRole(UserRole::SUPER_ADMIN_FO);

        // Retrieve connected user anrs
        $filterAnd['id'] = [];
        if (!$isSuperAdmin) {
            $anrs = $this->get('userAnrCliTable')->getEntityByFields(['user' => $connectedUser->getId()]);
            foreach ($anrs as $a) {
                $filterAnd['id'][$a->get('anr')->get('id')] = $a->get('anr')->get('id');
            }
        } else {
            $anrs = $this->anrCliTable->fetchAllObject();
            foreach ($anrs as $a) {
                $filterAnd['id'][$a->get('id')] = $a->get('id');
            }
        }

        // Filter out snapshots, as we don't want to show them unless we explicitly ask for them
        /** @var SnapshotTable $snapshotCliTable */
        $snapshotCliTable = $this->get('snapshotCliTable');
        $snapshots = $snapshotCliTable->getEntityByFields(['anr' => $filterAnd['id']]);
        foreach ($snapshots as $snapshot) {
            unset($filterAnd['id'][$snapshot->get('anr')->get('id')]);
        }

        // Retrieve ANRs information
        $anrs = $this->get('table')->fetchAllFiltered(
            array_keys($this->get('entity')->getJsonArray()),
            $page,
            $limit,
            $this->parseFrontendOrder($order),
            $this->parseFrontendFilter($filter, $this->filterColumns),
            $filterAnd
        );

        /** @var User $user */
        $user = $userCliTable->findById($connectedUser->getId());
        foreach ($anrs as &$anr) {
            //verify if this is the last current user's anr
            $anr['isCurrentAnr'] = 0;
            if ($user->getCurrentAnr() !== null && $anr['id'] === $user->getCurrentAnr()->getId()) {
                $anr['isCurrentAnr'] = 1;
            }

            $lk = current($this->get('userAnrCliTable')->getEntityByFields([
                'user' => $connectedUser->getId(),
                'anr' => $anr['id'],
            ]));
            $anr['rwd'] = (empty($lk)) ? -1 : $lk->get('rwd');
        }

        return $anrs;
    }

    /**
     * @inheritdoc
     */
    public function getFilteredCount($filter = null, $filterAnd = null)
    {
        return count($this->getList(1, 0, null, $filter, $filterAnd));
    }

    /**
     * Returns all ANRs without any filtering
     * @return array An array of ANRs
     */
    public function getAnrs()
    {
        return $this->get('anrCliTable')->fetchAll();
    }

    /**
     * @inheritdoc
     */
    public function getEntity($id)
    {
        $anr = $this->get('table')->get($id);

        // Retrieve snapshot
        /** @var SnapshotTable $snapshotCliTable */
        $snapshotCliTable = $this->get('snapshotCliTable');
        $anrSnapshot = current($snapshotCliTable->getEntityByFields(['anr' => $id]));

        $anr['isSnapshot'] = 0;
        $anr['snapshotParent'] = null;
        if (!empty($anrSnapshot)) {
            // This is a snapshot, tag it as so
            $anr['isSnapshot'] = 1;
            $anr['rwd'] = 0;
            $anr['snapshotParent'] = $anrSnapshot->get('anrReference')->get('id');
        } else {
            /** @var UserTable $userCliTable */
            $userCliTable = $this->get('userCliTable');

            /** @var CoreUser $connectedUser */
            $connectedUser = $userCliTable->getConnectedUser();

            $lk = current($this->get('userAnrCliTable')->getEntityByFields([
                'user' => $connectedUser->getId(),
                'anr' => $anr['id'],
            ]));
            if (empty($lk)) {
                throw new Exception('Restricted ANR', 412);
            } else {
                $anr['rwd'] = $lk->get('rwd');
            }
        }

        $this->setUserCurrentAnr($id);

        return $anr;
    }

    /**
     * Creates a new ANR from a model which is located inside the common database.
     * @param array $data Data coming from the API
     * @return Anr The newly created ANR id
     * @throws Exception If the source model is not found
     */
    public function createFromModelToClient($data): Anr
    {
        //retrieve model information
        /** @var ModelTable $modelTable */
        $modelTable = $this->get('modelTable');
        $model = $modelTable->getEntity($data['model']);
        unset($data['model']);

        if ($model->get('status') != \Monarc\Core\Model\Entity\AbstractEntity::STATUS_ACTIVE) { // disabled or deleted
            throw new Exception('Model not found', 412);
        }

        return $this->duplicateAnr($model->anr, MonarcObject::SOURCE_COMMON, $model, $data);
    }

    /**
     * Add or remove referentials to/from an existing ANR.
     * @param array $data Data coming from the API
     * @return int
     */
    public function updateReferentials($data)
    {
        // TODO: the goal is to remove such cases. Temporary unlimited.
        // This may take a lot of time on huge referential.
        ini_set('max_execution_time', 0);

        $anrTable = $this->get('anrCliTable');
        $anr = $anrTable->getEntity($data['id']);
        $uuidArray = array_map(
            function ($referential) {
                return $referential['uuid'];
            },
            $data['referentials']
        );

        // search for referentials to unlink from the anr
        foreach ($anr->getReferentials() as $referential) {
            if (!in_array($referential->getUuid(), $uuidArray, true)) {
                $this->get('referentialCliTable')->delete([
                    'anr' => $anr->id,
                    'uuid' => $referential->getUuid()
                ]);
            }
        }

        // link new referentials to an ANR
        foreach ($uuidArray as $uuid) {
            // check if referential already linked to the anr
            $referentials = $this->get('referentialCliTable')->getEntityByFields([
                'anr' => $anr->id,
                'uuid' => $uuid
            ]);
            if (! empty($referentials)) {
                // if referential already linked to the anr, go to next iteration
                continue;
            }

            $referential = $this->get('referentialTable')->getEntity($uuid);
            $measures = $referential->getMeasures();
            $referential->setMeasures(null);

            // duplicate the referential
            $newReferential = new Referential($referential);
            $newReferential->setAnr($anr);

            // duplicate categories
            $categoryNewIds = [];
            $category = $this->get('soaCategoryTable')->getEntityByFields(['referential' => $referential->getUuid()]);
            foreach ($category as $cat) {
                $newCategory = new SoaCategory($cat);
                $newCategory->set('id', null);
                $newCategory->setAnr($anr);
                $newCategory->setMeasures(null);
                $newCategory->setReferential($newReferential);
                $categoryNewIds[$cat->id] = $newCategory;
            }
            $newReferential->setCategories($categoryNewIds);

            // duplicate the measures
            $measuresNewIds = [];
            foreach ($measures as $measure) {
                // duplicate and link the measures to the current referential
                $newMeasure = (new Measure($measure))
                    ->setAnr($anr)
                    ->setReferential($newReferential)
                    ->setCategory($categoryNewIds[$measure->category->id]);
                foreach ($newMeasure->getMeasuresLinked() as $measureLinked) {
                    $data = [];
                    if (!count($this->get('measureMeasureCliTable')->getEntityByFields([
                        'anr' => $anr->id,
                        'father' => $measure->getUuid(),
                        'child' => $measureLinked->getUuid()
                    ]))) {
                        $data['father'] = $newMeasure->getUuid();
                        $data['child'] = $measureLinked->getUuid();
                        $newMeasureMeasure = new MeasureMeasure($data);
                        $newMeasureMeasure->setAnr($anr);
                        $this->get('measureMeasureCliTable')->save($newMeasureMeasure, false);
                    }

                    if (!count($this->get('measureMeasureCliTable')->getEntityByFields([
                        'anr' => $anr->id,
                        'father' => $measureLinked->getUuid(),
                        'child' => $newMeasure->getUuid()
                    ]))) {
                        $data['father'] = $measureLinked->getUuid();
                        $data['child'] = $newMeasure->getUuid();
                        $newMeasureMeasure = new MeasureMeasure($data);
                        $newMeasureMeasure->setAnr($anr);
                        $this->get('measureMeasureCliTable')->save($newMeasureMeasure, false);
                    }
                }
                $newMeasure->setMeasuresLinked(new ArrayCollection());
                $amvs = $newMeasure->getAmvs();
                $rolfRisks = $newMeasure->getRolfRisks();
                $newMeasure->amvs = new ArrayCollection;
                $newMeasure->rolfRisks = new ArrayCollection;
                // update the amv with the new measures from the current referential
                foreach ($amvs as $amvCommon) {
                    // match the AMVs from common with AMVS from cli
                    $amvCli = $this->get('amvCliTable')
                        ->getEntityByFields([
                            'asset' => ['uuid' => $amvCommon->getAsset()->getUuid(), 'anr' => $anr->getId()],
                            'threat' => ['uuid' => $amvCommon->getThreat()->getUuid(), 'anr' => $anr->getId()],
                            'vulnerability' => [
                                'uuid' => $amvCommon->getVulnerability()->getUuid(),
                                'anr' => $anr->id
                            ]
                        ]);
                    if (count($amvCli)) {
                        $newMeasure->addAmv($amvCli[0]);
                    }
                }
                foreach ($rolfRisks as $rolfRisk_common) {
                    // match the risks from common with risks from cli
                    $risk_cli = $this->get('rolfRiskCliTable')->getEntityByFields([
                        'anr' => $anr->id,
                        'label' . $this->getLanguage() => $rolfRisk_common->getLabel($this->getLanguage()),
                        'code' => $rolfRisk_common->getCode()
                    ]);
                    if (count($risk_cli)) {
                        //$risk_cli = $risk_cli[0];
                        $newMeasure->addOpRisk($risk_cli[0]);
                    }
                }
                $measuresNewIds[] = $newMeasure;

                $newSoa = new Soa();
                $newSoa->set('id', null);
                $newSoa->setAnr($anr);
                $newSoa->setMeasure($newMeasure);
                $this->get('soaTable')->save($newSoa, false);
            }
            $newReferential->setMeasures($measuresNewIds);

            $this->get('referentialCliTable')->save($newReferential);
        }

        return $anr->id;
    }

    /**
     * Duplicates either an existing ANR from the client, or an ANR model from the common database.
     * @param int|AnrSuperClass $anr The ANR to clone, either its ID or the object
     * @param string $source The source, either MonarcObject::SOURCE_CLIENT or MonarcObject::SOURCE_COMMON
     * @param Model|null $model The source common model, or null if none
     * @return Anr The newly created ANR
     * @throws Exception
     */
    public function duplicateAnr(
        $anr,
        $source = MonarcObject::SOURCE_CLIENT,
        $model = null,
        $data = [],
        $isSnapshot = false,
        $isSnapshotCloning = false
    ): Anr {
        // TODO: the goal is to remove such cases. Temporary unlimited.
        // This may take a lot of time on huge ANRs.
        ini_set('max_execution_time', 0);

        if (is_int($anr)) {
            /** @var AnrTable $anrTable */
            $anrTable = $source === MonarcObject::SOURCE_COMMON ? $this->get('anrTable') : $this->get('anrCliTable');
            $anr = $anrTable->getEntity($anr);
        }

        if (!$anr instanceof AnrSuperClass) {
            throw new Exception('Anr missing', 412);
        }
        if (empty($model)) {
            $idModel = $anr->get('model');
        } else {
            $idModel = $model->get('id');
        }

        if (!empty($idModel)) {
            if (!$this->verifyLanguage($idModel)) {
                throw new Exception('Error during analysis creation', 412);
            }
        } // if empty($idModel), maybe created from migration tool & model don't match with existing datas

        /** @var UserTable $userCliTable */
        $userCliTable = $this->get('userCliTable');

        /** @var CoreUser $connectedUser */
        $connectedUser = $userCliTable->getConnectedUser();

        if ($source == MonarcObject::SOURCE_CLIENT && !$isSnapshotCloning) {
            /** @var UserAnrTable $userAnrCliTable */
            $userAnrCliTable = $this->get('userAnrCliTable');
            $userAnr = $userAnrCliTable->getEntityByFields(['anr' => $anr->id, 'user' => $connectedUser->getId()]);

            if (count($userAnr) == 0) {
                throw new Exception('You are not authorized to duplicate this analysis', 412);
            }
        }

        try {
            // duplicate anr
            $newAnr = new Anr($anr);
            $newAnr->setId(null);
            $newAnr->generateAndSetUuid();
            $newAnr->setObjects(null);
            $newAnr->exchangeArray($data);
            $newAnr->set('model', $idModel);
            $newAnr->setReferentials(null);
            $newAnr->setCreator($connectedUser->getFirstname() . ' ' . $connectedUser->getLastname());
            if (!empty($model) && is_object($model)) {
                $newAnr->set('cacheModelShowRolfBrut', $model->showRolfBrut);
                $newAnr->set('cacheModelIsScalesUpdatable', $model->isScalesUpdatable);
            }
            if ($isSnapshot) { // if snapshot, add the prefix "[SNAP]"
                for ($i = 1; $i <= 4; $i++) {
                    $lab = trim($newAnr->get('label' . $i));
                    if (!empty($lab)) {
                        $newAnr->set('label' . $i, '[SNAP] ' . $lab);
                    }
                }
            }

            /** @var AnrTable $anrCliTable */
            $anrCliTable = $this->get('anrCliTable');
            $anrCliTable->saveEntity($newAnr);

            if (!$isSnapshot && !$isSnapshotCloning) { // useless if the user is doing a snapshot or is restoring a snapshot (SnapshotService::restore)
                //add user to anr
                $user = $userCliTable->findById($connectedUser->getId());
                $userAnr = (new UserAnr())
                    ->setUser($user)
                    ->setAnr($newAnr)
                    ->setRwd(1)
                    ->setCreator($connectedUser->getFirstname() . ' ' . $connectedUser->getLastname());
                /** @var UserAnrTable $userAnrCliTable */
                $userAnrCliTable = $this->get('userAnrCliTable');
                $userAnrCliTable->saveEntity($userAnr, false);
            }

            // duplicate themes
            $themesNewIds = [];
            $themes = $source === MonarcObject::SOURCE_COMMON
                ? $this->get('themeTable')->fetchAllObject()
                : $this->get('themeCliTable')->getEntityByFields(['anr' => $anr->id]);
            foreach ($themes as $theme) {
                $newTheme = new Theme($theme);
                $newTheme->set('id', null);
                $newTheme->setAnr($newAnr);
                $this->get('themeCliTable')->save($newTheme, false);
                $themesNewIds[$theme->id] = $newTheme;
            }

            // duplicate assets
            $assetsNewIds = [];
            if ($source == MonarcObject::SOURCE_COMMON) {
                $assets1 = [];
                if (!$model->isRegulator) {
                    $assets1 = $this->get('assetTable')->getEntityByFields(['mode' => Asset::MODE_GENERIC]);
                }
                $assets2 = [];
                if (!$model->isGeneric) {
                    $assets2 = $this->get('assetTable')->getEntityByFields(['mode' => Asset::MODE_SPECIFIC]);
                }
                $assets = $assets1 + $assets2;
            } else {
                $assets = $this->get('assetCliTable')->getEntityByFields(['anr' => $anr->id]);
            }
            foreach ($assets as $asset) {
                $newAsset = new Asset($asset);
                $newAsset->setAnr($newAnr);
                $this->get('assetCliTable')->save($newAsset, false);
                $assetsNewIds[$asset->getUuid()] = $newAsset;
            }

            // duplicate threats
            $threatsNewIds = [];
            if ($source === MonarcObject::SOURCE_COMMON) {
                $threats = [];
                if (!$model->isRegulator) {
                    $threats = $this->get('threatTable')->getEntityByFields(['mode' => Threat::MODE_GENERIC]);
                }
                $threats2 = [];
                if (!$model->isGeneric) {
                    $threats2 = $this->get('threatTable')->getEntityByFields(['mode' => Threat::MODE_SPECIFIC]);
                    foreach ($threats2 as $t) {
                        $threats[] = $t;
                    }
                    unset($threats2);
                }
            } else {
                $threats = $this->get('threatCliTable')->getEntityByFields(['anr' => $anr->id]);
            }
            foreach ($threats as $threat) {
                $newThreat = new Threat($threat);
                $newThreat->setAnr($newAnr);
                if ($threat->theme) {
                    $newThreat->setTheme($themesNewIds[$threat->theme->id]);
                }
                $this->get('threatCliTable')->save($newThreat, false);
                $threatsNewIds[$threat->getUuid()] = $newThreat;
            }

            // duplicate vulnerabilities
            $vulnerabilitiesNewIds = [];
            if ($source === MonarcObject::SOURCE_COMMON) {
                $vulnerabilities1 = [];
                if (!$model->isRegulator) {
                    $vulnerabilities1 = $this->get('vulnerabilityTable')->getEntityByFields(['mode' => Vulnerability::MODE_GENERIC]);
                }
                $vulnerabilities2 = [];
                if (!$model->isGeneric) {
                    $vulnerabilities2 = $this->get('vulnerabilityTable')->getEntityByFields(['mode' => Vulnerability::MODE_SPECIFIC]);
                }
                $vulnerabilities = $vulnerabilities1 + $vulnerabilities2;
            } else {
                $vulnerabilities = $this->get('vulnerabilityCliTable')->getEntityByFields(['anr' => $anr->id]);
            }
            foreach ($vulnerabilities as $vulnerability) {
                $newVulnerability = new Vulnerability($vulnerability);
                $newVulnerability->setAnr($newAnr);
                $this->get('vulnerabilityCliTable')->save($newVulnerability, false);
                $vulnerabilitiesNewIds[$vulnerability->getUuid()] = $newVulnerability;
            }

            // duplicate categories, referentials and measures
            $measuresNewIds = [];
            if ($source == MonarcObject::SOURCE_COMMON) {
                foreach ($data['referentials'] as $referential_array) {
                    $referential = $this->get('referentialTable')->getEntity($referential_array['uuid']);
                    $measures = $referential->getMeasures();
                    $referential->setMeasures(null);

                    // duplicate the referential
                    $newReferential = new Referential($referential);
                    $newReferential->setAnr($newAnr);

                    // duplicate categories
                    $categoryNewIds = [];
                    $category = $this->get('soaCategoryTable')->getEntityByFields(['referential' => $referential->getUuid()]);
                    foreach ($category as $cat) {
                        $newCategory = new SoaCategory($cat);
                        $newCategory->set('id', null);
                        $newCategory->setAnr($newAnr);
                        $newCategory->setMeasures(null);
                        $newCategory->setReferential($newReferential);
                        $categoryNewIds[$cat->id] = $newCategory;
                    }
                    $newReferential->setCategories($categoryNewIds);

                    foreach ($measures as $measure) {
                        // duplicate and link the measures to the current referential
                        $newMeasure = (new Measure($measure))
                            ->setAnr($newAnr)
                            ->setAmvs(new ArrayCollection())
                            ->setRolfRisks(new ArrayCollection())
                            ->setMeasuresLinked(new ArrayCollection())
                            ->setReferential($newReferential)
                            ->setCategory($categoryNewIds[$measure->category->id]);
                        foreach ($measure->getMeasuresLinked() as $measureLinked) {
                            $newMeasureMeasure = (new MeasureMeasure([
                                'father' => $measure->getUuid(),
                                'child' => $measureLinked->getUuid(),
                            ]))->setAnr($newAnr);
                            $this->get('measureMeasureCliTable')->save($newMeasureMeasure, false);
                        }
                        $measuresNewIds[$measure->getUuid()] = $newMeasure;
                    }
                    //$newReferential->setMeasures(null);
                    $this->get('referentialCliTable')->save($newReferential, false);
                    $this->get('referentialCliTable')->getDb()->flush();
                }
            } else { // copy from an existing anr (or a snapshot)
                $referentialTable = $this->get('referentialCliTable');
                $referentials = $referentialTable->getEntityByFields(['anr' => $anr->id]);
                foreach ($referentials as $referential) {
                    // duplicate referentials
                    $measures = $referential->getMeasures();
                    $categories = $referential->getCategories();
                    $referential->setMeasures(null);
                    $referential->setCategories(null);
                    $newReferential = new Referential($referential);
                    $newReferential->setAnr($newAnr);

                    $categoryNewIds = [];
                    foreach ($categories as $cat) {
                        $newCategory = new SoaCategory($cat);
                        $newCategory->set('id', null);
                        $newCategory->setAnr($newAnr);
                        $newCategory->setMeasures(null);
                        $newCategory->setReferential($newReferential);
                        $categoryNewIds[$cat->id] = $newCategory;
                    }
                    $newReferential->setCategories($categoryNewIds);

                    $newMeasures = [];
                    foreach ($measures as $measure) {
                        // duplicate and link the measures to the current referential
                        $newMeasure = (new Measure($measure))
                            ->setAnr($newAnr)
                            ->setReferential($newReferential)
                            ->setCategory($categoryNewIds[$measure->category->id])
                            ->setMeasuresLinked(new ArrayCollection())
                            ->setAmvs(new ArrayCollection())
                            ->setRolfRisks(new ArrayCollection());
                        $measuresNewIds[$measure->getUuid()] = $newMeasure;
                        $newMeasures[] = $newMeasure;
                    }
                    $newReferential->setMeasures($newMeasures);

                    $referentialTable->save($newReferential, false);
                    $referentialTable->getDb()->flush();
                }

                // duplicate measures-measures
                $measuresmeasures = $this->get('measureMeasureCliTable')->getEntityByFields(['anr' => $anr->id]);
                foreach ($measuresmeasures as $mm) {
                    $newMeasureMeasure = new MeasureMeasure($mm);
                    $newMeasureMeasure->setAnr($newAnr);
                    $this->get('measureMeasureCliTable')->save($newMeasureMeasure, false);
                }
            }

            // duplicate soas
            if ($source == MonarcObject::SOURCE_COMMON) {
                foreach ($measuresNewIds as $key => $value) {
                    $newSoa = new Soa();
                    $newSoa->set('id', null);
                    $newSoa->setAnr($newAnr);
                    $newSoa->setMeasure($value);
                    $this->get('soaTable')->save($newSoa, false);
                }
            } else {
                $soas = $this->get('soaTable')->getEntityByFields(['anr' => $anr->id]);
                foreach ($soas as $soa) {
                    $newSoa = new Soa($soa);
                    $newSoa->set('id', null);
                    $newSoa->setAnr($newAnr);
                    $newSoa->setMeasure($measuresNewIds[$soa->measure->getUuid()]);
                    $this->get('soaTable')->save($newSoa, false);
                }
            }

            // duplicate amvs
            $amvsNewIds = [];
            $amvs = $source === MonarcObject::SOURCE_COMMON
                ? $this->get('amvTable')->fetchAllObject()
                : $this->get('amvCliTable')->getEntityByFields(['anr' => $anr->id]);
            foreach ($amvs as $key => $amv) {
                if (!isset(
                    $assetsNewIds[$amv->asset->getUuid()],
                    $threatsNewIds[$amv->threat->getUuid()],
                    $vulnerabilitiesNewIds[$amv->vulnerability->getUuid()]
                )) {
                    unset($amvs[$key]);
                }
            }
            foreach ($amvs as $amv) {
                $newAmv = new Amv($amv);
                $newAmv->setAnr($newAnr);
                $newAmv->setAsset($assetsNewIds[$amv->getAsset()->getUuid()]);
                $newAmv->setThreat($threatsNewIds[$amv->getThreat()->getUuid()]);
                $newAmv->setVulnerability($vulnerabilitiesNewIds[$amv->getVulnerability()->getUuid()]);
                $newAmv->setMeasures(null);
                foreach ($amv->getMeasures() as $measure) {
                    if (isset($measuresNewIds[$measure->getUuid()])) {
                        $measuresNewIds[$measure->getUuid()]->addAmv($newAmv);
                    }
                }
                $this->get('amvCliTable')->save($newAmv, false);
                $amvsNewIds[$amv->getUuid()] = $newAmv;
            }

            // duplicate rolf tags
            $rolfTagsNewIds = [];
            $rolfTags = ($source == MonarcObject::SOURCE_COMMON) ? $this->get('rolfTagTable')->fetchAllObject() : $this->get('rolfTagCliTable')->getEntityByFields(['anr' => $anr->id]);
            foreach ($rolfTags as $rolfTag) {
                $newRolfTag = new RolfTag($rolfTag);
                $newRolfTag->set('id', null);
                $newRolfTag->setAnr($newAnr);
                $newRolfTag->set('risks', []);
                $this->get('rolfTagCliTable')->save($newRolfTag,false);
                $rolfTagsNewIds[$rolfTag->id] = $newRolfTag;
            }

            // duplicate rolf risk
            $rolfRisksNewIds = [];
            $rolfRisks = ($source == MonarcObject::SOURCE_COMMON) ? $this->get('rolfRiskTable')->fetchAllObject() : $this->get('rolfRiskCliTable')->getEntityByFields(['anr' => $anr->id]);
            foreach ($rolfRisks as $rolfRisk) {
                $newRolfRisk = new RolfRisk($rolfRisk);
                $newRolfRisk->set('id', null);
                $newRolfRisk->setAnr($newAnr);
                $newRolfRisk->measures = new ArrayCollection;
                // Link tags
                $indexTagRisk = 0;
                $listTagrisk = [];
                foreach ($rolfRisk->tags as $key => $tag) {
                    if (!empty($rolfTagsNewIds[$tag->id])) {
                        $listTagrisk[$indexTagRisk]=$rolfTagsNewIds[$tag->id];
                        $indexTagRisk++;
                    }
                }
                $newRolfRisk->setTags($listTagrisk);
                //link the measures

                foreach ($rolfRisk->measures as $m) {
                    try{
                        $measure = $this->get('measureCliTable')->getEntity([
                            'anr' => $newAnr->getId(),
                            'uuid' => $m->getUuid()
                        ]);
                        $measure->addOpRisk($newRolfRisk);
                    } catch (Exception $e) { } //needed if the measures don't exist in the client ANR
                }
                $this->get('rolfRiskCliTable')->save($newRolfRisk,false);
                $rolfRisksNewIds[$rolfRisk->id] = $newRolfRisk;
            }

            // duplicate objects categories
            $objects = $source === MonarcObject::SOURCE_COMMON
                ? $this->get('MonarcObjectTable')->fetchAllObject()
                : $this->get('objectCliTable')->getEntityByFields(['anr' => $anr->id]);
            foreach ($objects as $key => $object) {
                $existInAnr = false;
                foreach ($object->anrs as $anrObject) {
                    if ($anrObject->id == $anr->id) {
                        $existInAnr = true;
                    }
                }
                if (!$existInAnr) {
                    unset($objects[$key]);
                }
            }
            $categoriesIds = [];
            foreach ($objects as $object) {
                if ($object->category) {
                    $categoriesIds[] = $object->category->id;
                    $this->getParentsCategoryIds($object->category, $categoriesIds);
                }
            }

            $objectsCategoriesNewIds = [];
            $objectsCategories = $source === MonarcObject::SOURCE_COMMON
                ? $this->get('objectCategoryTable')->fetchAllObject()
                : $this->get('objectCategoryCliTable')->getEntityByFields(['anr' => $anr->id], ['parent' => 'ASC']);
            foreach ($objectsCategories as $objectCategory) {
                if (in_array($objectCategory->id, $categoriesIds)) {
                    $newObjectCategory = new ObjectCategory($objectCategory);
                    $newObjectCategory->set('id', null);
                    $newObjectCategory->setAnr($newAnr);
                    if ($objectCategory->parent) {
                        $newObjectCategory->setParent($objectsCategoriesNewIds[$objectCategory->parent->id]);
                    }
                    if ($objectCategory->root) {
                        $newObjectCategory->setRoot($objectsCategoriesNewIds[$objectCategory->root->id]);
                    }
                    $this->get('objectCategoryCliTable')->save($newObjectCategory,false);
                    $objectsCategoriesNewIds[$objectCategory->id] = $newObjectCategory;
                }
            }

            // duplicate objects
            $objectsNewIds = [];
            $objectsRootCategories = [];
            foreach ($objects as $object) {
                $newObject = new MonarcObject($object);
                $newObject->setAnr($newAnr);
                $newObject->setAnrs(null);
                $newObject->addAnr($newAnr);
                if (!is_null($object->category)) {
                    $newObject->setCategory($objectsCategoriesNewIds[$object->category->id]);
                }
                $newObject->setAsset($assetsNewIds[$object->asset->getUuid()]);
                if ($object->rolfTag) {
                    $newObject->setRolfTag($rolfTagsNewIds[$object->rolfTag->id]);
                }
                $this->get('objectCliTable')->save($newObject, false);
                $objectsNewIds[$object->getUuid()] = $newObject;

                //root category
                if (!is_null($object->category)) {
                    $objectCategoryTable = $source === MonarcObject::SOURCE_COMMON
                        ? $this->get('objectCategoryTable')
                        : $this->get('objectCategoryCliTable');
                    $objectCategory = $objectCategoryTable->getEntity($object->category->id);
                    $objectsRootCategories[] = ($objectCategory->root) ? $objectCategory->root->id : $objectCategory->id;
                }
            }

            $objectsRootCategories = array_unique($objectsRootCategories);

            // duplicate anrs objects categories
            $anrObjectCategoryTable = ($source == MonarcObject::SOURCE_COMMON) ? $this->get('anrObjectCategoryTable') : $this->get('anrObjectCategoryCliTable');
            $anrObjectsCategories = $anrObjectCategoryTable->getEntityByFields(['anr' => $anr->id]);
            foreach ($anrObjectsCategories as $key => $anrObjectCategory) {
                if (!in_array($anrObjectCategory->category->id, $objectsRootCategories)) {
                    unset($anrObjectsCategories[$key]);
                }
            }
            foreach ($anrObjectsCategories as $key => $anrObjectCategory) {
                $newAnrObjectCategory = new AnrObjectCategory($anrObjectCategory);
                $newAnrObjectCategory->set('id', null);
                $newAnrObjectCategory->setAnr($newAnr);
                $newAnrObjectCategory->setCategory($objectsCategoriesNewIds[$anrObjectCategory->category->id]);
                $this->get('anrObjectCategoryCliTable')->save($newAnrObjectCategory, false);
            }

            // duplicate objects objects
            $objectsObjects = $source === MonarcObject::SOURCE_COMMON
                ? $this->get('objectObjectTable')->fetchAllObject()
                : $this->get('objectObjectCliTable')->getEntityByFields(['anr' => $anr->id]);
            foreach ($objectsObjects as $key => $objectObject) {
                if (!($objectObject->getFather() && isset($objectsNewIds[$objectObject->getFather()->getUuid()])
                    && $objectObject->getChild() && isset($objectsNewIds[$objectObject->getChild()->getUuid()]))
                ) {
                    unset($objectsObjects[$key]);
                }
            }
            foreach ($objectsObjects as $objectObject) {
                $newObjectObject = new ObjectObject($objectObject);
                $newObjectObject->setAnr($newAnr);
                $newObjectObject->setFather($objectsNewIds[$objectObject->getFather()->getUuid()]);
                $newObjectObject->setChild($objectsNewIds[$objectObject->getChild()->getUuid()]);
                $this->get('objectObjectCliTable')->save($newObjectObject, false);
            }

            // duplicate instances
            $instancesNewIds = [];
            /** @var InstanceTable $instanceTable */
            $instanceTable = $source === MonarcObject::SOURCE_COMMON
                ? $this->get('instanceTable')
                : $this->get('instanceCliTable');
            $instances = $instanceTable->getEntityByFields(['anr' => $anr->getId()], ['parent' => 'ASC']);
            foreach ($instances as $instance) {
                $newInstance = new Instance($instance);
                $newInstance->set('id', null);
                $newInstance->setAnr($newAnr);
                $newInstance->setAsset($assetsNewIds[$instance->getAsset()->getUuid()]);
                $newInstance->setObject($objectsNewIds[$instance->getObject()->getUuid()]);
                $newInstance->setRoot(null);
                $newInstance->setParent(null);
                $newInstance->resetInstanceConsequences();
                $this->get('instanceCliTable')->save($newInstance, false);
                $instancesNewIds[$instance->id] = $newInstance;
            }
            foreach ($instances as $instance) {
                if ($instance->getRoot() || $instance->getParent()) {
                    $newInstance = $instancesNewIds[$instance->getId()];
                    if ($instance->getRoot()) {
                        $newInstance->setRoot($instancesNewIds[$instance->getRoot()->getId()]);
                    }
                    if ($instance->getParent()) {
                        $newInstance->setParent($instancesNewIds[$instance->getParent()->getId()]);
                    }
                    $this->get('instanceCliTable')->save($newInstance, false);
                }
            }

            $scalesImpactTypesOldIdsToNewObjectsMap = $this->createScalesFromSourceAnr(
                $newAnr,
                $anr,
                $source,
                $connectedUser
            );

            $operationalScaleTypesOldIdsToNewObjectsMap = $this->createOperationalRiskScalesFromSourceAnr(
                $newAnr,
                $anr,
                $source,
                $connectedUser
            );

            // duplicate instances risks
            /** @var InstanceRiskTable $instanceRiskCliTable */
            $instanceRiskCliTable = $this->get('instanceRiskCliTable');
            /** @var InstanceRiskTable|CoreInstanceRiskTable $instanceRiskTable */
            $instanceRiskTable = $source === MonarcObject::SOURCE_COMMON
                ? $this->get('instanceRiskTable')
                : $instanceRiskCliTable;
            $instancesRisks = $instanceRiskTable->findByAnr($anr);
            $instancesRisksNewIds = [];
            foreach ($instancesRisks as $instanceRisk) {
                $newInstanceRisk = new InstanceRisk($instanceRisk);
                $newInstanceRisk->set('id', null);
                $newInstanceRisk->setAnr($newAnr);
                if ($instanceRisk->getAmv()) {
                    $newInstanceRisk->setAmv($amvsNewIds[$instanceRisk->getAmv()->getUuid()]);
                }
                if ($instanceRisk->getAsset()) {
                    $newInstanceRisk->setAsset($assetsNewIds[$instanceRisk->getAsset()->getUuid()]);
                }
                if ($instanceRisk->getThreat()) {
                    $newInstanceRisk->setThreat($threatsNewIds[$instanceRisk->getThreat()->getUuid()]);
                }
                if ($instanceRisk->getVulnerability()) {
                    $newInstanceRisk->setVulnerability($vulnerabilitiesNewIds[$instanceRisk->getVulnerability()->getUuid()]);
                }
                if ($instanceRisk->getInstance()) {
                    $newInstanceRisk->setInstance($instancesNewIds[$instanceRisk->getInstance()->getId()]);
                }
                if ($instanceRisk->getInstanceRiskOwner()) {
                    $instanceRiskOwner = $this->getOrCreateInstanceRiskOwner(
                        $anr,
                        $instanceRisk->getInstanceRiskOwner()->getName(),
                        $connectedUser
                    );
                    $newInstanceRisk->setInstanceRiskOwner($instanceRiskOwner);
                }
                $newInstanceRisk->setContext($instanceRisk->getContext());

                $instanceRiskCliTable->saveEntity($newInstanceRisk, false);
                $instancesRisksNewIds[$instanceRisk->getId()] = $newInstanceRisk;
            }

            // duplicate instances risks op
            /** @var InstanceRiskOpTable $instanceRiskOpCliTable */
            $instanceRiskOpCliTable = $this->get('instanceRiskOpCliTable');
            /** @var CoreInstanceRiskOpTable|InstanceRiskOpTable $instanceRiskOpTable */
            $instanceRiskOpTable = $source === MonarcObject::SOURCE_COMMON
                ? $this->get('instanceRiskOpTable')
                : $instanceRiskOpCliTable;
            $instancesRisksOp = $instanceRiskOpTable->findByAnr($anr);
            $instancesRisksOpNewIds = [];
            foreach ($instancesRisksOp as $instanceRiskOp) {
                $newInstanceRiskOp = (new InstanceRiskOp())
                    ->setAnr($newAnr)
                    ->setInstance($instancesNewIds[$instanceRiskOp->getInstance()->getId()])
                    ->setObject($objectsNewIds[$instanceRiskOp->getObject()->getUuid()])
                    ->setKindOfMeasure($instanceRiskOp->getKindOfMeasure())
                    ->setBrutProb($instanceRiskOp->getBrutProb())
                    ->setNetProb($instanceRiskOp->getNetProb())
                    ->setTargetedProb($instanceRiskOp->getTargetedProb())
                    ->setCacheBrutRisk($instanceRiskOp->getCacheBrutRisk())
                    ->setCacheNetRisk($instanceRiskOp->getCacheNetRisk())
                    ->setCacheTargetedRisk($instanceRiskOp->getCacheTargetedRisk())
                    ->setMitigation($instanceRiskOp->getMitigation())
                    ->setSpecific($instanceRiskOp->getSpecific())
                    ->setRiskCacheCode($instanceRiskOp->getRiskCacheCode())
                    ->setRiskCacheLabels([
                        'riskCacheLabel1' => $instanceRiskOp->getRiskCacheLabel(1),
                        'riskCacheLabel2' => $instanceRiskOp->getRiskCacheLabel(2),
                        'riskCacheLabel3' => $instanceRiskOp->getRiskCacheLabel(3),
                        'riskCacheLabel4' => $instanceRiskOp->getRiskCacheLabel(4),
                    ])
                    ->setRiskCacheDescriptions([
                        'riskCacheDescription1' => $instanceRiskOp->getRiskCacheDescription(1),
                        'riskCacheDescription2' => $instanceRiskOp->getRiskCacheDescription(2),
                        'riskCacheDescription3' => $instanceRiskOp->getRiskCacheDescription(3),
                        'riskCacheDescription4' => $instanceRiskOp->getRiskCacheDescription(4),
                    ])
                    ->setComment($instanceRiskOp->getComment())
                    ->setContext($instanceRiskOp->getContext())
                    ->setCreator($connectedUser->getEmail())
                    ->resetUpdatedAtValue();
                if ($instanceRiskOp->getRolfRisk()) {
                    $newInstanceRiskOp->setRolfRisk($rolfRisksNewIds[$instanceRiskOp->getRolfRisk()->getId()]);
                }

                if ($instanceRiskOp->getInstanceRiskOwner()) {
                    $instanceRiskOwner = $this->getOrCreateInstanceRiskOwner(
                        $anr,
                        $instanceRiskOp->getInstanceRiskOwner()->getName(),
                        $connectedUser
                    );
                    $newInstanceRiskOp->setInstanceRiskOwner($instanceRiskOwner);
                }

                $instanceRiskOpCliTable->saveEntity($newInstanceRiskOp, false);

                $this->createOperationalInstanceRiskScalesFromSource(
                    $instanceRiskOp,
                    $operationalScaleTypesOldIdsToNewObjectsMap,
                    $newAnr,
                    $newInstanceRiskOp,
                    $connectedUser
                );

                $instancesRisksOpNewIds[$instanceRiskOp->getId()] = $newInstanceRiskOp;
            }

            //duplicate instances consequences
            /** @var InstanceConsequenceTable|CoreInstanceConsequenceTable $instanceConsequenceTable */
            $instanceConsequenceTable = $source === MonarcObject::SOURCE_COMMON
                ? $this->get('instanceConsequenceTable')
                : $this->get('instanceConsequenceCliTable');
            $instancesConsequences = $instanceConsequenceTable->findByAnr($anr);
            foreach ($instancesConsequences as $instanceConsequence) {
                $newInstanceConsequence = new InstanceConsequence($instanceConsequence);
                $newInstanceConsequence->set('id', null);
                $newInstanceConsequence->setAnr($newAnr);
                $newInstanceConsequence->setInstance($instancesNewIds[$instanceConsequence->getInstance()->getId()]);
                $newInstanceConsequence->setObject($objectsNewIds[$instanceConsequence->getObject()->getUuid()]);
                $newInstanceConsequence->setScaleImpactType(
                    $scalesImpactTypesOldIdsToNewObjectsMap[$instanceConsequence->getScaleImpactType()->getId()]
                );
                $this->get('instanceConsequenceCliTable')->save($newInstanceConsequence, false);
            }

            // duplicate questions & choices
            $questions = $source === MonarcObject::SOURCE_COMMON
                ? $this->get('questionTable')->fetchAllObject()
                : $this->get('questionCliTable')->getEntityByFields(['anr' => $anr->id]);
            $questionsNewIds = [];
            foreach ($questions as $q) {
                $newQuestion = new Question($q);
                $newQuestion->set('id', null);
                $newQuestion->set('anr', $newAnr);
                $this->get('questionCliTable')->save($newQuestion, false);
                $questionsNewIds[$q->id] = $newQuestion;
            }
            $questionChoices = $source === MonarcObject::SOURCE_COMMON
                ? $this->get('questionChoiceTable')->fetchAllObject()
                : $this->get('questionChoiceCliTable')->getEntityByFields(['anr' => $anr->id]);
            foreach ($questionChoices as $qc) {
                $newQuestionChoice = new QuestionChoice($qc);
                $newQuestionChoice->set('id', null);
                $newQuestionChoice->set('anr', $newAnr);
                $newQuestionChoice->set('question', $questionsNewIds[$qc->get('question')->get('id')]);
                $this->get('questionChoiceCliTable')->save($newQuestionChoice, false);
            }

            //if we are duplicating an analysis do the following
            if ($source === MonarcObject::SOURCE_CLIENT) {
                //duplicate interviews
                $interviews = $this->get('interviewCliTable')->getEntityByFields(['anr' => $anr->id]);
                foreach ($interviews as $interview) {
                    $newInterview = new Interview($interview);
                    $newInterview->set('id', null);
                    $newInterview->setAnr($newAnr);
                    $this->get('interviewCliTable')->save($newInterview, false);
                }

                //duplicate record actors
                $recordActors = $this->get('recordActorCliTable')->getEntityByFields(['anr' => $anr->id]);
                $actorNewIds = [];
                foreach ($recordActors as $a) {
                    $newActor = new RecordActor($a);
                    $newActor->set('id', null);
                    $newActor->setAnr($newAnr);
                    $this->get('recordActorCliTable')->save($newActor, false);
                    $this->get('recordActorCliTable')->getDb()->flush();
                    $actorNewIds[$a->id] = $newActor;
                }

                //duplicate record data categories
                $recordDataCategoryTable = $this->get('recordDataCategoryCliTable');
                $recordDataCategories = $recordDataCategoryTable->getEntityByFields(['anr' => $anr->id]);
                $dataCategoryNewIds = [];
                foreach ($recordDataCategories as $dc) {
                    $newDataCategory = new RecordDataCategory($dc);
                    $newDataCategory->set('id', null);
                    $newDataCategory->setAnr($newAnr);
                    $recordDataCategoryTable->save($newDataCategory, true);
                    $dataCategoryNewIds[$dc->id] = $newDataCategory;
                }

                //duplicate record processors
                $recordProcessors = $this->get('recordProcessorCliTable')->getEntityByFields(['anr' => $anr->id]);
                $processorNewIds = [];
                foreach ($recordProcessors as $p) {
                    $newProcessor = new RecordProcessor($p);
                    $newProcessor->set('id', null);
                    $newProcessor->setAnr($newAnr);
                    $activities = [];
                    $newProcessor->setActivities($activities);
                    $secMeasures = [];
                    $newProcessor->setSecMeasures($secMeasures);
                    if ($p->representative != null) {
                        $newProcessor->setRepresentative($actorNewIds[$p->representative->id]);
                    }
                    if ($p->dpo != null) {
                        $newProcessor->setDpo($actorNewIds[$p->dpo->id]);
                    }
                    $this->get('recordProcessorCliTable')->save($newProcessor, false);
                    $this->get('recordProcessorCliTable')->getDb()->flush();
                    $processorNewIds[$p->id] = $newProcessor;
                }

                //duplicate record recipients
                $recordRecipients = $this->get('recordRecipientCliTable')->getEntityByFields(['anr' => $anr->id]);
                $recipientNewIds = [];
                foreach ($recordRecipients as $r) {
                    $newRecipient = new RecordRecipient($r);
                    $newRecipient->set('id', null);
                    $newRecipient->setAnr($newAnr);
                    $this->get('recordRecipientCliTable')->save($newRecipient, false);
                    $this->get('recordRecipientCliTable')->getDb()->flush();
                    $recipientNewIds[$r->id] = $newRecipient;
                }
                //duplicate record
                $records = $this->get('recordCliTable')->getEntityByFields(['anr' => $anr->id]);
                $recordNewIds = [];
                foreach ($records as $record) {
                    $newRecord = new Record($record);
                    $newRecord->set('id', null);
                    $newRecord->setAnr($newAnr);
                    if ($record->controller != null) {
                        $newRecord->setController($actorNewIds[$record->controller->id]);
                    }
                    if ($record->representative != null) {
                        $newRecord->setRepresentative($actorNewIds[$record->representative->id]);
                    }
                    if ($record->dpo != null) {
                        $newRecord->setDpo($actorNewIds[$record->dpo->id]);
                    }

                    $jointControllerNewIds = [];
                    $jointControllers = $record->getJointControllers();
                    foreach ($jointControllers as $jc) {
                        $jointControllerNewIds[] = $actorNewIds[$jc->id];
                    }
                    $newRecord->setJointControllers($jointControllerNewIds);

                    $processorIds = [];
                    $processors = $record->getProcessors();
                    foreach ($processors as $p) {
                        $processorIds[] = $processorNewIds[$p->id];
                    }
                    $newRecord->setProcessors($processorIds);

                    $recipientIds = [];
                    $recipients = $record->getRecipients();
                    foreach ($recipients as $r) {
                        $recipientIds[$r->id] = $recipientNewIds[$r->id];
                    }
                    $newRecord->setRecipients($recipientIds);

                    $this->get('recordCliTable')->save($newRecord, false);
                    $this->get('recordCliTable')->getDb()->flush();
                    $recordNewIds[$record->id] = $newRecord;
                }

                foreach ($recordProcessors as $p) {
                    $data = [];
                    $activities = $p->getActivities();
                    foreach ($activities as $recordId => $value) {
                        $data["activities"][$recordNewIds[$recordId]->getId()] = $value;
                    }
                    $secMeasures = $p->getSecMeasures();
                    foreach ($secMeasures as $recordId => $value) {
                        $data["secMeasures"][$recordNewIds[$recordId]->getId()] = $value;
                    }
                    $this->recordProcessorService->patch($processorNewIds[$p->id]->getId(), $data);
                }

                //duplicate record personal data
                $recordPersonalData = $this->get('recordPersonalDataCliTable')->getEntityByFields(['anr' => $anr->id]);
                $personalDataNewIds = [];
                foreach ($recordPersonalData as $pd) {
                    $newPersonalData = new RecordPersonalData($pd);
                    $newPersonalData->set('id', null);
                    $newPersonalData->setAnr($newAnr);
                    $newPersonalData->setRecord($recordNewIds[$pd->record->id]);
                    $newDataCategoryIds = [];
                    $dataCategories = $pd->getDataCategories();
                    foreach ($dataCategories as $dc) {
                        $newDataCategoryIds[] = $dataCategoryNewIds[$dc->id];
                    }
                    $newPersonalData->setDataCategories($newDataCategoryIds);
                    $this->get('recordPersonalDataCliTable')->save($newPersonalData, false);
                    $this->get('recordPersonalDataCliTable')->getDb()->flush();
                    $personalDataNewIds[$pd->id] = $newPersonalData;
                }

                //duplicate record international transfers
                $recordInternationalTransfers = $this->get('recordInternationalTransferCliTable')->getEntityByFields(['anr' => $anr->id]);
                $internationalTransferNewIds = [];
                foreach ($recordInternationalTransfers as $it) {
                    $newInternationalTransfer = new RecordInternationalTransfer($it);
                    $newInternationalTransfer->set('id', null);
                    $newInternationalTransfer->setAnr($newAnr);
                    $newInternationalTransfer->setRecord($recordNewIds[$it->record->id]);
                    $this->get('recordInternationalTransferCliTable')->save($newInternationalTransfer, false);
                    $this->get('recordInternationalTransferCliTable')->getDb()->flush();
                    $internationalTransferNewIds[$it->id] = $newInternationalTransfer;
                }

                $recommendationsNewIds = [];
                // duplicate recommandations sets and recommandations
                /** @var RecommandationSet[] $recommendationsSets */
                $recommendationsSets = $this->get('recommandationSetCliTable')->getEntityByFields(['anr' => $anr->id]);
                foreach ($recommendationsSets as $recommendationSet) {
                    $recommendationSetRecommendations = [];

                    $recommendations = $recommendationSet->getRecommandations();
                    $recommendationSet->setRecommandations(null);
                    $newRecommendationSet = new RecommandationSet($recommendationSet);
                    $newRecommendationSet->setAnr($newAnr);

                    foreach ($recommendations as $recommandation) {
                        $newRecommandation = new Recommandation($recommandation);
                        $newRecommandation->setAnr($newAnr);
                        $newRecommandation->setRecommandationSet($newRecommendationSet);
                        $this->get('recommandationCliTable')->saveEntity($newRecommandation, false);
                        $recommendationSetRecommendations[] = $newRecommandation;
                        $recommendationsNewIds[$recommandation->getUuid()] = $newRecommandation;
                    }

                    $newRecommendationSet->setRecommandations($recommendationSetRecommendations);
                    $this->get('recommandationSetCliTable')->save($newRecommendationSet, false);
                }

                // duplicate recommendations historics
                $recommandationsHistorics = $this->get('recommandationHistoricCliTable')->getEntityByFields(['anr' => $anr->id]);
                foreach ($recommandationsHistorics as $recommandationHistoric) {
                    $newRecommandationHistoric = new RecommandationHistoric($recommandationHistoric);
                    $newRecommandationHistoric->set('id', null);
                    $newRecommandationHistoric->setAnr($newAnr);
                    $this->get('recommandationHistoricCliTable')->save($newRecommandationHistoric, false);
                }

                //duplicate recommandations risks
                /** @var RecommandationRiskTable $recommendationRiskTable */
                $recommendationRiskTable = $this->get('recommandationRiskCliTable');
                $recommandationsRisks = $recommendationRiskTable->findByAnr($anr);
                foreach ($recommandationsRisks as $recommandationRisk) {
                    $newRecommendationRisk = (new RecommandationRisk())
                        ->setAnr($newAnr)
                        ->setCommentAfter($recommandationRisk->getCommentAfter())
                        ->setRecommandation(
                            $recommendationsNewIds[$recommandationRisk->getRecommandation()->getUuid()]
                        )
                        ->setInstance($instancesNewIds[$recommandationRisk->getInstance()->getId()]);

                    if ($recommandationRisk->getInstanceRisk()) {
                        $newRecommendationRisk->setInstanceRisk(
                            $instancesRisksNewIds[$recommandationRisk->getInstanceRisk()->getId()]
                        );
                    }
                    if ($recommandationRisk->getInstanceRiskOp()) {
                        $newRecommendationRisk->setInstanceRiskOp(
                            $instancesRisksOpNewIds[$recommandationRisk->getInstanceRiskOp()->getId()]
                        );
                        // TODO: remove when #240 is done.
                        $newRecommendationRisk->setAnr(null);
                    }
                    if ($recommandationRisk->getGlobalObject()
                        && isset($objectsNewIds[$recommandationRisk->getGlobalObject()->getUuid()])
                    ) {
                        $newRecommendationRisk->setGlobalObject(
                            $objectsNewIds[$recommandationRisk->getGlobalObject()->getUuid()]
                        );
                    }
                    if ($recommandationRisk->getAsset()) {
                        $newRecommendationRisk->setAsset($assetsNewIds[$recommandationRisk->getAsset()->getUuid()]);
                    }
                    if ($recommandationRisk->getThreat()) {
                        $newRecommendationRisk->setThreat(
                            $threatsNewIds[$recommandationRisk->getThreat()->getUuid()]
                        );
                    }
                    if ($recommandationRisk->getVulnerability()) {
                        $newRecommendationRisk->setVulnerability(
                            $vulnerabilitiesNewIds[$recommandationRisk->getVulnerability()->getUuid()]
                        );
                    }
                    /*
                     * We do this trick becasue the other relations (asset, threat, vulnerability)
                     * in case of operation risks are null and the anr will be force reset to null.
                     * TODO: remove when #240 is done.
                     */
                    if ($newRecommendationRisk->getAnr() === null) {
                        $recommendationRiskTable->saveEntity($newRecommendationRisk);
                        $newRecommendationRisk->setAnr($newAnr);
                    }

                    $recommendationRiskTable->saveEntity($newRecommendationRisk, false);
                }
            }

            $this->get('table')->getDb()->flush();

            $this->setUserCurrentAnr($newAnr->getId());

        } catch (\Exception $e) {
            if (!empty($newAnr)) {
                $anrCliTable->deleteEntity($newAnr);
            }
            throw new Exception('Error during analysis creation', 412);
        }

        return $newAnr;
    }

    /**
     * Defines the user's "current" (ie. last visited) ANR to the specified ID
     * @param int $anrId The ANR ID
     */
    public function setUserCurrentAnr($anrId)
    {
        //retrieve connected user
        /** @var UserTable $userCliTable */
        $userCliTable = $this->get('userCliTable');

        /** @var CoreUser $connectedUser */
        $connectedUser = $userCliTable->getConnectedUser();

        //retrieve connected user information
        /** @var User $user */
        $user = $userCliTable->findById($connectedUser->getId());

        //record last anr to user
        /** @var AnrTable $anrCliTable */
        $anrCliTable = $this->get('anrCliTable');
        $user->setCurrentAnr($anrCliTable->getEntity($anrId));
        $userCliTable->saveEntity($user);
    }

    /**
     * Recursively retrieves parent categories IDs
     * @param AnrObjectCategory $category The category for which we want the parents
     * @param array $categoriesIds Reference to an array of categories
     * @return array The IDs of the categories of all parents
     */
    public function getParentsCategoryIds($category, &$categoriesIds)
    {
        if ($category->parent) {
            $categoriesIds[] = $category->parent->id;
            $this->getParentsCategoryIds($category->parent, $categoriesIds);

            return $categoriesIds;
        }

        return [];
    }

    /**
     * Returns the color to apply on the ROLF risks
     * @param Anr $anr The ANR Object
     * @param int $value The risk value
     * @param array $classes The classes name to return for low, med and hi risks
     * @return mixed One of the value of $classes
     */
    public function getColor($anr, $value, $classes = ['green', 'orange', 'alerte'])
    {
        if ($value <= $anr->get('seuil1')) {
            return $classes[0];
        } elseif ($value <= $anr->get('seuil2')) {
            return $classes[1];
        } else {
            return $classes[2];
        }
    }


    public function getColorRiskOp($anr, $value, $classes = ['green', 'orange', 'alerte'])
    {
        if ($value <= $anr->get('seuilRolf1')) {
            return $classes[0];
        } elseif ($value <= $anr->get('seuilRolf2')) {
            return $classes[1];
        } else {
            return $classes[2];
        }
    }

    /**
     * @inheritdoc
     */
    public function delete($id)
    {
        /** @var AnrTable $anrTable */
        $anrTable = $this->get('table');
        //retrieve and delete snapshots associated to anr
        $snapshots = $this->get('snapshotCliTable')->getEntityByFields(['anrReference' => $id]);
        foreach ($snapshots as $s) {
            if (!empty($s)) {
                $anrTable->delete($s->get('anr')->get('id'), false);
            }
        }

        // Try to drop the stats.
        try {
            $anr = $anrTable->findById($id);
            /** @var StatsAnrService $statsAnrService */
            $statsAnrService = $this->get('statsAnrService');
            $statsAnrService->deleteStatsForAnr($anr->getUuid());
        } catch (Throwable $e) {}

        return $anrTable->delete($id);
    }

    /**
     * Returns an array that specifies in which language the model may be instancied
     * @param int $modelId The model ID
     * @return array The array of languages that are valid
     */
    public function verifyLanguage($modelId)
    {
        $languages = [1, 2, 3, 4]; // TODO: why don't use config list ?
        $success = [];
        foreach ($languages as $lang) {
            $success[$lang] = true;
        }

        //model
        $model = $this->get('modelTable')->getEntity($modelId);
        foreach ($languages as $lang) {
            if (empty($model->get('label' . $lang))) {
                $success[$lang] = false;
            }
        }

        //themes, measures, rolf tags, rolf risks, object categories, questions and questions choices
        $array = [
            'theme' => 'label',
            'measure' => 'label',
            'rolfRisk' => 'label',
            'rolfTag' => 'label',
            'objectCategory' => 'label',
            'question' => 'label',
            'questionChoice' => 'label',
        ];
        foreach ($array as $key => $value) {
            $entities = $this->get($key . 'Table')->fetchAllObject();
            foreach ($entities as $entity) {
                foreach ($languages as $lang) {
                    if (empty($entity->get($value . $lang))) {
                        $success[$lang] = false;
                    }
                }
            }
        }

        if ($model->get('anr')) {
            //instances
            $instances = $this->get('instanceTable')->getEntityByFields(['anr' => $model->get('anr')->get('id')]);
            foreach ($instances as $instance) {
                foreach ($languages as $lang) {
                    if (empty($instance->get('name' . $lang))) {
                        $success[$lang] = false;
                    }
                    if (empty($instance->get('label' . $lang))) {
                        $success[$lang] = false;
                    }
                }
            }

            //scales impact types
            $scalesImpactsTypes = $this->get('scaleImpactTypeTable')->getEntityByFields(['anr' => $model->get('anr')->get('id')]);
            foreach ($scalesImpactsTypes as $scaleImpactType) {
                foreach ($languages as $lang) {
                    if (empty($scaleImpactType->get('label' . $lang))) {
                        $success[$lang] = false;
                    }
                }
            }
        } else {
            foreach ($languages as $lang) {
                $success[$lang] = false;
            }
        }

        //assets, threats and vulnerabilities
        $array = ['asset', 'threat', 'vulnerability'];
        foreach ($array as $value) {
            $entities1 = [];
            if (!$model->isRegulator) {
                $entities1 = $this->get($value . 'Table')->getEntityByFields(['mode' => Asset::MODE_GENERIC]);
            }
            $entities2 = [];
            if (!$model->isGeneric) {
                $entities2 = $this->get($value . 'Table')->getEntityByFields(['mode' => Asset::MODE_SPECIFIC]);
            }
            $entities = $entities1 + $entities2;
            foreach ($entities as $entity) {
                foreach ($languages as $lang) {
                    if (empty($entity->get('label' . $lang))) {
                        $success[$lang] = false;
                    } else {
                        ${$value}[$entity->getUuid()] = $entity->getUuid();
                    }
                }
            }
        }

        //objects
        if ($model->get('anr')) {
            $objects = $this->get('MonarcObjectTable')->fetchAllObject();
            foreach ($objects as $key => $object) {
                $existInAnr = false;
                foreach ($object->anrs as $anrObject) {
                    if ($anrObject->id == $model->get('anr')->get('id')) {
                        $existInAnr = true;
                    }
                }
                if (!$existInAnr) {
                    unset($objects[$key]);
                }
            }
            foreach ($objects as $object) {
                foreach ($languages as $lang) {
                    if (empty($object->get('label' . $lang))) {
                        $success[$lang] = false;
                    }
                    if (empty($object->get('name' . $lang))) {
                        $success[$lang] = false;
                    }
                }
            }
        } else {
            foreach ($languages as $lang) {
                $success[$lang] = false;
            }
        }

        return $success;
    }

    private function createScalesFromSourceAnr(
        Anr $newAnr,
        AnrSuperClass $sourceAnr,
        string $sourceName,
        UserSuperClass $connectedUser
    ): array {
        $scalesImpactTypesOldIdsToNewObjectsMap = [];
        /** @var ScaleTable $scaleCliTable */
        $scaleCliTable = $this->get('scaleCliTable');
        /** @var ScaleTable|CoreScaleTable $scaleTable */
        $scaleTable = $sourceName === MonarcObject::SOURCE_COMMON
            ? $this->get('scaleTable')
            : $scaleCliTable;
        /** @var ScaleImpactTypeTable $scaleImpactTypeCliTable */
        $scaleImpactTypeCliTable = $this->get('scaleImpactTypeCliTable');

        $scales = $scaleTable->findByAnr($sourceAnr);
        foreach ($scales as $scale) {
            $newScale = (new Scale())
                ->setAnr($newAnr)
                ->setType($scale->getType())
                ->setMin($scale->getMin())
                ->setMax($scale->getMax())
                ->setCreator($connectedUser->getFirstname() . ' ' . $connectedUser->getLastname());

            $scaleCliTable->saveEntity($newScale, false);

            foreach ($scale->getScaleImpactTypes() as $scaleImpactType) {
                $newScaleImpactType = (new ScaleImpactType())
                    ->setAnr($newAnr)
                    ->setScale($newScale)
                    ->setIsHidden($scaleImpactType->isHidden())
                    ->setIsSys($scaleImpactType->isSys())
                    ->setLabels([
                        'label1' => $scaleImpactType->getLabel(1),
                        'label2' => $scaleImpactType->getLabel(2),
                        'label3' => $scaleImpactType->getLabel(3),
                        'label4' => $scaleImpactType->getLabel(4),
                    ])
                    ->setType($scaleImpactType->getType())
                    ->setPosition($scaleImpactType->getPosition())
                    ->setCreator($connectedUser->getFirstname() . ' ' . $connectedUser->getLastname());

                $scaleImpactTypeCliTable->saveEntity($newScaleImpactType, false);

                $scalesImpactTypesOldIdsToNewObjectsMap[$scaleImpactType->getId()] = $newScaleImpactType;

                foreach ($scaleImpactType->getScaleComments() as $scaleComment) {
                    $this->createScaleCommentsFromSource(
                        $newAnr,
                        $newScale,
                        $newScaleImpactType,
                        $scaleComment,
                        $connectedUser
                    );
                }
            }

            foreach ($scale->getScaleComments() as $scaleComment) {
                if ($scaleComment->getScaleImpactType() !== null) {
                    continue;
                }

                $this->createScaleCommentsFromSource($newAnr, $newScale, null, $scaleComment, $connectedUser);
            }
        }

        return $scalesImpactTypesOldIdsToNewObjectsMap;
    }

    private function createScaleCommentsFromSource(
        Anr $newAnr,
        ScaleSuperClass $newScale,
        ?ScaleImpactTypeSuperClass $newScaleImpactType,
        ScaleCommentSuperClass $sourceScaleComment,
        UserSuperClass $connectedUser
    ): void {
        /** @var ScaleCommentTable $scaleCommentCliTable */
        $scaleCommentCliTable = $this->get('scaleCommentCliTable');

        $newScaleComment = (new ScaleComment())
            ->setAnr($newAnr)
            ->setScale($newScale)
            ->setScaleIndex($sourceScaleComment->getScaleIndex())
            ->setScaleValue($sourceScaleComment->getScaleValue())
            ->setComments([
                'comment1' => $sourceScaleComment->getComment(1),
                'comment2' => $sourceScaleComment->getComment(2),
                'comment3' => $sourceScaleComment->getComment(3),
                'comment4' => $sourceScaleComment->getComment(4),
            ])
            ->setCreator($connectedUser->getFirstname() . ' ' . $connectedUser->getLastname());
        if ($newScaleImpactType !== null) {
            $newScaleComment->setScaleImpactType($newScaleImpactType);
        }

        $scaleCommentCliTable->saveEntity($newScaleComment, false);
    }

    private function createOperationalRiskScalesFromSourceAnr(
        Anr $newAnr,
        AnrSuperClass $sourceAnr,
        string $sourceName,
        UserSuperClass $connectedUser
    ): array {
        $operationalScaleTypesOldIdsToNewObjectsMap = [];
        /** @var OperationalRiskScaleTable $operationalRiskScaleCliTable */
        $operationalRiskScaleCliTable = $this->get('operationalRiskScaleCliTable');
        /** @var OperationalRiskScaleTable|CoreOperationalRiskScaleTable $sourceOperationalRiskScaleTable */
        $sourceOperationalRiskScaleTable = $sourceName === MonarcObject::SOURCE_COMMON
            ? $this->get('operationalRiskScaleTable')
            : $operationalRiskScaleCliTable;
        /** @var OperationalRiskScaleTypeTable $operationalRiskScaleTypeCliTable */
        $operationalRiskScaleTypeCliTable = $this->get('operationalRiskScaleTypeCliTable');
        /** @var TranslationTable|CoreTranslationTable $sourceTranslationTable */
        $sourceTranslationTable = $sourceName === MonarcObject::SOURCE_COMMON
            ? $this->get('translationTable')
            : $this->get('translationCliTable');

        $sourceTranslations = $sourceTranslationTable->findByAnrTypesAndLanguageIndexedByKey(
            $sourceAnr,
            [OperationalRiskScaleType::TRANSLATION_TYPE_NAME, OperationalRiskScaleComment::TRANSLATION_TYPE_NAME],
            $this->getAnrLanguageCode($newAnr)
        );

        $operationalRiskScales = $sourceOperationalRiskScaleTable->findByAnr($sourceAnr);
        foreach ($operationalRiskScales as $operationalRiskScale) {
            $newOperationalRiskScale = (new OperationalRiskScale())
                ->setAnr($newAnr)
                ->setType($operationalRiskScale->getType())
                ->setMin($operationalRiskScale->getMin())
                ->setMax($operationalRiskScale->getMax())
                ->setCreator($connectedUser->getEmail());

            foreach ($operationalRiskScale->getOperationalRiskScaleTypes() as $operationalRiskScaleType) {
                $newOperationalRiskScaleType = (new OperationalRiskScaleType())
                    ->setAnr($newAnr)
                    ->setOperationalRiskScale($newOperationalRiskScale)
                    ->setLabelTranslationKey($operationalRiskScaleType->getLabelTranslationKey())
                    ->setIsHidden($operationalRiskScaleType->isHidden())
                    ->setCreator($connectedUser->getEmail());

                $operationalRiskScaleTypeCliTable->save($newOperationalRiskScaleType, false);

                $operationalScaleTypesOldIdsToNewObjectsMap[$operationalRiskScaleType->getId()]
                    = $newOperationalRiskScaleType;

                $this->createTranslationFromSource(
                    $newAnr,
                    $sourceTranslations[$operationalRiskScaleType->getLabelTranslationKey()],
                    $connectedUser
                );

                foreach ($operationalRiskScaleType->getOperationalRiskScaleComments() as $operationalRiskScaleComment) {
                    $this->createOperationalRiskScaleCommentsFromSource(
                        $newAnr,
                        $newOperationalRiskScale,
                        $newOperationalRiskScaleType,
                        $operationalRiskScaleComment,
                        $sourceTranslations[$operationalRiskScaleComment->getCommentTranslationKey()],
                        $connectedUser
                    );
                }
            }

            foreach ($operationalRiskScale->getOperationalRiskScaleComments() as $operationalRiskScaleComment) {
                if ($operationalRiskScaleComment->getOperationalRiskScaleType() !== null) {
                    continue;
                }

                $this->createOperationalRiskScaleCommentsFromSource(
                    $newAnr,
                    $newOperationalRiskScale,
                    null,
                    $operationalRiskScaleComment,
                    $sourceTranslations[$operationalRiskScaleComment->getCommentTranslationKey()],
                    $connectedUser
                );
            }

            $operationalRiskScaleCliTable->save($newOperationalRiskScale, false);
        }

        return $operationalScaleTypesOldIdsToNewObjectsMap;
    }

    private function createOperationalRiskScaleCommentsFromSource(
        Anr $newAnr,
        OperationalRiskScaleSuperClass $newOperationalRiskScale,
        ?OperationalRiskScaleTypeSuperClass $newOperationalRiskScaleType,
        OperationalRiskScaleCommentSuperClass $sourceOperationalRiskScaleComment,
        TranslationSuperClass $sourceTranslation,
        UserSuperClass $connectedUser
    ): void {
        /** @var OperationalRiskScaleCommentTable $operationalRiskScaleCommentCliTable */
        $operationalRiskScaleCommentCliTable = $this->get('operationalRiskScaleCommentCliTable');

        $newOperationalRiskScaleComment = (new OperationalRiskScaleComment())
            ->setAnr($newAnr)
            ->setScaleIndex($sourceOperationalRiskScaleComment->getScaleIndex())
            ->setScaleValue($sourceOperationalRiskScaleComment->getScaleValue())
            ->setCommentTranslationKey($sourceOperationalRiskScaleComment->getCommentTranslationKey())
            ->setOperationalRiskScale($newOperationalRiskScale)
            ->setIsHidden($sourceOperationalRiskScaleComment->isHidden())
            ->setCreator($connectedUser->getEmail());
        if ($newOperationalRiskScaleType !== null) {
            $newOperationalRiskScaleComment->setOperationalRiskScaleType($newOperationalRiskScaleType);
        }

        $operationalRiskScaleCommentCliTable->save($newOperationalRiskScaleComment, false);

        $this->createTranslationFromSource($newAnr, $sourceTranslation, $connectedUser);
    }

    private function createTranslationFromSource(
        Anr $newAnr,
        TranslationSuperClass $sourceTranslation,
        UserSuperClass $connectedUser
    ): void {
        /** @var TranslationTable $translationCliTable */
        $translationCliTable = $this->get('translationCliTable');

        $newTranslation = (new Translation())
            ->setAnr($newAnr)
            ->setType($sourceTranslation->getType())
            ->setKey($sourceTranslation->getKey())
            ->setLang($sourceTranslation->getLang())
            ->setValue($sourceTranslation->getValue())
            ->setCreator($connectedUser->getEmail());

        $translationCliTable->save($newTranslation, false);
    }

    private function createOperationalInstanceRiskScalesFromSource(
        InstanceRiskOpSuperClass $instanceRiskOp,
        array $operationalScaleTypesOldIdsToNewObjectsMap,
        Anr $newAnr,
        InstanceRiskOp $newInstanceRiskOp,
        UserSuperClass $connectedUser
    ): void {
        /** @var OperationalInstanceRiskScaleTable $operationalInstanceRiskScaleCliTable */
        $operationalInstanceRiskScaleCliTable = $this->get('operationalInstanceRiskScaleCliTable');
        foreach ($instanceRiskOp->getOperationalInstanceRiskScales() as $operationalInstanceRiskScale) {
            $operationalRiskScaleType = $operationalScaleTypesOldIdsToNewObjectsMap[
                $operationalInstanceRiskScale->getOperationalRiskScaleType()->getId()
            ];

            $operationalInstanceRiskScale = (new OperationalInstanceRiskScale())
                ->setAnr($newAnr)
                ->setOperationalInstanceRisk($newInstanceRiskOp)
                ->setOperationalRiskScaleType($operationalRiskScaleType)
                ->setBrutValue($operationalInstanceRiskScale->getBrutValue())
                ->setNetValue($operationalInstanceRiskScale->getNetValue())
                ->setTargetedValue($operationalInstanceRiskScale->getTargetedValue())
                ->setCreator($connectedUser->getEmail());
            $operationalInstanceRiskScaleCliTable->save($operationalInstanceRiskScale, false);
        }
    }

    private function getOrCreateInstanceRiskOwner(
        AnrSuperClass $anr,
        string $ownerName,
        UserSuperClass $connectedUser
    ): InstanceRiskOwner {
        if (!isset($this->cachedData['instanceRiskOwners'][$ownerName])) {
            /** @var InstanceRiskOwnerTable $instanceRiskOwnerTable */
            $instanceRiskOwnerTable = $this->get('instanceRiskOwnerCliTable');
            $instanceRiskOwner = $instanceRiskOwnerTable->findByAnrAndName($anr, $ownerName);
            if ($instanceRiskOwner === null) {
                $instanceRiskOwner = (new InstanceRiskOwner())
                    ->setAnr($anr)
                    ->setName($ownerName)
                    ->setCreator($connectedUser->getEmail());

                $instanceRiskOwnerTable->save($instanceRiskOwner, false);
            }

            $this->cachedData['instanceRiskOwners'][$ownerName] = $instanceRiskOwner;
        }

        return $this->cachedData['instanceRiskOwners'][$ownerName];
    }

    private function getAnrLanguageCode(AnrSuperClass $anr): string
    {
        /** @var ConfigService $configService */
        $configService = $this->get('configService');

        return strtolower($configService->getLanguageCodes()[$anr->getLanguage()]);
    }
}
