<?php
namespace MonarcFO\Service;

use MonarcCore\Model\Entity\AnrSuperClass;
use MonarcFO\Model\Entity\Interview;
use MonarcFO\Model\Entity\Recommandation;
use MonarcFO\Model\Entity\RecommandationHistoric;
use MonarcFO\Model\Entity\RecommandationMeasure;
use MonarcFO\Model\Entity\RecommandationRisk;
use MonarcFO\Model\Entity\RolfTag;
use MonarcFO\Model\Entity\User;
use MonarcFO\Model\Table\AnrTable;
use MonarcFO\Model\Table\InstanceTable;
use MonarcFO\Model\Table\ModelTable;
use MonarcFO\Model\Table\SnapshotTable;
use MonarcFO\Model\Table\UserAnrTable;
use MonarcFO\Model\Table\UserRoleTable;
use MonarcFO\Service\AbstractService;
use MonarcFO\Model\Entity\Asset;
use MonarcFO\Model\Entity\Object;
use MonarcFO\Model\Entity\Threat;
use MonarcFO\Model\Entity\Vulnerability;
use MonarcFO\Model\Table\UserTable;

/**
 * Anr Service
 *
 * Class AnrService
 * @package MonarcFO\Service
 */
class AnrService extends \MonarcCore\Service\AbstractService
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
    protected $objectTable;
    protected $objectCategoryTable;
    protected $objectObjectTable;
    protected $rolfCategoryTable;
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
    protected $recommandationMeasureCliTable;
    protected $recommandationRiskCliTable;
    protected $rolfCategoryCliTable;
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

    protected $instanceService;

    /**
     * Get List
     *
     * @param int $page
     * @param int $limit
     * @param null $order
     * @param null $filter
     * @return mixed
     */
    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null)
    {
        //retrieve connected user
        /** @var UserTable $userCliTable */
        $userCliTable = $this->get('userCliTable');
        $userArray = $userCliTable->getConnectedUser();

        //retrieve roles for connected user
        /** @var UserRoleTable $userRoleTable */
        $userRoleTable = $this->get('userRoleTable');
        $userRoles = $userRoleTable->getEntityByFields(['user' => $userArray['id']]);

        //verify if connected user is admin
        $isSuperAdmin = false;
        foreach ($userRoles as $userRole) {
            if ($userRole->role == 'superadminfo') {
                $isSuperAdmin = true;
                break;
            }
        }

        //retrieve connected user anrs
        $filterAnd['id'] = [];
        if (!$isSuperAdmin) {
            $anrs = $this->get('userAnrCliTable')->getEntityByFields(['user' => $userArray['id']]);
            foreach ($anrs as $a) {
                $filterAnd['id'][$a->get('anr')->get('id')] = $a->get('anr')->get('id');
            }
        } else {
            $anrs = $this->anrCliTable->fetchAllObject();
            foreach ($anrs as $a) {
                $filterAnd['id'][$a->get('id')] = $a->get('id');
            }
        }

        //remove snapshots of connected user anrs
        /** @var SnapshotTable $snapshotCliTable */
        $snapshotCliTable = $this->get('snapshotCliTable');
        $snapshots = $snapshotCliTable->getEntityByFields(['anr' => $filterAnd['id']]);
        foreach ($snapshots as $snapshot) {
            unset($filterAnd['id'][$snapshot->get('anr')->get('id')]);
        }

        //retrieve anrs information
        $anrs = $this->get('table')->fetchAllFiltered(
            array_keys($this->get('entity')->getJsonArray()),
            $page,
            $limit,
            $this->parseFrontendOrder($order),
            $this->parseFrontendFilter($filter, $this->filterColumns),
            $filterAnd
        );

        $user = $userCliTable->get($userArray['id']);
        foreach ($anrs as &$anr) {
            //verify if this is the last current user's anr
            if (isset($user['currentAnr']) && $anr['id'] == $user['currentAnr']->get('id')) {
                $anr['isCurrentAnr'] = 1;
            }

            $lk = current($this->get('userAnrCliTable')->getEntityByFields(['user' => $userArray['id'], 'anr' => $anr['id']]));
            $anr['rwd'] = (empty($lk)) ? -1 : $lk->get('rwd');
        }

        return $anrs;
    }

    /**
     * Get Filtered Count
     *
     * @param null $filter
     * @return int
     */
    public function getFilteredCount($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null)
    {
        return count($this->getList($page, $limit, $order, $filter, $filterAnd));
    }

    /**
     * Get Anrs
     * @return array|bool
     */
    public function getAnrs()
    {
        return $this->get('anrCliTable')->fetchAll();
    }

    /**
     * Get Entity
     *
     * @param $id
     * @return mixed
     * @throws \Exception
     */
    public function getEntity($id)
    {
        $anr = $this->get('table')->get($id);

        //retrieve snapshot
        /** @var SnapshotTable $snapshotCliTable */
        $snapshotCliTable = $this->get('snapshotCliTable');
        $anrSnapshot = current($snapshotCliTable->getEntityByFields(['anr' => $id]));

        $anr['isSnapshot'] = 0;
        $anr['snapshotParent'] = null;
        if (!empty($anrSnapshot)) { // On est sur un snapshot
            $anr['isSnapshot'] = 1;
            $anr['rwd'] = 0;
            $anr['snapshotParent'] = $anrSnapshot->get('anrReference')->get('id');
        } else {
            $userCliTable = $this->get('userCliTable');
            $userArray = $userCliTable->getConnectedUser();

            $lk = current($this->get('userAnrCliTable')->getEntityByFields(['user' => $userArray['id'], 'anr' => $anr['id']]));
            if (empty($lk)) {
                throw new \Exception('Restricted ANR', 412);
            } else {
                $anr['rwd'] = $lk->get('rwd');
            }
        }

        $this->setUserCurrentAnr($id);

        return $anr;
    }

    /**
     * Create From Model To Client
     *
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    public function createFromModelToClient($data)
    {
        //retrieve model information
        /** @var ModelTable $modelTable */
        $modelTable = $this->get('modelTable');
        $model = $modelTable->getEntity($data['model']);
        unset($data['model']);
        if ($model->get('status') != \MonarcCore\Model\Entity\AbstractEntity::STATUS_ACTIVE) { // disabled or deleted
            throw new \Exception('Model not found', 412);
        }

        return $this->duplicateAnr($model->anr, Object::SOURCE_COMMON, $model, $data);
    }

    /**
     * Duplicate Anr
     *
     * @param $anr
     * @param string $source
     * @param null $model
     * @return mixed
     * @throws \Exception
     */
    public function duplicateAnr($anr, $source = Object::SOURCE_CLIENT, $model = null, $data = [], $isSnapshot = false, $isSnapshotCloning = false)
    {
        ini_set('max_execution_time', 0);

        if (is_integer($anr)) {
            /** @var AnrTable $anrTable */
            $anrTable = ($source == Object::SOURCE_COMMON) ? $this->get('anrTable') : $this->get('anrCliTable');
            $anr = $anrTable->getEntity($anr);
        }

        if (!$anr instanceof AnrSuperClass) {
            throw new \Exception('Anr missing', 412);
        }
        if (empty($model)) {
            $idModel = $anr->get('model');
        } else {
            $idModel = $model->get('id');
        }

        /** @var UserTable $userCliTable */
        $userCliTable = $this->get('userCliTable');
        $userArray = $userCliTable->getConnectedUser();

        if ($source == Object::SOURCE_CLIENT && !$isSnapshotCloning) {
            /** @var UserAnrTable $userAnrCliTable */
            $userAnrCliTable = $this->get('userAnrCliTable');
            $userAnr = $userAnrCliTable->getEntityByFields(['anr' => $anr->id, 'user' => $userArray['id']]);

            if (count($userAnr) == 0) {
                throw new \Exception('You are not authorized to duplicate this analysis', 412);
            }
        }

        try {
            //duplicate anr
            $newAnr = new \MonarcFO\Model\Entity\Anr($anr);
            $newAnr->setId(null);
            $newAnr->setObjects(null);
            $newAnr->exchangeArray($data);
            $newAnr->set('model', $idModel);
            if (!empty($model) && is_object($model)) {
                $newAnr->set('cacheModelShowRolfBrut', $model->showRolfBrut);
                $newAnr->set('cacheModelIsScalesUpdatable', $model->isScalesUpdatable);
            }
            if ($isSnapshot) { // Si c'est un snapshot on ajoute le préfixe "[SNAP]"
                for ($i = 1; $i <= 4; $i++) {
                    $lab = trim($newAnr->get('label' . $i));
                    if (!empty($lab)) {
                        $newAnr->set('label' . $i, '[SNAP] ' . $lab);
                    }
                }
            }

            /** @var AnrTable $anrCliTable */
            $anrCliTable = $this->get('anrCliTable');
            $id = $anrCliTable->save($newAnr,false);

            if (!$isSnapshot && !$isSnapshotCloning) { // inutile si c'est un snapshot & dans la cas d'un restore (SnapshotService::restore)
                //add user to anr
                $userCliTable = $this->get('userCliTable');
                $userArray = $userCliTable->getConnectedUser();
                $user = $userCliTable->getEntity($userArray['id']);
                $userAnr = new \MonarcFO\Model\Entity\UserAnr();
                $userAnr->set('id', null);
                $userAnr->setUser($user);
                $userAnr->setAnr($newAnr);
                $userAnr->setRwd(1);
                $this->get('userAnrCliTable')->save($userAnr,false);
            }

            //duplicate themes
            $themesNewIds = [];
            $themes = ($source == Object::SOURCE_COMMON) ? $this->get('themeTable')->fetchAllObject() : $this->get('themeCliTable')->getEntityByFields(['anr' => $anr->id]);
            foreach ($themes as $theme) {
                $newTheme = new \MonarcFO\Model\Entity\Theme($theme);
                $newTheme->set('id', null);
                $newTheme->setAnr($newAnr);
                $this->get('themeCliTable')->save($newTheme,false);
                $themesNewIds[$theme->id] = $newTheme;
            }

            //duplicate assets
            $assetsNewIds = [];
            if ($source == Object::SOURCE_COMMON) {
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
                $newAsset = new \MonarcFO\Model\Entity\Asset($asset);
                $newAsset->set('id', null);
                $newAsset->setAnr($newAnr);
                $this->get('assetCliTable')->save($newAsset,false);
                $assetsNewIds[$asset->id] = $newAsset;
            }

            //duplicate threats
            $threatsNewIds = [];
            if ($source == Object::SOURCE_COMMON) {
                $threats = [];
                if (!$model->isRegulator) {
                    $threats = $this->get('threatTable')->getEntityByFields(['mode' => Threat::MODE_GENERIC]);
                }
                $threats2 = [];
                if (!$model->isGeneric) {
                    $threats2 = $this->get('threatTable')->getEntityByFields(['mode' => Threat::MODE_SPECIFIC]);
                    foreach($threats2 as $t){
                        array_push($threats,$t);
                    }
                    unset($threats2);
                }
            } else {
                $threats = $this->get('threatCliTable')->getEntityByFields(['anr' => $anr->id]);
            }
            foreach ($threats as $threat) {
                $newThreat = new \MonarcFO\Model\Entity\Threat($threat);
                $newThreat->set('id', null);
                $newThreat->setAnr($newAnr);
                if ($threat->theme) {
                    $newThreat->setTheme($themesNewIds[$threat->theme->id]);
                }
                $this->get('threatCliTable')->save($newThreat,false);
                $threatsNewIds[$threat->id] = $newThreat;
            }

            //duplicate vulnerabilities
            $vulnerabilitiesNewIds = [];
            if ($source == Object::SOURCE_COMMON) {
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
                $newVulnerability = new \MonarcFO\Model\Entity\Vulnerability($vulnerability);
                $newVulnerability->set('id', null);
                $newVulnerability->setAnr($newAnr);
                $this->get('vulnerabilityCliTable')->save($newVulnerability,false);
                $vulnerabilitiesNewIds[$vulnerability->id] = $newVulnerability;
            }

            //duplicate measures
            $measuresNewIds = [];
            $measures = ($source == Object::SOURCE_COMMON) ? $this->get('measureTable')->fetchAllObject() : $this->get('measureCliTable')->getEntityByFields(['anr' => $anr->id]);
            foreach ($measures as $measure) {
                $newMeasure = new \MonarcFO\Model\Entity\Measure($measure);
                $newMeasure->set('id', null);
                $newMeasure->setAnr($newAnr);
                $this->get('measureCliTable')->save($newMeasure,false);
                $measuresNewIds[$measure->id] = $newMeasure;
            }

            //duplicate amvs
            $amvsNewIds = [];
            $amvs = ($source == Object::SOURCE_COMMON) ? $this->get('amvTable')->fetchAllObject() : $this->get('amvCliTable')->getEntityByFields(['anr' => $anr->id]);
            foreach ($amvs as $key => $amv) {
                if (
                    (!isset($assetsNewIds[$amv->asset->id])) ||
                    (!isset($threatsNewIds[$amv->threat->id])) ||
                    (!isset($vulnerabilitiesNewIds[$amv->vulnerability->id]))
                ) {
                    unset($amvs[$key]);
                }
            }
            foreach ($amvs as $amv) {
                $newAmv = new \MonarcFO\Model\Entity\Amv($amv);
                $newAmv->set('id', null);
                $newAmv->setAnr($newAnr);
                $newAmv->setAsset($assetsNewIds[$amv->asset->id]);
                $newAmv->setThreat($threatsNewIds[$amv->threat->id]);
                $newAmv->setVulnerability($vulnerabilitiesNewIds[$amv->vulnerability->id]);
                if ($amv->measure1) {
                    $newAmv->setMeasure1($measuresNewIds[$amv->measure1->id]);
                }
                if ($amv->measure2) {
                    $newAmv->setMeasure2($measuresNewIds[$amv->measure2->id]);
                }
                if ($amv->measure3) {
                    $newAmv->setMeasure3($measuresNewIds[$amv->measure3->id]);
                }
                $this->get('amvCliTable')->save($newAmv,false);
                $amvsNewIds[$amv->id] = $newAmv;
            }

            //duplicate rolf categories
            $rolfCategoriesNewIds = [];
            $rolfCategories = ($source == Object::SOURCE_COMMON) ? $this->get('rolfCategoryTable')->fetchAllObject() : $this->get('rolfCategoryCliTable')->getEntityByFields(['anr' => $anr->id]);
            foreach ($rolfCategories as $rolfCategory) {
                $newRolfCategory = new \MonarcFO\Model\Entity\RolfCategory($rolfCategory);
                $newRolfCategory->set('id', null);
                $newRolfCategory->setAnr($newAnr);
                $this->get('rolfCategoryCliTable')->save($newRolfCategory,false);
                $rolfCategoriesNewIds[$rolfCategory->id] = $newRolfCategory;
            }

            //duplicate rolf tags
            $rolfTagsNewIds = [];
            $rolfTags = ($source == Object::SOURCE_COMMON) ? $this->get('rolfTagTable')->fetchAllObject() : $this->get('rolfTagCliTable')->getEntityByFields(['anr' => $anr->id]);
            foreach ($rolfTags as $rolfTag) {
                $newRolfTag = new \MonarcFO\Model\Entity\RolfTag($rolfTag);
                $newRolfTag->set('id', null);
                $newRolfTag->setAnr($newAnr);
                $newRolfTag->set('risks', []);
                $this->get('rolfTagCliTable')->save($newRolfTag,false);
                $rolfTagsNewIds[$rolfTag->id] = $newRolfTag;
            }

            //duplicate rolf risk
            $rolfRisksNewIds = [];
            $rolfRisks = ($source == Object::SOURCE_COMMON) ? $this->get('rolfRiskTable')->fetchAllObject() : $this->get('rolfRiskCliTable')->getEntityByFields(['anr' => $anr->id]);
            foreach ($rolfRisks as $rolfRisk) {
                $newRolfRisk = new \MonarcFO\Model\Entity\RolfRisk($rolfRisk);
                $newRolfRisk->set('id', null);
                $newRolfRisk->setAnr($newAnr);
                foreach ($rolfRisk->categories as $key => $category) {
                    if (!empty($rolfCategoriesNewIds[$category->id])) {
                        $newRolfRisk->setCategory($key, $rolfCategoriesNewIds[$category->id]);
                    }
                }
                foreach ($rolfRisk->tags as $key => $tag) {
                    if (!empty($rolfTagsNewIds[$tag->id])) {
                        $newRolfRisk->setTag($key, $rolfTagsNewIds[$tag->id]);
                    }
                }
                $this->get('rolfRiskCliTable')->save($newRolfRisk,false);
                $rolfRisksNewIds[$rolfRisk->id] = $newRolfRisk;
            }

            //duplicate rolf risk/tags association
            /** @var RolfTag $rolfTag */
            /*foreach ($rolfTags as $rolfTag) {
                $tag = $rolfTagsNewIds[$rolfTag->id];
                $risks = $rolfTag->get('risks');
                $newRisks = [];

                foreach ($risks as $risk) {
                    $newRisks[] = $rolfRisksNewIds[$risk->id];
                }

                $tag->set('risks', $newRisks);
                $this->get('rolfTagCliTable')->save($tag, $last);
            }*/

            //duplicate objects categories
            $objects = ($source == Object::SOURCE_COMMON) ? $this->get('objectTable')->fetchAllObject() : $this->get('objectCliTable')->getEntityByFields(['anr' => $anr->id]);
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
            $objectsCategories = ($source == Object::SOURCE_COMMON) ? $this->get('objectCategoryTable')->fetchAllObject() : $this->get('objectCategoryCliTable')->getEntityByFields(['anr' => $anr->id], ['parent' => 'ASC']);
            foreach ($objectsCategories as $objectCategory) {
                if (in_array($objectCategory->id, $categoriesIds)) {
                    $newObjectCategory = new \MonarcFO\Model\Entity\ObjectCategory($objectCategory);
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

            //duplicate objects
            $objectsNewIds = [];
            $objectsRootCategories = [];
            foreach ($objects as $object) {
                $newObject = new \MonarcFO\Model\Entity\Object($object);
                $newObject->set('id', null);
                $newObject->setAnr($newAnr);
                $newObject->setAnrs(null);
                $newObject->addAnr($newAnr);
                if (!is_null($object->category)) {
                    $newObject->setCategory($objectsCategoriesNewIds[$object->category->id]);
                }
                $newObject->setAsset($assetsNewIds[$object->asset->id]);
                if ($object->rolfTag) {
                    $newObject->setRolfTag($rolfTagsNewIds[$object->rolfTag->id]);
                }
                $this->get('objectCliTable')->save($newObject,false);
                $objectsNewIds[$object->id] = $newObject;

                //root category
                if (!is_null($object->category)) {
                    $objectCategoryTable = ($source == Object::SOURCE_COMMON) ? $this->get('objectCategoryTable') : $this->get('objectCategoryCliTable');
                    $objectCategory = $objectCategoryTable->getEntity($object->category->id);
                    $objectsRootCategories[] = ($objectCategory->root) ? $objectCategory->root->id : $objectCategory->id;
                }
            }

            $objectsRootCategories = array_unique($objectsRootCategories);

            //duplicate anrs objects categories
            $anrObjectCategoryTable = ($source == Object::SOURCE_COMMON) ? $this->get('anrObjectCategoryTable') : $this->get('anrObjectCategoryCliTable');
            $anrObjectsCategories = $anrObjectCategoryTable->getEntityByFields(['anr' => $anr->id]);
            foreach ($anrObjectsCategories as $key => $anrObjectCategory) {
                if (!in_array($anrObjectCategory->category->id, $objectsRootCategories)) {
                    unset($anrObjectsCategories[$key]);
                }
            }
            foreach ($anrObjectsCategories as $key => $anrObjectCategory) {
                $newAnrObjectCategory = new \MonarcFO\Model\Entity\AnrObjectCategory($anrObjectCategory);
                $newAnrObjectCategory->set('id', null);
                $newAnrObjectCategory->setAnr($newAnr);
                $newAnrObjectCategory->setCategory($objectsCategoriesNewIds[$anrObjectCategory->category->id]);
                $this->get('anrObjectCategoryCliTable')->save($newAnrObjectCategory,false);
            }

            //duplicate objects objects
            $objectsObjects = ($source == Object::SOURCE_COMMON) ? $this->get('objectObjectTable')->fetchAllObject() : $this->get('objectObjectCliTable')->getEntityByFields(['anr' => $anr->id]);
            foreach ($objectsObjects as $key => $objectObject) {
                if (!($objectObject->father && isset($objectsNewIds[$objectObject->father->id]) && $objectObject->child && isset($objectsNewIds[$objectObject->child->id]))) {
                    unset($objectsObjects[$key]);
                }
            }
            foreach ($objectsObjects as $objectObject) {
                $newObjectObject = new \MonarcFO\Model\Entity\ObjectObject($objectObject);
                $newObjectObject->set('id', null);
                $newObjectObject->setAnr($newAnr);
                $newObjectObject->setFather($objectsNewIds[$objectObject->father->id]);
                $newObjectObject->setChild($objectsNewIds[$objectObject->child->id]);
                $this->get('objectObjectCliTable')->save($newObjectObject,false);
            }

            //duplicate instances
            $nbInstanceWithParent = 0;
            $instancesNewIds = [];
            /** @var InstanceTable $instanceTable */
            $instanceTable = ($source == Object::SOURCE_COMMON) ? $this->get('instanceTable') : $this->get('instanceCliTable');
            $instances = $instanceTable->getEntityByFields(['anr' => $anr->id], ['parent' => 'ASC']);
            foreach ($instances as $instance) {
                $newInstance = new \MonarcFO\Model\Entity\Instance($instance);
                $newInstance->set('id', null);
                $newInstance->setAnr($newAnr);
                $newInstance->setAsset($assetsNewIds[$instance->asset->id]);
                $newInstance->setObject($objectsNewIds[$instance->object->id]);
                if ($instance->root || $instance->parent) {
                    $nbInstanceWithParent++;
                }
                $newInstance->setRoot(null);
                $newInstance->setParent(null);
                $this->get('instanceCliTable')->save($newInstance,false);
                $instancesNewIds[$instance->id] = $newInstance;
            }
            foreach ($instances as $instance) {
                if ($instance->root || $instance->parent) {
                    $newInstance = $instancesNewIds[$instance->id];
                    if ($instance->root) {
                        $newInstance->setRoot($instancesNewIds[$instance->root->id]);
                    }
                    if ($instance->parent) {
                        $newInstance->setParent($instancesNewIds[$instance->parent->id]);
                    }
                    $this->get('instanceCliTable')->save($newInstance,false);
                }
            }

            //duplicate scales
            $scalesNewIds = [];
            $scaleTable = ($source == Object::SOURCE_COMMON) ? $this->get('scaleTable') : $this->get('scaleCliTable');
            $scales = $scaleTable->getEntityByFields(['anr' => $anr->id]);
            foreach ($scales as $scale) {
                $newScale = new \MonarcFO\Model\Entity\Scale($scale);
                $newScale->set('id', null);
                $newScale->setAnr($newAnr);
                $this->get('scaleCliTable')->save($newScale,false);
                $scalesNewIds[$scale->id] = $newScale;
            }

            //duplicate scales impact types
            $scalesImpactTypesNewIds = [];
            $scaleImpactTypeTable = ($source == Object::SOURCE_COMMON) ? $this->get('scaleImpactTypeTable') : $this->get('scaleImpactTypeCliTable');
            $scalesImpactTypes = $scaleImpactTypeTable->getEntityByFields(['anr' => $anr->id]);
            foreach ($scalesImpactTypes as $scaleImpactType) {
                $newScaleImpactType = new \MonarcFO\Model\Entity\ScaleImpactType($scaleImpactType);
                $newScaleImpactType->set('id', null);
                $newScaleImpactType->setAnr($newAnr);
                $newScaleImpactType->setScale($scalesNewIds[$scaleImpactType->scale->id]);
                $this->get('scaleImpactTypeCliTable')->save($newScaleImpactType,false);
                $scalesImpactTypesNewIds[$scaleImpactType->id] = $newScaleImpactType;
            }

            //duplicate scales comments
            $scaleCommentTable = ($source == Object::SOURCE_COMMON) ? $this->get('scaleCommentTable') : $this->get('scaleCommentCliTable');
            $scalesComments = $scaleCommentTable->getEntityByFields(['anr' => $anr->id]);
            foreach ($scalesComments as $scaleComment) {
                $newScaleComment = new \MonarcFO\Model\Entity\ScaleComment($scaleComment);
                $newScaleComment->set('id', null);
                $newScaleComment->setAnr($newAnr);
                $newScaleComment->setScale($scalesNewIds[$scaleComment->scale->id]);
                if ($scaleComment->scaleImpactType) {
                    $newScaleComment->setScaleImpactType($scalesImpactTypesNewIds[$scaleComment->scaleImpactType->id]);
                }
                $this->get('scaleCommentCliTable')->save($newScaleComment,false);
            }

            //duplicate instances risks
            $instanceRiskTable = ($source == Object::SOURCE_COMMON) ? $this->get('instanceRiskTable') : $this->get('instanceRiskCliTable');
            $instancesRisks = $instanceRiskTable->getEntityByFields(['anr' => $anr->id]);
            $instancesRisksNewIds = [];
            foreach ($instancesRisks as $instanceRisk) {
                $newInstanceRisk = new \MonarcFO\Model\Entity\InstanceRisk($instanceRisk);
                $newInstanceRisk->set('id', null);
                $newInstanceRisk->setAnr($newAnr);
                if ($instanceRisk->amv) {
                    $newInstanceRisk->setAmv($amvsNewIds[$instanceRisk->amv->id]);
                }
                if ($instanceRisk->asset) {
                    $newInstanceRisk->setAsset($assetsNewIds[$instanceRisk->asset->id]);
                }
                if ($instanceRisk->threat) {
                    $newInstanceRisk->setThreat($threatsNewIds[$instanceRisk->threat->id]);
                }
                if ($instanceRisk->vulnerability) {
                    $newInstanceRisk->setVulnerability($vulnerabilitiesNewIds[$instanceRisk->vulnerability->id]);
                }
                if ($instanceRisk->instance) {
                    $newInstanceRisk->setInstance($instancesNewIds[$instanceRisk->instance->id]);
                }
                $this->get('instanceRiskCliTable')->save($newInstanceRisk,false);
                $instancesRisksNewIds[$instanceRisk->id] = $newInstanceRisk;
            }

            //duplicate instances risks op
            $instanceRiskOpTable = ($source == Object::SOURCE_COMMON) ? $this->get('instanceRiskOpTable') : $this->get('instanceRiskOpCliTable');
            $instancesRisksOp = $instanceRiskOpTable->getEntityByFields(['anr' => $anr->id]);
            $instancesRisksOpNewIds = [];
            foreach ($instancesRisksOp as $instanceRiskOp) {
                $newInstanceRiskOp = new \MonarcFO\Model\Entity\InstanceRiskOp($instanceRiskOp);
                $newInstanceRiskOp->set('id', null);
                $newInstanceRiskOp->setAnr($newAnr);
                $newInstanceRiskOp->setInstance($instancesNewIds[$instanceRiskOp->instance->id]);
                $newInstanceRiskOp->setObject($objectsNewIds[$instanceRiskOp->object->id]);
                if ($instanceRiskOp->rolfRisk) {
                    $newInstanceRiskOp->setRolfRisk($rolfRisksNewIds[$instanceRiskOp->rolfRisk->id]);
                }
                $this->get('instanceRiskOpCliTable')->save($newInstanceRiskOp,false);
                $instancesRisksOpNewIds[$instanceRiskOp->id] = $newInstanceRiskOp;
            }

            //duplicate instances consequences
            $instanceConsequenceTable = ($source == Object::SOURCE_COMMON) ? $this->get('instanceConsequenceTable') : $this->get('instanceConsequenceCliTable');
            $instancesConsequences = $instanceConsequenceTable->getEntityByFields(['anr' => $anr->id]);
            foreach ($instancesConsequences as $instanceConsequence) {
                $newInstanceConsequence = new \MonarcFO\Model\Entity\InstanceConsequence($instanceConsequence);
                $newInstanceConsequence->set('id', null);
                $newInstanceConsequence->setAnr($newAnr);
                $newInstanceConsequence->setInstance($instancesNewIds[$instanceConsequence->instance->id]);
                $newInstanceConsequence->setObject($objectsNewIds[$instanceConsequence->object->id]);
                $newInstanceConsequence->setScaleImpactType($scalesImpactTypesNewIds[$instanceConsequence->scaleImpactType->id]);
                $this->get('instanceConsequenceCliTable')->save($newInstanceConsequence,false);
            }

            // duplicate questions & choices
            $questions = ($source == Object::SOURCE_COMMON) ? $this->get('questionTable')->fetchAllObject() : $this->get('questionCliTable')->getEntityByFields(['anr' => $anr->id]);
            $questionsNewIds = [];
            foreach ($questions as $q) {
                $newQuestion = new \MonarcFO\Model\Entity\Question($q);
                $newQuestion->set('id', null);
                $newQuestion->set('anr', $newAnr);
                $this->get('questionCliTable')->save($newQuestion,false);
                $questionsNewIds[$q->id] = $newQuestion;
            }
            $questionChoices = ($source == Object::SOURCE_COMMON) ? $this->get('questionChoiceTable')->fetchAllObject() : $this->get('questionChoiceCliTable')->getEntityByFields(['anr' => $anr->id]);
            foreach ($questionChoices as $qc) {
                $newQuestionChoice = new \MonarcFO\Model\Entity\QuestionChoice($qc);
                $newQuestionChoice->set('id', null);
                $newQuestionChoice->set('anr', $newAnr);
                $newQuestionChoice->set('question', $questionsNewIds[$qc->get('question')->get('id')]);
                $this->get('questionChoiceCliTable')->save($newQuestionChoice,false);
            }

            //duplicate interviews
            $interviews = $this->get('interviewCliTable')->getEntityByFields(['anr' => $anr->id]);
            foreach ($interviews as $interview) {
                $newInterview = new Interview($interview);
                $newInterview->set('id', null);
                $newInterview->setAnr($newAnr);
                $this->get('interviewCliTable')->save($newInterview,false);
            }

            //duplicate recommandations
            $recommandationsNewIds = [];
            $recommandations = $this->get('recommandationCliTable')->getEntityByFields(['anr' => $anr->id]);
            foreach ($recommandations as $recommandation) {
                $newRecommandation = new Recommandation($recommandation);
                $newRecommandation->set('id', null);
                $newRecommandation->setAnr($newAnr);
                $this->get('recommandationCliTable')->save($newRecommandation,false);
                $recommandationsNewIds[$recommandation->id] = $newRecommandation;
            }

            //duplicate recommandations historics
            $recommandationsHistorics = $this->get('recommandationHistoricCliTable')->getEntityByFields(['anr' => $anr->id]);
            foreach ($recommandationsHistorics as $recommandationHistoric) {
                $newRecommandationHistoric = new RecommandationHistoric($recommandationHistoric);
                $newRecommandationHistoric->set('id', null);
                $newRecommandationHistoric->setAnr($newAnr);
                $this->get('recommandationHistoricCliTable')->save($newRecommandationHistoric,false);
            }

            //duplicate recommandations measures
            $recommandationsMeasures = $this->get('recommandationMeasureCliTable')->getEntityByFields(['anr' => $anr->id]);
            foreach ($recommandationsMeasures as $recommandationMeasure) {
                $newRecommandationMeasure = new RecommandationMeasure($recommandationMeasure);
                $newRecommandationMeasure->set('id', null);
                $newRecommandationMeasure->setAnr($newAnr);
                $newRecommandationMeasure->set('measure', $measuresNewIds[$newRecommandationMeasure->get('measure')->get('id')]);
                $this->get('recommandationMeasureCliTable')->save($newRecommandationMeasure,false);
            }

            //duplicate recommandations risks
            $recommandationsRisks = $this->get('recommandationRiskCliTable')->getEntityByFields(['anr' => $anr->id]);
            foreach ($recommandationsRisks as $recommandationRisk) {
                $newRecommandationRisk = new RecommandationRisk($recommandationRisk);
                $newRecommandationRisk->set('id', null);
                $newRecommandationRisk->setAnr($newAnr);
                $newRecommandationRisk->set('recommandation', $recommandationsNewIds[$newRecommandationRisk->get('recommandation')->get('id')]);
                if ($newRecommandationRisk->get('instanceRisk')) {
                    $newRecommandationRisk->set('instanceRisk', $instancesRisksNewIds[$newRecommandationRisk->get('instanceRisk')->get('id')]);
                }
                if ($newRecommandationRisk->get('instanceRiskOp')) {
                    $newRecommandationRisk->set('instanceRiskOp', $instancesRisksOpNewIds[$newRecommandationRisk->get('instanceRiskOp')->get('id')]);
                }
                $newRecommandationRisk->set('instance', $instancesNewIds[$newRecommandationRisk->get('instance')->get('id')]);
                if ($newRecommandationRisk->get('objectGlobal') && isset($objectsNewIds[$newRecommandationRisk->get('objectGlobal')->get('id')])) {
                    $newRecommandationRisk->set('objectGlobal', $objectsNewIds[$newRecommandationRisk->get('objectGlobal')->get('id')]);
                } else {
                    $newRecommandationRisk->set('objectGlobal', null);
                }
                if ($newRecommandationRisk->get('asset')) {
                    $newRecommandationRisk->set('asset', $assetsNewIds[$newRecommandationRisk->get('asset')->get('id')]);
                }
                if ($newRecommandationRisk->get('threat')) {
                    $newRecommandationRisk->set('threat', $threatsNewIds[$newRecommandationRisk->get('threat')->get('id')]);
                }
                if ($newRecommandationRisk->get('vulnerability')) {
                    $newRecommandationRisk->set('vulnerability', $vulnerabilitiesNewIds[$newRecommandationRisk->get('vulnerability')->get('id')]);
                }
                $this->get('recommandationRiskCliTable')->save($newRecommandationRisk,false);
            }

            $this->get('table')->getDb()->flush();

            $this->setUserCurrentAnr($newAnr->get('id'));

        } catch (\Exception $e) {

            if (is_integer($id)) {
                $anrCliTable->delete($id);
            }

            throw new  \Exception('Error during analysis creation', 412);
        }

        return $newAnr->get('id');
    }

    /**
     * Set User Current Anr
     *
     * @param $anrId
     */
    public function setUserCurrentAnr($anrId)
    {
        //retrieve connected user
        /** @var UserTable $userCliTable */
        $userCliTable = $this->get('userCliTable');
        $currentUser = $userCliTable->getConnectedUser();

        //retrieve connected user information
        /** @var User $user */
        $user = $userCliTable->getEntity($currentUser['id']);

        //record last anr to user
        /** @var AnrTable $anrCliTable */
        $anrCliTable = $this->get('anrCliTable');
        $user->set('currentAnr', $anrCliTable->getEntity($anrId));
        $userCliTable->save($user);
    }

    /**
     * Get Parents Category Ids
     *
     * @param $category
     * @param $categoriesIds
     * @return array
     */
    public function getParentsCategoryIds($category, &$categoriesIds)
    {
        if ($category->parent) {
            $categoriesIds[] = $category->parent->id;
            $this->getParentsCategoryIds($category->parent, $categoriesIds);

            return $categoriesIds;
        } else {
            return [];
        }
    }

    /**
     * Get Color
     *
     * @param $anr
     * @param $value
     * @param array $classes
     * @return mixed
     */
    public function getColor($anr, $value, $classes = ['green', 'orange', 'alerte'])
    {
        if ($value <= $anr->get('seuil1')) {
            return $classes[0];
        } else if ($value <= $anr->get('seuil2')) {
            return $classes[1];
        } else {
            return $classes[2];
        }
    }

    /**
     * Export Anr
     *
     * @param $data
     * @return string
     * @throws \Exception
     */
    public function exportAnr(&$data)
    {
        if (empty($data['id'])) {
            throw new \Exception('Anr to export is required', 412);
        }
        if (empty($data['password'])) {
            $data['password'] = '';
        }
        $filename = "";

        $with_eval = isset($data['assessments']) && $data['assessments'];

        $return = $this->generateExportArray($data['id'], $filename, $with_eval);
        $data['filename'] = $filename;

        return base64_encode($this->encrypt(json_encode($return), $data['password']));
    }

    /**
     * Generate Export Array
     *
     * @param $id
     * @param string $filename
     * @param bool $with_eval
     * @return array
     * @throws \Exception
     */
    public function generateExportArray($id, &$filename = "", $with_eval = false)
    {
        if (empty($id)) {
            throw new \Exception('Anr to export is required', 412);
        }
        $entity = $this->get('table')->getEntity($id);

        if (!$entity) {
            throw new \Exception('Entity `id` not found.');
        }

        $filename = preg_replace("/[^a-z0-9\._-]+/i", '', $entity->get('label' . $this->getLanguage()));

        $return = [
            'type' => 'anr',
            'version' => $this->getVersion(),
            'instances' => [],
            'with_eval' => $with_eval,
        ];

        $instanceService = $this->get('instanceService');
        $table = $this->get('instanceCliTable');
        $instances = $table->getEntityByFields(['anr' => $entity->get('id'), 'parent' => null]);
        $f = '';
        $with_scale = false;
        foreach ($instances as $i) {
            $return['instances'][$i->id] = $instanceService->generateExportArray($i->id, $f, $with_eval, $with_scale);
        }

        if ($with_eval) {
            // scales
            $return['scales'] = [];
            $scaleTable = $this->get('scaleCliTable');
            $scales = $scaleTable->getEntityByFields(['anr' => $entity->get('id')]);
            $scalesArray = [
                'min' => 'min',
                'max' => 'max',
                'type' => 'type',
            ];
            foreach ($scales as $s) {
                $return['scales'][$s->type] = $s->getJsonArray($scalesArray);
            }
        }
        return $return;
    }

    /**
     * Delete
     *
     * @param $id
     */
    public function delete($id)
    {
        //retrieve and delete snapshots associated to anr
        $snapshots = $this->get('snapshotCliTable')->getEntityByFields(['anrReference' => $id]);
        foreach ($snapshots as $s) {
            if (!empty($s)) {
                $this->get('table')->delete($s->get('anr')->get('id'), false);
            }
        }

        return $this->get('table')->delete($id);
    }

    /**
     * Verify language
     *
     * @param $modelId
     * @return array
     */
    public function verifyLanguage($modelId)
    {
        $languages = [1, 2, 3, 4];
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

        //themes, measures, rolf categories, rolf tags, rolf risks, object categories, questions and questions choices
        $array = [
            'theme' => 'label',
            'measure' => 'description',
            'rolfCategory' => 'label',
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
                        ${$value}[$entity->get('id')] = $entity->get('id');
                    }
                }
            }
        }

        //objects
        if ($model->get('anr')) {
            $objects = $this->get('objectTable')->fetchAllObject();
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
}