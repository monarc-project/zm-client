<?php
namespace MonarcFO\Service;

use MonarcCore\Model\Table\AmvTable;
use MonarcCore\Model\Table\AnrObjectCategoryTable;
use MonarcCore\Model\Table\AssetTable;
use MonarcCore\Model\Table\InstanceConsequenceTable;
use MonarcCore\Model\Table\InstanceRiskOpTable;
use MonarcCore\Model\Table\InstanceRiskTable;
use MonarcCore\Model\Table\InstanceTable;
use MonarcCore\Model\Table\ModelTable;
use MonarcCore\Model\Table\ObjectCategoryTable;
use MonarcCore\Model\Table\ObjectTable;
use MonarcCore\Model\Table\RolfRiskTable;
use MonarcCore\Model\Table\RolfTagTable;
use MonarcCore\Model\Table\ScaleCommentTable;
use MonarcCore\Model\Table\ScaleImpactTypeTable;
use MonarcCore\Model\Table\ScaleTable;
use MonarcCore\Model\Table\ThemeTable;
use MonarcCore\Service\AbstractService;
use MonarcCore\Model\Table\RolfCategoryTable;

/**
 * Anr Service
 *
 * Class AnrService
 * @package MonarcFO\Service
 */
class AnrService extends AbstractService
{
    protected $amvTable;
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

    protected $amvCliTable;
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
    protected $threatCliTable;
    protected $themeCliTable;
    protected $vulnerabilityCliTable;


