<?php
namespace MonarcFO\Service;

/**
 * Anr Scale Comment Service
 *
 * Class AnrScaleCommentService
 * @package MonarcFO\Service
 */
class AnrScaleCommentService extends \MonarcCore\Service\AbstractService
{
	protected $filterColumns = [];
    protected $anrTable;
    protected $userAnrTable;
    protected $scaleTable;
    protected $scaleImpactTypeTable;
    protected $dependencies = ['anr', 'scale', 'scaleImpactType'];

    /**
     * Create
     *
     * @param $data
     * @param bool $last
     * @return mixed
     */
    public function create($data, $last = true) {

        $class = $this->get('entity');
        $entity = new $class();
        $entity->setLanguage($this->getLanguage());
        $entity->setDbAdapter($this->get('table')->getDb());
        if (isset($data['scale'])) {
            $scale = $this->get('scaleTable')->getEntity($data['scale']);
            $entity->setScale($scale);
            if ($scale->type !=1) {
                unset($data['scaleImpactType']);
            }
        }
        $entity->exchangeArray($data);

        $dependencies =  (property_exists($this, 'dependencies')) ? $this->dependencies : [];
        $this->setDependencies($entity, $dependencies);

        /** @var AnrTable $table */
        $table = $this->get('table');

        return $table->save($entity, $last);
    }
}
