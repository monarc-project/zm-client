<?php
namespace MonarcFO\Service;

use MonarcCore\Model\Table\AmvTable;
use MonarcCore\Model\Table\ModelTable;
use MonarcCore\Service\AbstractService;

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
    protected $threatTable;
    protected $vulnerabilityTable;

    protected $amvCliTable;
    protected $assetCliTable;
    protected $measureCliTable;
    protected $objectCliTable;
    protected $threatCliTable;
    protected $vulnerabilityCliTable;


    public function createFromModelToClient($modelId) {

        //retrieve model information
        /** @var ModelTable $modeltable */
        $modelTable = $this->get('modelTable');
        $model = $modelTable->getEntity($modelId);
        if (!$model) {
            throw new \Exception('Model not exist', 412);
        }

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
            $newAmv->setVulnerability($vulnerabilityNewIds[$amv->threat->id]);

            $this->get('amvCliTable')->save($newAmv, $last);

            $i++;
        }


        return $id;
    }
}