    public function createFromModelToClient($modelId) {

        //retrieve model information
        /** @var ModelTable $modelTable */
        $modelTable = $this->get('modelTable');
        $model = $modelTable->getEntity($modelId);

        $anr = $model->anr;

        //duplicate anr
        $newAnr = clone $anr;
        $newAnr->setId(null);
        $newAnr->setObjects(null);
        /** @var \MonarcFO\Model\Table\AnrTable $anrCliTable */
        $anrCliTable = $this->get('cliTable');
        $id = $anrCliTable->save($newAnr);

        //duplicate themes
        $i = 1;
        $themesNewIds = [];
        /** @var ThemeTable $themeTable */
        $themeTable = $this->get('themeTable');
        $themes = $themeTable->fetchAllObject();
        foreach($themes as $theme) {
            $last = ($i == count($themes)) ? true : false;
            $newTheme = clone $theme;
            $newTheme->setAnr($newAnr);
            $this->get('themeCliTable')->save($newTheme, $last);
            $themesNewIds[$theme->id] = $newTheme;
            $i++;
        }

        //duplicate assets, threats, vulnerabilities ans measures
        $array = ['asset', 'threat', 'vulnerability', 'measure'];
        foreach($array as $value) {
            $i = 1;
            $arrayNewIdsName = $value . 'NewIds';
            ${$arrayNewIdsName} = [];
            $entities = $this->get($value . 'Table')->fetchAllObject();
            foreach ($entities as $entity) {
                $last = ($i == count($entities)) ? true : false;
                $newEntity = clone $entity;
                $newEntity->setAnr($newAnr);
                $newEntity->setModels(null);
                if ($value == 'threat') {
                    if ($entity->theme) {
                        $newEntity->setTheme($themesNewIds[$entity->theme->id]);
                    }
                }
                $this->get($value . 'CliTable')->save($newEntity, $last);
                ${$arrayNewIdsName}[$entity->id] = $newEntity;
                $i++;
            }
        }

        //duplicate amvs
        $i = 1;
        $amvsNewIds = [];
        /** @var AmvTable $amvTable */
        $amvTable = $this->get('amvTable');
        $amvs = $amvTable->fetchAllObject();
        foreach($amvs as $amv) {
            $last = ($i == count($amvs)) ? true : false;
            $newAmv = clone $amv;
            $newAmv->setAnr($newAnr);
            $newAmv->setAsset($assetNewIds[$amv->asset->id]);
            $newAmv->setThreat($threatNewIds[$amv->threat->id]);
            $newAmv->setVulnerability($vulnerabilityNewIds[$amv->vulnerability->id]);
            $this->get('amvCliTable')->save($newAmv, $last);
            $amvsNewIds[$amv->id] = $newAmv;
            $i++;
        }

        //duplicate rolf categories
        $i = 1;
        $rolfCategoriesNewIds = [];
        /** @var RolfCategoryTable $rolfCategoryTable */
        $rolfCategoryTable = $this->get('rolfCategoryTable');
        $rolfCategories = $rolfCategoryTable->fetchAllObject();
        foreach($rolfCategories as $rolfCategory) {
            $last = ($i == count($rolfCategories)) ? true : false;
            $newRolfCategory = clone $rolfCategory;
            $newRolfCategory->setAnr($newAnr);
            $this->get('rolfCategoryCliTable')->save($newRolfCategory, $last);
            $rolfCategoriesNewIds[$rolfCategory->id] = $newRolfCategory;
            $i++;
        }

        //duplicate rolf tags
        $i = 1;
        $rolfTagsNewIds = [];
        /** @var RolfTagTable $rolfTagTable */
        $rolfTagTable = $this->get('rolfTagTable');
        $rolfTags = $rolfTagTable->fetchAllObject();
        foreach($rolfTags as $rolfTag) {
            $last = ($i == count($rolfTags)) ? true : false;
            $newRolfTag = clone $rolfTag;
            $newRolfTag->setAnr($newAnr);
            $this->get('rolfTagCliTable')->save($newRolfTag, $last);
            $rolfTagsNewIds[$rolfTag->id] = $newRolfTag;
            $i++;
        }

        //duplicate rolf risk
        $i = 1;
        $rolfRisksNewIds = [];
        /** @var RolfRiskTable $rolfRiskTable */
        $rolfRiskTable = $this->get('rolfRiskTable');
        $rolfRisks = $rolfRiskTable->fetchAllObject();
        foreach($rolfRisks as $rolfRisk) {
            $last = ($i == count($rolfRisks)) ? true : false;
            $newRolfRisk = clone $rolfRisk;
            $newRolfRisk->setAnr($newAnr);
            foreach($rolfRisk->categories as $key => $category) {
                $newRolfRisk->setCategory($key, $rolfCategoriesNewIds[$category->id]);
            }
            foreach($rolfRisk->tags as $key => $tag) {
                $newRolfRisk->setTag($key, $rolfTagsNewIds[$tag->id]);
            }
            $this->get('rolfRiskCliTable')->save($newRolfRisk, $last);
            $rolfRisksNewIds[$rolfRisk->id] = $newRolfRisk;
            $i++;
        }

        //duplicate objects categories
        $i = 1;
        $objectsCategoriesNewIds = [];
        /** @var ObjectCategoryTable $objectCategoryTable */
        $objectCategoryTable = $this->get('objectCategoryTable');
        $objectsCategories = $objectCategoryTable->fetchAllObject();
        foreach($objectsCategories as $objectCategory) {
            $last = ($i == count($objectsCategories)) ? true : false;
            $newObjectCategory = clone $objectCategory;
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
        /** @var ObjectTable $objectTable */
        $objectTable = $this->get('objectTable');
        $objects = $objectTable->fetchAllObject();
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
            $newObject = clone $object;
            $newObject->setAnr($newAnr);
            $newObject->setAnrs(null);
            $newObject->addAnr($newAnr);
            $newObject->setCategory($objectsCategoriesNewIds[$object->category->id]);
            $newObject->setAsset($assetNewIds[$object->asset->id]);
            if ($object->rolfTag) {
                $newObject->setRolfTag($rolfTagsNewIds[$object->rolfTag->id]);
            }
            $newObject->setModel(null);
            $this->get('objectCliTable')->save($newObject, $last);
            $objectsNewIds[$object->id] = $newObject;
            $i++;

            //root category
            $objectCategory = $objectCategoryTable->getEntity($object->category->id);
            $objectsRootCategories[] = ($objectCategory->root) ? $objectCategory->root->id : $objectCategory->id;
        }

        $objectsRootCategories = array_unique($objectsRootCategories);

        //duplicate anrs objects categories
        $i = 1;
        /** @var AnrObjectCategoryTable $anrObjectCategoryTable */
        $anrObjectCategoryTable = $this->get('anrObjectCategoryTable');
        $anrObjectsCategories = $anrObjectCategoryTable->getEntityByFields(['anr' => $anr->id]);
        foreach($anrObjectsCategories as $key => $anrObjectCategory) {
            if (!in_array($anrObjectCategory->category->id, $objectsRootCategories)) {
                unset($anrObjectsCategories[$key]);
            }
        }
        foreach($anrObjectsCategories as $key => $anrObjectCategory) {
            $last = ($i == count($anrObjectsCategories)) ? true : false;
            $newAnrObjectCategory = clone $anrObjectCategory;
            $newAnrObjectCategory->setAnr($newAnr);
            $newAnrObjectCategory->setCategory($objectsCategoriesNewIds[$anrObjectCategory->category->id]);
            $this->get('anrObjectCategoryCliTable')->save($newAnrObjectCategory, $last);
            $i++;
        }

        //duplicate objects objects
        $i = 1;
        /** @var ObjectTable $objectTable */
        $objectObjectTable = $this->get('objectObjectTable');
        $objectsObjects = $objectObjectTable->fetchAllObject();
        foreach($objectsObjects as $key => $objectObject) {
            $relationInAnr = true;
            if ((!isset($objectsNewIds[$objectObject->father->id])) || (!isset($objectsNewIds[$objectObject->child->id]))) {
                $relationInAnr = false;
            }
            if (!$relationInAnr) {
                unset($objectsObjects[$key]);
            }
        }
        foreach($objectsObjects as $objectObject) {
            $last = ($i == count($objectsObjects)) ? true : false;
            $newObjectObject = clone $objectObject;
            $newObjectObject->setAnr($newAnr);
            $newObjectObject->setFather($objectsNewIds[$objectObject->father->id]);
            $newObjectObject->setChild($objectsNewIds[$objectObject->child->id]);
            $this->get('objectObjectCliTable')->save($newObjectObject, $last);
            $i++;
        }

        //duplicate instances
        $i = 1;
        $instancesNewIds = [];
        /** @var InstanceTable $instanceTable */
        $instanceTable = $this->get('instanceTable');
        $instances = $instanceTable->getEntityByFields(['anr' => $anr->id]);
        foreach($instances as $instance) {
            $last = ($i == count($instances)) ? true : false;
            $newInstance = clone $instance;
            $newInstance->setAnr($newAnr);
            $newInstance->setAsset($assetNewIds[$instance->asset->id]);
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
        /** @var ScaleTable $scaleTable */
        $scaleTable = $this->get('scaleTable');
        $scales = $scaleTable->getEntityByFields(['anr' => $anr->id]);
        foreach($scales as $scale) {
            $last = ($i == count($scales)) ? true : false;
            $newScale = clone $scale;
            $newScale->setAnr($newAnr);
            $this->get('scaleCliTable')->save($newScale, $last);
            $scalesNewIds[$scale->id] = $newScale;
            $i++;
        }

        //duplicate scales impact types
        $i = 1;
        $scalesImpactTypesNewIds = [];
        /** @var ScaleImpactTypeTable $scaleImpactTypeTable */
        $scaleImpactTypeTable = $this->get('scaleImpactTypeTable');
        $scalesImpactTypes = $scaleImpactTypeTable->getEntityByFields(['anr' => $anr->id]);
        foreach($scalesImpactTypes as $scaleImpactType) {
            $last = ($i == count($scalesImpactTypes)) ? true : false;
            $newScaleImpactType = clone $scaleImpactType;
            $newScaleImpactType->setAnr($newAnr);
            $newScaleImpactType->setScale($scalesNewIds[$scaleImpactType->scale->id]);
            $this->get('scaleImpactTypeCliTable')->save($newScaleImpactType, $last);
            $scalesImpactTypesNewIds[$scaleImpactType->id] = $newScaleImpactType;
            $i++;
        }

        //duplicate scales comments
        $i = 1;
        /** @var ScaleCommentTable $scaleCommentTable */
        $scaleCommentTable = $this->get('scaleCommentTable');
        $scalesComments = $scaleCommentTable->getEntityByFields(['anr' => $anr->id]);
        foreach($scalesComments as $scaleComment) {
            $last = ($i == count($scalesComments)) ? true : false;
            $newScaleComment = clone $scaleComment;
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
        /** @var InstanceRiskTable $instanceRiskTable */
        $instanceRiskTable = $this->get('instanceRiskTable');
        $instancesRisks = $instanceRiskTable->getEntityByFields(['anr' => $anr->id]);
        foreach($instancesRisks as $instanceRisk) {
            $last = ($i == count($instancesRisks)) ? true : false;
            $newInstanceRisk = clone $instanceRisk;
            $newInstanceRisk->setAnr($newAnr);
            $newInstanceRisk->setAmv($amvsNewIds[$instanceRisk->amv->id]);
            $newInstanceRisk->setAsset($assetNewIds[$instanceRisk->asset->id]);
            $newInstanceRisk->setThreat($threatNewIds[$instanceRisk->threat->id]);
            $newInstanceRisk->setVulnerability($vulnerabilityNewIds[$instanceRisk->vulnerability->id]);
            $this->get('instanceRiskCliTable')->save($newInstanceRisk, $last);
            $i++;
        }

        //duplicate instances risks op
        $i = 1;
        /** @var InstanceRiskOpTable $instanceRiskOpTable */
        $instanceRiskOpTable = $this->get('instanceRiskOpTable');
        $instancesRisksOp = $instanceRiskOpTable->getEntityByFields(['anr' => $anr->id]);
        foreach($instancesRisksOp as $instanceRiskOp) {
            $last = ($i == count($instancesRisksOp)) ? true : false;
            $newInstanceRiskOp = clone $instanceRiskOp;
            $newInstanceRiskOp->setAnr($newAnr);
            $newInstanceRiskOp->setInstance($instancesNewIds[$instanceRiskOp->instance->id]);
            $newInstanceRiskOp->setObject($objectsNewIds[$instanceRiskOp->object->id]);
            $newInstanceRiskOp->setRolfRisk($rolfRisksNewIds[$instanceRiskOp->rolfRisk->id]);
            $this->get('instanceRiskOpCliTable')->save($newInstanceRiskOp, $last);
            $i++;
        }

        //duplicate instances consequences
        $i = 1;
        /** @var InstanceConsequenceTable $instanceConsequenceTable */
        $instanceConsequenceTable = $this->get('instanceConsequenceTable');
        $instancesConsequences = $instanceConsequenceTable->getEntityByFields(['anr' => $anr->id]);
        foreach($instancesConsequences as $instanceConsequence) {
            $last = ($i == count($instancesConsequences)) ? true : false;
            $newInstanceConsequence = clone $instanceConsequence;
            $newInstanceConsequence->setAnr($newAnr);
            $newInstanceConsequence->setInstance($instancesNewIds[$instanceConsequence->instance->id]);
            $newInstanceConsequence->setObject($objectsNewIds[$instanceConsequence->object->id]);
            $newInstanceConsequence->setScaleImpactType($scalesImpactTypesNewIds[$instanceConsequence->scaleImpactType->id]);
            $this->get('instanceConsequenceCliTable')->save($newInstanceConsequence, $last);
            $i++;
        }

        return $id;
    }
}