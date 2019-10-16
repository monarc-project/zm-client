<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractService;
use Monarc\FrontOffice\Model\Table\RecommandationTable;
use Monarc\FrontOffice\Model\Table\RecommandationSetTable;

/**
 * This class is the service that handles recommendations within an ANR.
 * @package Monarc\FrontOffice\Service
 */
class AnrRecommandationService extends AbstractService
{
    protected $filterColumns = ['code', 'description'];
    protected $dependencies = ['anr', 'recommandationSet'];
    protected $anrTable;
    protected $userAnrTable;

    /**
     * @inheritdoc
     */
    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null)
    {
        list($filterJoin,$filterLeft,$filtersCol) = $this->get('entity')->getFiltersForService();
        $recos = $this->get('table')->fetchAllFiltered(
            array_keys($this->get('entity')->getJsonArray()),
            $page,
            $limit,
            $this->parseFrontendOrder($order),
            $this->parseFrontendFilter($filter, $this->filterColumns),
            $filterAnd,
            $filterJoin,
            $filterLeft
        );

        foreach ($recos as $key => $reco) {
            $recos[$key]['timerColor'] = $this->getDueDateColor($reco['duedate']);
            $recos[$key]['counterTreated'] = ($reco['counterTreated'] == 0) ? 'COMING' : '_SMILE_IN_PROGRESS (<span>' . $reco['counterTreated'] . '</span>)';
        }
        return $recos;
    }

    /**
     * @inheritdoc
     */
    public function create($data, $last = true)
    {
        $class = $this->get('entity');
        $entity = new $class();
        $entity->setLanguage($this->getLanguage());
        $entity->setDbAdapter($this->get('table')->getDb());
        $entity->anr = $data['anr'];

        $dependencies = (property_exists($this, 'dependencies')) ? $this->dependencies : [];
        $this->setDependencies($entity, $dependencies);

        $data['position'] = null;
        $entity->exchangeArray($data);

        $dependencies = (property_exists($this, 'dependencies')) ? $this->dependencies : [];
        $this->setDependencies($entity, $dependencies);

        /** @var RecommandationTable $table */
        $table = $this->get('table');

        return $table->save($entity, $last);
    }

    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        if (!empty($data['duedate'])) {
            try {
                $data['duedate'] = new \DateTime($data['duedate']);
            } catch (Exception $e) {
                throw new \Monarc\Core\Exception\Exception('Invalid date format', 412);
            }
        }elseif(isset($data['duedate'])){
            $data['duedate'] = null;
        }

        if(empty($data['recommandationSet'])){
            $data['recommandationSet'] = $this->get('table')->getEntity($id)->get('recommandationSet');
        }

        $this->updateRecoPosition($id, $data);
        parent::patch($id, $data);
    }

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        if (!empty($data['duedate'])) {
            try {
                $data['duedate'] = new \DateTime($data['duedate']);
            } catch (Exception $e) {
                throw new \Monarc\Core\Exception\Exception('Invalid date format', 412);
            }
        }elseif(isset($data['duedate'])){
            $data['duedate'] = null;
        }

        if(empty($data['recommandationSet'])){
            $data['recommandationSet'] = $this->get('table')->getEntity($id)->get('recommandationSet');
        }

        $this->updateRecoPosition($id, $data);
        parent::update($id, $data);
    }

    /**
     * Updates the position of the recommendation, based on the implicitPosition field passed in $data.
     * @param int $id The recommendation composite ID [anr, uuid]
     * @param array $data The positionning data (implicitPosition field, and previous)
     */
    public function updateRecoPosition($id, &$data){
        if(!empty($data['implicitPosition'])){
            $entity = $this->get('table')->getEntity($id);
            if($entity->get('position') > 0){
                switch ($data['implicitPosition']) {
                    case \Monarc\Core\Model\Entity\AbstractEntity::IMP_POS_START:
                        $data['position'] = 1;
                        $bros = $this->get('table')->getRepository()->createQueryBuilder('bro')
                            ->select()
                            ->where('bro.anr = :anrid')
                            ->setParameter(':anrid', $entity->get('anr')->get('id'))
                            ->andWhere('bro.uuid != :uuid')
                            ->setParameter(':uuid', $entity->get('uuid'))
                            ->andWhere('bro.position <= :pos')
                            ->setParameter(':pos', $entity->get('position'))
                            ->andWhere('bro.position IS NOT NULL')
                            ->getQuery()
                            ->getResult();
                        foreach($bros as $b){
                            $b->set('position',$b->get('position')+1);
                            $this->get('table')->save($b,false);
                        }
                        break;
                    case \Monarc\Core\Model\Entity\AbstractEntity::IMP_POS_END:
                        $pos = $this->get('table')->getRepository()->createQueryBuilder('bro')
                            ->select('MAX(bro.position)')
                            ->where('bro.anr = :anrid')
                            ->setParameter(':anrid', $entity->get('anr')->get('id'))
                            ->andWhere('bro.position IS NOT NULL')
                            ->getQuery()->getSingleScalarResult();
                        $data['position'] = $pos;
                        $bros = $this->get('table')->getRepository()->createQueryBuilder('bro')
                            ->select()
                            ->where('bro.anr = :anrid')
                            ->setParameter(':anrid', $entity->get('anr')->get('id'))
                            ->andWhere('bro.uuid != :uuid')
                            ->setParameter(':uuid', $entity->get('uuid'))
                            ->andWhere('bro.position >= :pos')
                            ->setParameter(':pos', $entity->get('position'))
                            ->andWhere('bro.position IS NOT NULL')
                            ->getQuery()
                            ->getResult();
                        foreach($bros as $b){
                            $b->set('position',$b->get('position')-1);
                            $this->get('table')->save($b,false);
                        }
                        break;
                    case \Monarc\Core\Model\Entity\AbstractEntity::IMP_POS_AFTER:
                        if(!empty($data['previous'])){
                            $prev = $this->get('table')->getEntity(['anr' => $entity->get('anr')->get('id'), 'uuid'=> $data['previous']]);
                            if($prev && $prev->get('position') > 0 && $prev->get('anr')->get('id') == $entity->get('anr')->get('id')){
                                $data['position'] = $prev->get('position')+($entity->get('position') > $prev->get('position')?1:0);
                                $bros = $this->get('table')->getRepository()->createQueryBuilder('bro')
                                    ->select()
                                    ->where('bro.anr = :anrid')
                                    ->setParameter(':anrid', $entity->get('anr')->get('id'))
                                    ->andWhere('bro.uuid != :uuid')
                                    ->setParameter(':uuid', $entity->get('uuid'))
                                    ->andWhere('bro.position '.($entity->get('position')>$data['position']?'>':'<').'= :pos1')
                                    ->setParameter(':pos1', $data['position'])
                                    ->andWhere('bro.position '.($entity->get('position')>$data['position']?'<':'>').' :pos2')
                                    ->setParameter(':pos2', $entity->get('position'))
                                    ->andWhere('bro.position IS NOT NULL')
                                    ->getQuery()
                                    ->getResult();
                                $val = $entity->get('position') > $data['position'] ? 1 : -1;
                                foreach($bros as $b){
                                    $b->set('position',$b->get('position')+$val);
                                    $this->get('table')->save($b,false);
                                }
                            }
                        }
                        break;
                    default:
                        break;
                }
            }
        }
        unset($data['implicitPosition']);
        unset($data['previous']);
    }


    /**
     * Computes the due date color for the recommendation. Returns 'no-date' if no due date is set on the
     * recommendation, 'large' if there's a lot of time remaining, 'warning' if there is less than 15 days remaining,
     * and 'alert' if the due date is in the past.
     * @param string $dueDate The due date, in yyyy-mm-dd format
     * @return string 'no-date', 'large', 'warning', 'alert'
     */
    protected function getDueDateColor($dueDate)
    {
        if (empty($dueDate) || $dueDate == '0000-00-00') {
            return 'no-date';
        } else {
            $now = time();
            if ($dueDate instanceof \DateTime) {
                $dueDate = $dueDate->getTimestamp();
            } else {
                $dueDate = strtotime($dueDate);
            }
            $diff = $dueDate - $now;

            if ($diff < 0) {
                return "alert";
            } else {
                $days = round($diff / 60 / 60 / 24);
                if ($days <= 15) {//arbitrary 15 days
                    return "warning";
                } else {
                    return "large";
                }
            }
        }
    }
}