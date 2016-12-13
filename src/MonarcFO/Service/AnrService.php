<?php
namespace MonarcFO\Service;

use MonarcCore\Model\Entity\AnrSuperClass;
use MonarcFO\Model\Table\AnrTable;
use MonarcFO\Model\Table\ModelTable;
use MonarcFO\Model\Table\SnapshotTable;
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
    protected $measureCliTable;
    protected $objectCliTable;
    protected $objectCategoryCliTable;
    protected $objectObjectCliTable;
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
    protected $vulnerabilityCliTable;
    protected $questionCliTable;
    protected $questionChoiceCliTable;

    /**
     * Get List
     *
     * @param int $page
     * @param int $limit
     * @param null $order
     * @param null $filter
     * @return mixed
     */
    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null){
        $userCliTable = $this->get('userCliTable');
        $userArray = $userCliTable->getConnectedUser();
        $anrs = $this->get('userAnrCliTable')->getEntityByFields(['user'=>$userArray['id']]);
        $filterAnd['id'] = [];
        foreach($anrs as $a){
            $filterAnd['id'][$a->get('anr')->get('id')] = $a->get('anr')->get('id');
        }

        /** @var SnapshotTable $snapshotCliTable */
        $snapshotCliTable = $this->get('snapshotCliTable');
        $snapshots = $snapshotCliTable->getEntityByFields(['anr'=>$filterAnd['id']]);
        foreach($snapshots as $snapshot) {
            unset($filterAnd['id'][$snapshot->get('anr')->get('id')]);
        }

        $anrs = $this->get('table')->fetchAllFiltered(
            array_keys($this->get('entity')->getJsonArray()),
            $page,
            $limit,
            $this->parseFrontendOrder($order),
            $this->parseFrontendFilter($filter, $this->filterColumns),
            $filterAnd
        );

        return $anrs;
    }

    /**
     * Get Filtered Count
     *
     * @param null $filter
     * @return int
     */
    public function getFilteredCount($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null) {

        return count($this->getList($page, $limit, $order, $filter, $filterAnd));
    }

    /**
     * Get Anrs
     * @return array|bool
     */
    public function getAnrs() {

        /** @var \MonarcFO\Model\Table\AnrTable $anrCliTable */
        $anrCliTable = $this->get('anrCliTable');
        $anrs = $anrCliTable->fetchAll();

        return $anrs;
    }

    /**
     * Get Entity
     *
     * @param $id
     * @return array
     */
    public function getEntity($id){
        $anr = $this->get('table')->get($id);

        //retrieve snapshot
        /** @var SnapshotTable $snapshotCliTable */
        $snapshotCliTable = $this->get('snapshotCliTable');
        $anrSnapshot = current($snapshotCliTable->getEntityByFields(['anr' => $id]));

        $anr['isSnapshot'] = 0;
        $anr['snapshotParent'] = null;
        if(!empty($anrSnapshot)){ // On est sur un snapshot
            $anr['isSnapshot'] = 1;
            $anr['rwd'] = 0;
            $anr['snapshotParent'] = $anrSnapshot->get('anrReference')->get('id');
        }else{
            $userCliTable = $this->get('userCliTable');
            $userArray = $userCliTable->getConnectedUser();
            $lk = current($this->get('userAnrCliTable')->getEntityByFields(['user'=>$userArray['id'], 'anr'=>$anr['id']]));
            if(empty($lk)){
                throw new \Exception('Restricted ANR', 412);
            }else{
                $anr['rwd'] = $lk->get('rwd');
            }
        }
        return $anr;
    }

    /**
     * Create From Model To Client
     *
     * @param $modelId
     * @return mixed|null
     */
    public function createFromModelToClient($data) {

        //retrieve model information
        /** @var ModelTable $modelTable */
        $modelTable = $this->get('modelTable');
        $model = $modelTable->getEntity($data['model']);
        unset($data['model']);
        if($model->get('status') != \MonarcCore\Model\Entity\AbstractEntity::STATUS_ACTIVE){ // disabled or deleted
            throw new \Exception('Model not found', 412);
        }

        return $this->duplicateAnr($model->anr, Object::SOURCE_COMMON, $model,$data);
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
    public function duplicateAnr($anr, $source = Object::SOURCE_CLIENT, $model = null,$data=[], $isSnapshot = false) {

        if (is_integer($anr)) {
            /** @var AnrTable $anrTable */
            $anrTable = ($source == Object::SOURCE_COMMON) ? $this->get('anrTable') : $this->get('anrCliTable');
            $anr = $anrTable->getEntity($anr);
        }

        if (!$anr instanceof AnrSuperClass) {
            throw new \Exception('Anr missing', 412);
        }
        if(empty($model)){
            $idModel = $anr->get('model');
        }else{
            $idModel = $model->get('id');
        }

        //duplicate anr
        $newAnr = new \MonarcFO\Model\Entity\Anr($anr);
        $newAnr->setId(null);
        $newAnr->setObjects(null);
        $newAnr->exchangeArray($data);
        $newAnr->set('model',$idModel);
        if($isSnapshot){ // Si c'est un snapshot on ajoute le préfixe "[SNAP]"
            for($i=1;$i<=4;$i++){
                $lab = trim($newAnr->get('label'.$i));
                if(!empty($lab)){
                    $newAnr->set('label'.$i,'[SNAP] '.$lab);
                }
            }
        }

        /** @var AnrTable $anrCliTable */
        $anrCliTable = $this->get('anrCliTable');
        $id = $anrCliTable->save($newAnr);

        if(!$isSnapshot){ // inutile si c'est un snapshot
            //add user to anr
            $userCliTable = $this->get('userCliTable');
            $userArray = $userCliTable->getConnectedUser();
            $user = $userCliTable->getEntity($userArray['id']);
            $userAnr = new \MonarcFO\Model\Entity\UserAnr();
            $userAnr->set('id',null);
            $userAnr->setUser($user);
            $userAnr->setAnr($newAnr);
            $userAnr->setRwd(1);
            $this->get('userAnrCliTable')->save($userAnr);
        }

        //duplicate themes
        $i = 1;
        $themesNewIds = [];
        $themes = ($source == Object::SOURCE_COMMON) ? $this->get('themeTable')->fetchAllObject() : $this->get('themeCliTable')->getEntityByFields(['anr' => $anr->id]);
        foreach($themes as $theme) {
            $last = ($i == count($themes)) ? true : false;
            $newTheme = new \MonarcFO\Model\Entity\Theme($theme);
            $newTheme->set('id',null);
            $newTheme->setAnr($newAnr);
            $this->get('themeCliTable')->save($newTheme, $last);
            $themesNewIds[$theme->id] = $newTheme;
            $i++;
        }

        //duplicate assets
        $i = 1;
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
        foreach($assets as $asset) {
            $last = ($i == count($assets)) ? true : false;
            $newAsset = new \MonarcFO\Model\Entity\Asset($asset);
            $newAsset->set('id',null);
            $newAsset->setAnr($newAnr);
            $this->get('assetCliTable')->save($newAsset, $last);
            $assetsNewIds[$asset->id] = $newAsset;
            $i++;
        }

        //duplicate threats
        $i = 1;
        $threatsNewIds = [];
        if ($source == Object::SOURCE_COMMON) {
            $threats1 = [];
            if (!$model->isRegulator) {
                $threats1 = $this->get('threatTable')->getEntityByFields(['mode' => Threat::MODE_GENERIC]);
            }
            $threats2 = [];
            if (!$model->isGeneric) {
                $threats2 = $this->get('threatTable')->getEntityByFields(['mode' => Threat::MODE_SPECIFIC]);
            }
            $threats = $threats1 + $threats2;
        } else {
            $threats = $this->get('threatCliTable')->getEntityByFields(['anr' => $anr->id]);
        }
        foreach($threats as $threat) {
            $last = ($i == count($threats)) ? true : false;
            $newThreat = new \MonarcFO\Model\Entity\Threat($threat);
            $newThreat->set('id',null);
            $newThreat->setAnr($newAnr);
            if ($threat->theme) {
                $newThreat->setTheme($themesNewIds[$threat->theme->id]);
            }
            $this->get('threatCliTable')->save($newThreat, $last);
            $threatsNewIds[$threat->id] = $newThreat;
            $i++;
        }

        //duplicate vulnerabilities
        $i = 1;
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
        foreach($vulnerabilities as $vulnerability) {
            $last = ($i == count($vulnerabilities)) ? true : false;
            $newVulnerability = new \MonarcFO\Model\Entity\Vulnerability($vulnerability);
            $newVulnerability->set('id',null);
            $newVulnerability->setAnr($newAnr);
            $this->get('vulnerabilityCliTable')->save($newVulnerability, $last);
            $vulnerabilitiesNewIds[$vulnerability->id] = $newVulnerability;
            $i++;
        }

        //duplicate measures
        $i = 1;
        $measuresNewIds = [];
        $measures = ($source == Object::SOURCE_COMMON) ? $this->get('measureTable')->fetchAllObject() : $this->get('measureCliTable')->getEntityByFields(['anr' => $anr->id]);
        foreach($measures as $measure) {
            $last = ($i == count($measures)) ? true : false;
            $newMeasure = new \MonarcFO\Model\Entity\Measure($measure);
            $newMeasure->set('id',null);
            $newMeasure->setAnr($newAnr);
            $this->get('measureCliTable')->save($newMeasure, $last);
            $measuresNewIds[$measure->id] = $newMeasure;
            $i++;
        }

        //duplicate amvs
        $i = 1;
        $amvsNewIds = [];
        $amvs = ($source == Object::SOURCE_COMMON) ? $this->get('amvTable')->fetchAllObject() : $this->get('amvCliTable')->getEntityByFields(['anr' => $anr->id]);
        foreach($amvs as $key => $amv) {
            if (
                (!isset($assetsNewIds[$amv->asset->id])) ||
                (!isset($threatsNewIds[$amv->threat->id])) ||
                (!isset($vulnerabilitiesNewIds[$amv->vulnerability->id]))
            ) {
                unset($amvs[$key]);
            }
        }
        foreach($amvs as $amv) {
            $last = ($i == count($amvs)) ? true : false;
            $newAmv = new \MonarcFO\Model\Entity\Amv($amv);
            $newAmv->set('id',null);
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
            $this->get('amvCliTable')->save($newAmv, $last);
            $amvsNewIds[$amv->id] = $newAmv;
            $i++;
        }

        //duplicate rolf categories
        $i = 1;
        $rolfCategoriesNewIds = [];
        $rolfCategories = ($source == Object::SOURCE_COMMON) ? $this->get('rolfCategoryTable')->fetchAllObject() : $this->get('rolfCategoryCliTable')->getEntityByFields(['anr' => $anr->id]);
        foreach($rolfCategories as $rolfCategory) {
            $last = ($i == count($rolfCategories)) ? true : false;
            $newRolfCategory = new \MonarcFO\Model\Entity\RolfCategory($rolfCategory);
            $newRolfCategory->set('id',null);
            $newRolfCategory->setAnr($newAnr);
            $this->get('rolfCategoryCliTable')->save($newRolfCategory, $last);
            $rolfCategoriesNewIds[$rolfCategory->id] = $newRolfCategory;
            $i++;
        }

        //duplicate rolf tags
        $i = 1;
        $rolfTagsNewIds = [];
        $rolfTags = ($source == Object::SOURCE_COMMON) ? $this->get('rolfTagTable')->fetchAllObject() : $this->get('rolfTagCliTable')->getEntityByFields(['anr' => $anr->id]);
        foreach($rolfTags as $rolfTag) {
            $last = ($i == count($rolfTags)) ? true : false;
            $newRolfTag = new \MonarcFO\Model\Entity\RolfTag($rolfTag);
            $newRolfTag->set('id',null);
            $newRolfTag->setAnr($newAnr);
            $this->get('rolfTagCliTable')->save($newRolfTag, $last);
            $rolfTagsNewIds[$rolfTag->id] = $newRolfTag;
            $i++;
        }

        //duplicate rolf risk
        $i = 1;
        $rolfRisksNewIds = [];
        $rolfRisks = ($source == Object::SOURCE_COMMON) ? $this->get('rolfRiskTable')->fetchAllObject() : $this->get('rolfRiskCliTable')->getEntityByFields(['anr' => $anr->id]);
        foreach($rolfRisks as $rolfRisk) {
            $last = ($i == count($rolfRisks)) ? true : false;
            $newRolfRisk = new \MonarcFO\Model\Entity\RolfRisk($rolfRisk);
            $newRolfRisk->set('id',null);
            $newRolfRisk->setAnr($newAnr);
            foreach($rolfRisk->categories as $key => $category) {
                if(!empty($rolfCategoriesNewIds[$category->id])){
                    $newRolfRisk->setCategory($key, $rolfCategoriesNewIds[$category->id]);
                }
            }
            foreach($rolfRisk->tags as $key => $tag) {
                if(!empty($rolfTagsNewIds[$tag->id])){
                    $newRolfRisk->setTag($key, $rolfTagsNewIds[$tag->id]);
                }
            }
            $this->get('rolfRiskCliTable')->save($newRolfRisk, $last);
            $rolfRisksNewIds[$rolfRisk->id] = $newRolfRisk;
            $i++;
        }

        //duplicate objects categories
        $i = 1;
        $objectsCategoriesNewIds = [];
        $objectsCategories = ($source == Object::SOURCE_COMMON) ? $this->get('objectCategoryTable')->fetchAllObject() : $this->get('objectCategoryCliTable')->getEntityByFields(['anr' => $anr->id]);
        foreach($objectsCategories as $objectCategory) {
            $last = ($i == count($objectsCategories)) ? true : false;
            $newObjectCategory = new \MonarcFO\Model\Entity\ObjectCategory($objectCategory);
            $newObjectCategory->set('id',null);
            $newObjectCategory->setAnr($newAnr);
            if ($objectCategory->parent) {
                $newObjectCategory->setParent($objectsCategoriesNewIds[$objectCategory->parent->id]);
            }
            if ($objectCategory->root) {
                $newObjectCategory->setRoot($objectsCategoriesNewIds[$objectCategory->root->id]);
            }
            $this->get('objectCategoryCliTable')->save($newObjectCategory, $last);
            $objectsCategoriesNewIds[$objectCategory->id] = $newObjectCategory;
            $i++;
        }

        //duplicate objects
        $i = 1;
        $objectsNewIds = [];
        $objectsRootCategories = [];
        $objects = ($source == Object::SOURCE_COMMON) ? $this->get('objectTable')->fetchAllObject() : $this->get('objectCliTable')->getEntityByFields(['anr' => $anr->id]);
        foreach($objects as $key => $object) {
            $existInAnr = false;
            foreach($object->anrs as $anrObject) {
                if ($anrObject->id == $anr->id) {
                    $existInAnr = true;
                }
            }
            if (!$existInAnr) {
                unset($objects[$key]);
            }
        }
        foreach($objects as $object) {
            $last = ($i == count($objects)) ? true : false;
            $newObject = new \MonarcFO\Model\Entity\Object($object);
            $newObject->set('id',null);
            $newObject->setAnr($newAnr);
            $newObject->setAnrs(null);
            $newObject->addAnr($newAnr);
            $newObject->setCategory($objectsCategoriesNewIds[$object->category->id]);
            $newObject->setAsset($assetsNewIds[$object->asset->id]);
            if ($object->rolfTag) {
                $newObject->setRolfTag($rolfTagsNewIds[$object->rolfTag->id]);
            }
            $this->get('objectCliTable')->save($newObject, $last);
            $objectsNewIds[$object->id] = $newObject;
            $i++;

            //root category
            $objectCategoryTable = ($source == Object::SOURCE_COMMON) ? $this->get('objectCategoryTable') : $this->get('objectCategoryCliTable');
            $objectCategory = $objectCategoryTable->getEntity($object->category->id);
            $objectsRootCategories[] = ($objectCategory->root) ? $objectCategory->root->id : $objectCategory->id;
        }

        $objectsRootCategories = array_unique($objectsRootCategories);

        //duplicate anrs objects categories
        $i = 1;
        $anrObjectCategoryTable = ($source == Object::SOURCE_COMMON) ? $this->get('anrObjectCategoryTable') : $this->get('anrObjectCategoryCliTable');
        $anrObjectsCategories = $anrObjectCategoryTable->getEntityByFields(['anr' => $anr->id]);
        foreach($anrObjectsCategories as $key => $anrObjectCategory) {
            if (!in_array($anrObjectCategory->category->id, $objectsRootCategories)) {
                unset($anrObjectsCategories[$key]);
            }
        }
        foreach($anrObjectsCategories as $key => $anrObjectCategory) {
            $last = ($i == count($anrObjectsCategories)) ? true : false;
            $newAnrObjectCategory = new \MonarcFO\Model\Entity\AnrObjectCategory($anrObjectCategory);
            $newAnrObjectCategory->set('id',null);
            $newAnrObjectCategory->setAnr($newAnr);
            $newAnrObjectCategory->setCategory($objectsCategoriesNewIds[$anrObjectCategory->category->id]);
            $this->get('anrObjectCategoryCliTable')->save($newAnrObjectCategory, $last);
            $i++;
        }

        //duplicate objects objects
        $i = 1;
        $objectsObjects = ($source == Object::SOURCE_COMMON) ? $this->get('objectObjectTable')->fetchAllObject() : $this->get('objectObjectCliTable')->getEntityByFields(['anr' => $anr->id]);
        foreach($objectsObjects as $key => $objectObject) {
            if (!($objectObject->father && isset($objectsNewIds[$objectObject->father->id]) && $objectObject->child && isset($objectsNewIds[$objectObject->child->id]))) {
                unset($objectsObjects[$key]);
            }
        }
        foreach($objectsObjects as $objectObject) {
            $last = ($i == count($objectsObjects)) ? true : false;
            $newObjectObject = new \MonarcFO\Model\Entity\ObjectObject($objectObject);
            $newObjectObject->set('id',null);
            $newObjectObject->setAnr($newAnr);
            $newObjectObject->setFather($objectsNewIds[$objectObject->father->id]);
            $newObjectObject->setChild($objectsNewIds[$objectObject->child->id]);
            $this->get('objectObjectCliTable')->save($newObjectObject, $last);
            $i++;
        }

        //duplicate instances
        $i = 1;
        $instancesNewIds = [];
        $instanceTable = ($source == Object::SOURCE_COMMON) ? $this->get('instanceTable') : $this->get('instanceCliTable');
        $instances = $instanceTable->getEntityByFields(['anr' => $anr->id]);
        foreach($instances as $instance) {
            $last = ($i == count($instances)) ? true : false;
            $newInstance = new \MonarcFO\Model\Entity\Instance($instance);
            $newInstance->set('id',null);
            $newInstance->setAnr($newAnr);
            $newInstance->setAsset($assetsNewIds[$instance->asset->id]);
            $newInstance->setObject($objectsNewIds[$instance->object->id]);
            if ($instance->root) {
                $newInstance->setRoot($instancesNewIds[$instance->root->id]);
            }
            if ($instance->parent) {
                $newInstance->setParent($instancesNewIds[$instance->parent->id]);
            }
            $this->get('instanceCliTable')->save($newInstance, $last);
            $instancesNewIds[$instance->id] = $newInstance;
            $i++;
        }

        //duplicate scales
        $i = 1;
        $scalesNewIds = [];
        $scaleTable = ($source == Object::SOURCE_COMMON) ? $this->get('scaleTable') : $this->get('scaleCliTable');
        $scales = $scaleTable->getEntityByFields(['anr' => $anr->id]);
        foreach($scales as $scale) {
            $last = ($i == count($scales)) ? true : false;
            $newScale = new \MonarcFO\Model\Entity\Scale($scale);
            $newScale->set('id',null);
            $newScale->setAnr($newAnr);
            $this->get('scaleCliTable')->save($newScale, $last);
            $scalesNewIds[$scale->id] = $newScale;
            $i++;
        }

        //duplicate scales impact types
        $i = 1;
        $scalesImpactTypesNewIds = [];
        $scaleImpactTypeTable = ($source == Object::SOURCE_COMMON) ? $this->get('scaleImpactTypeTable') : $this->get('scaleImpactTypeCliTable');
        $scalesImpactTypes = $scaleImpactTypeTable->getEntityByFields(['anr' => $anr->id]);
        foreach($scalesImpactTypes as $scaleImpactType) {
            $last = ($i == count($scalesImpactTypes)) ? true : false;
            $newScaleImpactType = new \MonarcFO\Model\Entity\ScaleImpactType($scaleImpactType);
            $newScaleImpactType->set('id',null);
            $newScaleImpactType->setAnr($newAnr);
            $newScaleImpactType->setScale($scalesNewIds[$scaleImpactType->scale->id]);
            $this->get('scaleImpactTypeCliTable')->save($newScaleImpactType, $last);
            $scalesImpactTypesNewIds[$scaleImpactType->id] = $newScaleImpactType;
            $i++;
        }

        //duplicate scales comments
        $i = 1;
        $scaleCommentTable = ($source == Object::SOURCE_COMMON) ? $this->get('scaleCommentTable') : $this->get('scaleCommentCliTable');
        $scalesComments = $scaleCommentTable->getEntityByFields(['anr' => $anr->id]);
        foreach($scalesComments as $scaleComment) {
            $last = ($i == count($scalesComments)) ? true : false;
            $newScaleComment = new \MonarcFO\Model\Entity\ScaleComment($scaleComment);
            $newScaleComment->set('id',null);
            $newScaleComment->setAnr($newAnr);
            $newScaleComment->setScale($scalesNewIds[$scaleComment->scale->id]);
            if ($scaleComment->scaleImpactType) {
                $newScaleComment->setScaleImpactType($scalesImpactTypesNewIds[$scaleComment->scaleImpactType->id]);
            }
            $this->get('scaleCommentCliTable')->save($newScaleComment, $last);
            $i++;
        }

        //duplicate instances risks
        $i = 1;
        $instanceRiskTable = ($source == Object::SOURCE_COMMON) ? $this->get('instanceRiskTable') : $this->get('instanceRiskCliTable');
        $instancesRisks = $instanceRiskTable->getEntityByFields(['anr' => $anr->id]);
        foreach($instancesRisks as $instanceRisk) {
            $last = ($i == count($instancesRisks)) ? true : false;
            $newInstanceRisk = new \MonarcFO\Model\Entity\InstanceRisk($instanceRisk);
            $newInstanceRisk->set('id',null);
            $newInstanceRisk->setAnr($newAnr);
            $newInstanceRisk->setAmv($amvsNewIds[$instanceRisk->amv->id]);
            $newInstanceRisk->setAsset($assetsNewIds[$instanceRisk->asset->id]);
            $newInstanceRisk->setThreat($threatsNewIds[$instanceRisk->threat->id]);
            $newInstanceRisk->setVulnerability($vulnerabilitiesNewIds[$instanceRisk->vulnerability->id]);
            $newInstanceRisk->setInstance($instancesNewIds[$instanceRisk->instance->id]);
            $this->get('instanceRiskCliTable')->save($newInstanceRisk, $last);
            $i++;
        }

        //duplicate instances risks op
        $i = 1;
        $instanceRiskOpTable = ($source == Object::SOURCE_COMMON) ? $this->get('instanceRiskOpTable') : $this->get('instanceRiskOpCliTable');
        $instancesRisksOp = $instanceRiskOpTable->getEntityByFields(['anr' => $anr->id]);
        foreach($instancesRisksOp as $instanceRiskOp) {
            $last = ($i == count($instancesRisksOp)) ? true : false;
            $newInstanceRiskOp = new \MonarcFO\Model\Entity\InstanceRiskOp($instanceRiskOp);
            $newInstanceRiskOp->set('id',null);
            $newInstanceRiskOp->setAnr($newAnr);
            $newInstanceRiskOp->setInstance($instancesNewIds[$instanceRiskOp->instance->id]);
            $newInstanceRiskOp->setObject($objectsNewIds[$instanceRiskOp->object->id]);
            $newInstanceRiskOp->setRolfRisk($rolfRisksNewIds[$instanceRiskOp->rolfRisk->id]);
            $this->get('instanceRiskOpCliTable')->save($newInstanceRiskOp, $last);
            $i++;
        }

        //duplicate instances consequences
        $i = 1;
        $instanceConsequenceTable = ($source == Object::SOURCE_COMMON) ? $this->get('instanceConsequenceTable') : $this->get('instanceConsequenceCliTable');
        $instancesConsequences = $instanceConsequenceTable->getEntityByFields(['anr' => $anr->id]);
        foreach($instancesConsequences as $instanceConsequence) {
            $last = ($i == count($instancesConsequences)) ? true : false;
            $newInstanceConsequence = new \MonarcFO\Model\Entity\InstanceConsequence($instanceConsequence);
            $newInstanceConsequence->set('id',null);
            $newInstanceConsequence->setAnr($newAnr);
            $newInstanceConsequence->setInstance($instancesNewIds[$instanceConsequence->instance->id]);
            $newInstanceConsequence->setObject($objectsNewIds[$instanceConsequence->object->id]);
            $newInstanceConsequence->setScaleImpactType($scalesImpactTypesNewIds[$instanceConsequence->scaleImpactType->id]);
            $this->get('instanceConsequenceCliTable')->save($newInstanceConsequence, $last);
            $i++;
        }

        // duplicate questions & choices
        $questions = ($source == Object::SOURCE_COMMON) ? $this->get('questionTable')->fetchAllObject() : $this->get('questionCliTable')->getEntityByFields(['anr' => $anr->id]);
        $questionsNewIds = [];
        $i = 1;
        foreach($questions as $q){
            $newQuestion = new \MonarcFO\Model\Entity\Question($q);
            $newQuestion->set('id',null);
            $newQuestion->set('anr',$newAnr);
            $this->get('questionCliTable')->save($newQuestion, ($i == count($questions)));
            $questionsNewIds[$q->id] = $newQuestion;
            $i++;
        }
        $questionChoices = ($source == Object::SOURCE_COMMON) ? $this->get('questionChoiceTable')->fetchAllObject() : $this->get('questionChoiceCliTable')->getEntityByFields(['anr' => $anr->id]);
        $i = 1;
        foreach($questionChoices as $qc){
            $newQuestionChoice = new \MonarcFO\Model\Entity\QuestionChoice($qc);
            $newQuestionChoice->set('id',null);
            $newQuestionChoice->set('anr',$newAnr);
            $newQuestionChoice->set('question',$questionsNewIds[$qc->get('question')->get('id')]);
            $this->get('questionChoiceCliTable')->save($newQuestionChoice, ($i == count($questionChoices)));
            $i++;
        }

        return $id;
    }
}