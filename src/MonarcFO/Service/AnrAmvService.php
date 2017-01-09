<?php
namespace MonarcFO\Service;

/**
 * Anr Asset Service
 *
 * Class AnrAmvService
 * @package MonarcFO\Service
 */
class AnrAmvService extends \MonarcCore\Service\AbstractService
{
    protected $anrTable;
    protected $userAnrTable;
    protected $assetTable;
    protected $threatTable;
    protected $vulnerabilityTable;
    protected $measureTable;

	protected $filterColumns = ['status'];
    protected $dependencies = ['anr', 'asset', 'threat', 'vulnerability', 'measure[1]()', 'measure[2]()', 'measure[3]()'];


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
        $filterJoin = array(
            array(
                'as' => 'a',
                'rel' => 'asset',
            ),
            array(
                'as' => 'th',
                'rel' => 'threat',
            ),
            array(
                'as' => 'v',
                'rel' => 'vulnerability',
            ),
        );
        $filterLeft = array(
            array(
                'as' => 'm1',
                'rel' => 'measure1',
            ),
            array(
                'as' => 'm2',
                'rel' => 'measure2',
            ),
            array(
                'as' => 'm3',
                'rel' => 'measure3',
            ),
        );
        $filtersCol = array();
        $filtersCol[] = 'a.code';
        $filtersCol[] = 'a.label1';
        $filtersCol[] = 'a.label2';
        $filtersCol[] = 'a.label3';
        $filtersCol[] = 'a.description1';
        $filtersCol[] = 'a.description2';
        $filtersCol[] = 'a.description3';
        $filtersCol[] = 'th.code';
        $filtersCol[] = 'th.label1';
        $filtersCol[] = 'th.label2';
        $filtersCol[] = 'th.label3';
        $filtersCol[] = 'th.description1';
        $filtersCol[] = 'th.description2';
        $filtersCol[] = 'th.description3';
        $filtersCol[] = 'v.code';
        $filtersCol[] = 'v.label1';
        $filtersCol[] = 'v.label2';
        $filtersCol[] = 'v.label3';
        $filtersCol[] = 'v.description1';
        $filtersCol[] = 'v.description2';
        $filtersCol[] = 'v.description3';
        $filtersCol[] = 'm1.code';
        $filtersCol[] = 'm1.description1';
        $filtersCol[] = 'm1.description2';
        $filtersCol[] = 'm1.description3';
        $filtersCol[] = 'm2.code';
        $filtersCol[] = 'm2.description1';
        $filtersCol[] = 'm2.description2';
        $filtersCol[] = 'm2.description3';
        $filtersCol[] = 'm3.code';
        $filtersCol[] = 'm3.description1';
        $filtersCol[] = 'm3.description2';
        $filtersCol[] = 'm3.description3';

        return $this->get('table')->fetchAllFiltered(
            array_keys($this->get('entity')->getJsonArray()),
            $page,
            $limit,
            $this->parseFrontendOrder($order),
            $this->parseFrontendFilter($filter, $filtersCol),
            $filterAnd,
            $filterJoin,
            $filterLeft
        );
    }

    /**
     * Update
     *
     * @param $id
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    public function update($id,$data){

        $entity = $this->get('table')->getEntity($id);
        if (!$entity) {
            throw new \Exception('Entity does not exist', 412);
        }
        if($entity->get('anr')->get('id') != $data['anr']){
            throw new \Exception('Anr id error', 412);
        }

        $data['asset'] = $entity->get('asset')->get('id'); // on ne permet pas de modifier l'asset

        $this->filterPostFields($data, $entity);

        $entity->setDbAdapter($this->get('table')->getDb());
        $entity->setLanguage($this->getLanguage());

        if (empty($data)) {
            throw new \Exception('Data missing', 412);
        }

        $entity->exchangeArray($data);

        $dependencies =  (property_exists($this, 'dependencies')) ? $this->dependencies : [];
        $this->setDependencies($entity, $dependencies);

        return $this->get('table')->save($entity);
    }

    /**
     * Patch
     *
     * @param $id
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    public function patch($id, $data){

        $entity = $this->get('table')->getEntity($id);
        if (!$entity) {
            throw new \Exception('Entity does not exist', 412);
        }
        if($entity->get('anr')->get('id') != $data['anr']){
            throw new \Exception('Anr id error', 412);
        }

        $data['asset'] = $entity->get('asset')->get('id'); // on ne permet pas de modifier l'asset

        $entity->setLanguage($this->getLanguage());

        foreach ($this->dependencies as $dependency) {
            if (!isset($data[$dependency])) {
                $data[$dependency] = $entity->$dependency->id;
            }
        }

        $entity->exchangeArray($data, true);

        $dependencies =  (property_exists($this, 'dependencies')) ? $this->dependencies : [];
        $this->setDependencies($entity, $dependencies);

        return $this->get('table')->save($entity);
    }
}
