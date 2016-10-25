<?php
namespace MonarcFO\Service;

use MonarcCore\Model\Table\AmvTable;
use MonarcCore\Model\Table\AnrObjectCategoryTable;
use MonarcCore\Model\Table\AssetTable;
use MonarcCore\Model\Table\ModelTable;
use MonarcCore\Model\Table\ObjectCategoryTable;
use MonarcCore\Model\Table\ObjectTable;
use MonarcCore\Model\Table\RolfRiskTable;
use MonarcCore\Model\Table\RolfTagTable;
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
    protected $modelTable;
    protected $measureTable;
    protected $objectTable;
    protected $objectCategoryTable;
    protected $objectObjectTable;
    protected $rolfCategoryTable;
    protected $rolfRiskTable;
    protected $rolfTagTable;
    protected $threatTable;
    protected $vulnerabilityTable;

    protected $amvCliTable;
    protected $anrObjectCategoryCliTable;
    protected $assetCliTable;
    protected $measureCliTable;
    protected $objectCliTable;
    protected $objectCategoryCliTable;
    protected $objectObjectCliTable;
    protected $rolfCategoryCliTable;
    protected $rolfRiskCliTable;
    protected $rolfTagCliTable;
    protected $threatCliTable;
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
                $this->get($value . 'CliTable')->save($newEntity, $last);
                ${$arrayNewIdsName}[$entity->id] = $newEntity;
                $i++;
            }
        }

        //duplicate amvs
        $i = 1;
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

        return $id;
    }
}