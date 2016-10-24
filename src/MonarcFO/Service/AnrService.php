<?php
namespace MonarcFO\Service;

use MonarcCore\Model\Table\AmvTable;
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
    protected $assetTable;
    protected $modelTable;
    protected $measureTable;
    protected $objectTable;
    protected $objectCategoryTable;
    protected $rolfCategoryTable;
    protected $rolfRiskTable;
    protected $rolfTagTable;
    protected $threatTable;
    protected $vulnerabilityTable;

    protected $amvCliTable;
    protected $assetCliTable;
    protected $measureCliTable;
    protected $objectCliTable;
    protected $objectCategoryCliTable;
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
        /** @var ObjectTable $objectTable */
        $objectTable = $this->get('objectTable');
        $objects = $objectTable->fetchAllObject();
        foreach($objects as $object) {
            $last = ($i == count($objects)) ? true : false;

            $newObject = clone $object;
            $newObject->setAnr($newAnr);
            $newObject->setCategory($objectsCategoriesNewIds[$object->category->id]);
            $newObject->setAsset($assetNewIds[$object->asset->id]);
            if ($object->rolfTag) {
                $newObject->setRolfTag($rolfTagsNewIds[$object->rolfTag->id]);
            }

            $this->get('objectCliTable')->save($newObject, $last);

            $objectsNewIds[$object->id] = $newObject;

            $i++;
        }

        return $id;
    }
}