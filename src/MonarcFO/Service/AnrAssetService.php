<?php
namespace MonarcFO\Service;

/**
 * Anr Asset Service
 *
 * Class AnrAssetService
 * @package MonarcFO\Service
 */
class AnrAssetService extends \MonarcCore\Service\AbstractService
{
	protected $anrTable;

    protected $amvTable;
    protected $amvEntity;
    protected $threatTable;
    protected $threatEntity;
    protected $themeTable;
    protected $themeEntity;
    protected $vulnerabilityTable;
    protected $vulnerabilityEntity;
    protected $measureTable;
    protected $measureEntity;


	protected $filterColumns = [
        'label1', 'label2', 'label3', 'label4',
        'description1', 'description2', 'description3', 'description4',
        'code',
    ];

    protected $dependencies = ['anr'];

    public function importFromFile($anrId,$data){
    	// password ?
        $key = empty($data['password'])?'':$data['password'];
        $data = $this->decrypt($data,$key);

        $anr = $this->get('anrTable')->getEntity($anrId); // on a une erreur si inconnue

        /*
        TODO:
        - lors de la création: tester le code utilisé
        */

        if(isset($data['type']) && $data['type'] == 'asset' &&
            isset($data['version']) && $data['version'] == $this->getVersion()){
            $asset = $this->get('table')->getEntityByFields(['anr'=>$anrId,'code'=>$data['asset']['code']]);
            if($asset){
                $idAsset = $asset->get('id');
            }else{
                $asset = $this->get('entity');
                $asset->exchangeArray($data['asset']);
                $asset->set('id',null);
                $asset->set('anr',$anrId);
                $this->setDependencies($asset,['anr']);
                $idAsset = $this->get('table')->save($asset);
            }

            if(!empty($data['amvs'])){
                foreach($data['amvs'] as $amvArray){
                    $amv = $this->get('amvEntity');
                    $amv->set('asset',$idAsset);
                    $amv->set('anr',$anrId);
                    $amv->set('status',$amvArray['status']);
                    if(isset($data['threat'][$amvArray['threat']])){ // Threats
                        if(is_array($data['threat'][$amvArray['threat']])){
                            $threat = $this->get('threatTable')->getEntityByFields(['anr'=>$anrId,'code'=>$data['threat'][$amvArray['threat']]['code']]);
                            if($threat){
                                $data['threat'][$amvArray['threat']] = $threat->get('id');
                                // TODO: que fait-on si le theme est différent ?
                            }else{
                                $threat = $this->get('threatEntity');
                                $data['threat'][$amvArray['threat']]['id'] = null;
                                $themeArray = $data['threat'][$amvArray['threat']]['theme'];
                                unset($data['threat'][$amvArray['threat']]['theme']);
                                $threat->exchangeArray($data['threat'][$amvArray['threat']]);
                                $threat->set('anr',$anrId);
                                if(!empty($themeArray) && isset($data['themes'][$themeArray['id']])){ // Themes
                                    if(is_array($data['themes'][$themeArray['id']])){
                                        $theme = $this->get('themeEntity');
                                        $data['themes'][$themeArray['id']]['id'] = null;
                                        $theme->exchangeArray($data['themes'][$themeArray['id']]);
                                        $theme->set('anr',$anrId);
                                        $this->setDependencies($theme,['anr']);
                                        $idTheme = $this->get('themeTable')->save($theme);
                                        $data['themes'][$themeArray['id']] = $idTheme;
                                    }
                                    $threat->set('theme',$data['themes'][$themeArray['id']]);
                                }
                                $this->setDependencies($threat,['anr', 'theme']);
                                $data['threat'][$amvArray['threat']] = $this->get('threatTable')->save($threat);
                            }
                        }
                        $amv->set('threat',$data['threat'][$amvArray['threat']]);
                    }

                    if(isset($data['vuls'][$amvArray['vulnerability']])){ // Vulnerabilities
                        if(is_array($data['vuls'][$amvArray['vulnerability']])){
                            $vul = $this->get('vulnerabilityTable')->getEntityByFields(['anr'=>$anrId,'code'=>$data['vuls'][$amvArray['vulnerability']]['code']]);
                            if($vul){
                                $data['vuls'][$amvArray['vulnerability']] = $vul->get('id');
                            }else{
                                $vul = $this->get('vulnerabilityEntity');
                                $data['vuls'][$amvArray['vulnerability']]['id'] = null;
                                $vul->exchangeArray($data['vuls'][$amvArray['vulnerability']]);
                                $vul->set('anr',$anrId);
                                $this->setDependencies($vul,['anr']);
                                $data['vuls'][$amvArray['vulnerability']] = $this->get('vulnerabilityTable')->save($vul);
                            }
                        }
                        $amv->set('vulnerability',$data['vuls'][$amvArray['vulnerability']]);
                    }

                    for($i=1;$i<=3;$i++){
                        if(isset($data['measures'][$amvArray['measure'.$i]])){ // Measure 1 / 2 / 3
                            if(is_array($data['measures'][$amvArray['measure'.$i]])){
                                $measure = $this->get('measureTable')->getEntityByFields(['anr'=>$anrId,'code'=>$data['measures'][$amvArray['measure'.$i]]['code']]);
                                if($measure){
                                    $data['measures'][$amvArray['measure'.$i]] = $measure->get('id');
                                }else{
                                    $measure = $this->get('measureEntity');
                                    $data['measures'][$amvArray['measure'.$i]]['id'] = null;
                                    $measure->exchangeArray($data['measures'][$amvArray['measure'.$i]]);
                                    $measure->set('anr',$anrId);
                                    $this->setDependencies($measure,['anr']);
                                    $data['measures'][$amvArray['measure'.$i]] = $this->get('measureTable')->save($measure);
                                }
                            }
                            $amv->set('measure'.$i,$data['measures'][$amvArray['measure'.$i]]);
                        }else{
                            $amv->set('measure'.$i,null);
                        }
                    }

                    $amvTest = $this->get('amvTable')->getEntityByFields([
                        'anr'=>$anrId,
                        'asset'=>$amv->get('asset'),
                        'threat'=>$amv->get('threat'),
                        'vulnerability'=>$amv->get('vulnerability'),
                    ]);
                    if(!$amvTest){ // on test que cet AMV sur cette ANR n'existe pas
                        $this->setDependencies($amv,['anr', 'asset', 'threat', 'vulnerability', 'measure[1]()', 'measure[2]()', 'measure[3]()']);
                        $this->get('amvTable')->save($amv);
                    }
                }
            }
            return $idAsset;
        }
        return false;
    }
}
