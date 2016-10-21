<?php
namespace MonarcFO\Service;

use MonarcCore\Model\Table\AmvTable;
use MonarcCore\Model\Table\ModelTable;
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
    protected $rolfCategoryTable;
    protected $rolfRiskTable;
    protected $rolfTagTable;
    protected $threatTable;
    protected $vulnerabilityTable;

    protected $amvCliTable;
    protected $assetCliTable;
    protected $measureCliTable;
    protected $objectCliTable;
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
        /** @var RolfCategoryTable $rolfCategoryTable */
        $rolfCategoryTable = $this->get('rolfCategoryTable');
        $rolfCategories = $rolfCategoryTable->fetchAllObject();
        $rolfCategoriesNewIds = [];
        foreach($rolfCategories as $rolfCategory) {
            $last = ($i == count($rolfCategories)) ? true : false;

            $newRolfCategory = clone $rolfCategory;

            $this->get('rolfCategoryCliTable')->save($newRolfCategory, $last);

            $rolfCategoriesNewIds[$rolfCategory->id] = $newRolfCategory;

            $i++;
        }

        //duplicate rolf tags
        $i = 1;
        /** @var RolfTagTable $rolfTagTable */
        $rolfTagTable = $this->get('rolfTagTable');
        $rolfTags = $rolfTagTable->fetchAllObject();
        $rolfTagsNewIds = [];
        foreach($rolfTags as $rolfTag) {
            $last = ($i == count($rolfTags)) ? true : false;

            $newRolfTag = clone $rolfTag;

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

            foreach($rolfRisk->categories as $key => $category) {
                $newRolfRisk->setCategory($key, $rolfCategoriesNewIds[$category->id]);
            }

            foreach($rolfRisk->tags as $key => $tag) {
                $newRolfRisk->setTag($key, $rolfTagsNewIds[$tag->id]);
            }

            $this->get('rolfRiskCliTable')->save($newRolfRisk, $last);

            $i++;
        }

        return $id;
    }
}