<?php
namespace MonarcFO\Service;

/**
 * Anr Asset Common Service
 *
 * Class AnrAssetCommonService
 * @package MonarcFO\Service
 */
class AnrAssetCommonService extends \MonarcCore\Service\AbstractService
{
	protected $anrTable;
	protected $amvTable;
	protected $clientity;
	protected $clitable;

	public function getListAssets($anrId){
		$anr = $this->get('anrTable')->getEntity($anrId);
		if($anr){
			$assets = $this->get('table')->getRepository()->createQueryBuilder('a')
				->innerJoin('a.models', 'm')
				->where('m.id = :mid')
				->setParameter(':mid',$anr->get('model'))
				->orderBy('a.code', 'ASC')
				->getQuery()->getResult();
			$return = [];
			$aObj = [
				'id',
				'label'.$anr->get('language'),
				'description'.$anr->get('language'),
				'status',
				'mode',
				'type',
				'code',
			];
			foreach($assets as $a){
				$return[] = $a->getJsonArray($aObj);
			}
        	return $return;
		}else{
			throw new \Exception('Anr does not exist', 412);
		}
    }

    public function getAsset($anrId, $assetId){
    	$anr = $this->get('anrTable')->getEntity($anrId);
		if($anr){
			$asset = $this->get('table')->getRepository()->createQueryBuilder('a')
				->innerJoin('a.models', 'm')
				->where('m.id = :mid')
				->setParameter(':mid',$anr->get('model'))
				->setFirstResult(0)->setMaxResults(1)
				->getQuery()->getResult();
			if($asset){
				$asset = current($asset);
				$return = $asset->getJsonArray([
					'id',
					'label'.$anr->get('language'),
					'description'.$anr->get('language'),
					'status',
					'mode',
					'type',
					'code',
				]);
				$return['amvs'] = [];
				$amvs = $this->get('amvTable')->getRepository()->createQueryBuilder('t')
					->where('t.asset = :aid')
					->setParameter(':aid',$return['id']) // add orders
					->getQuery()->getResult();
				foreach($amvs as $amv){
					$amvArray = [
						'threat' => [
							'code' => $amv->get('threat')->get('code'),
							'label'.$anr->get('language') => $amv->get('threat')->get('label'.$anr->get('language')),
						],
						'vulnerability' => [
							'code' => $amv->get('vulnerability')->get('code'),
							'label'.$anr->get('language') => $amv->get('vulnerability')->get('label'.$anr->get('language')),
						],
					];
					for($i = 1; $i <= 3; $i++){
						$amvArray['measure'.$i] = [
							'code' => null,
							'description'.$anr->get('language') => null,
						];
						if($amv->get('measure'.$i)){
							$amvArray['measure'.$i] = [
								'code' => $amv->get('measure'.$i)->get('code'),
								'description'.$anr->get('language') => $amv->get('measure'.$i)->get('description'.$anr->get('language')),
							];
						}
						$return['amvs'][] = $amvArray;
					}
				}
				return $return;
			}else{
				throw new \Exception('Asset does not exist', 412);
			}
		}else{
			throw new \Exception('Anr does not exist', 412);
		}
    }

    public function importAsset($anrId, $assetId){
    	$anr = $this->get('anrTable')->getEntity($anrId);
		if($anr){
			$asset = $this->get('table')->getRepository()->createQueryBuilder('a')
				->innerJoin('a.models', 'm')
				->where('m.id = :mid')
				->setParameter(':mid',$anr->get('model'))
				->setFirstResult(0)->setMaxResults(1)
				->getQuery()->getResult();
			if($asset){
				$asset = current($asset);
			}else{
				throw new \Exception('Asset does not exist', 412);
			}
		}else{
			throw new \Exception('Anr does not exist', 412);
		}
    }
}
