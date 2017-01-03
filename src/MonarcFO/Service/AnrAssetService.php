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
	protected $userAnrTable;

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
    protected $assetTable; // for setDependencies


	protected $filterColumns = [
        'label1', 'label2', 'label3', 'label4',
        'description1', 'description2', 'description3', 'description4',
        'code',
    ];

    protected $dependencies = ['anr'];

    public function importFromFile($anrId,$data){
    	// on a bien un pwd (ou vide)
        $key = empty($data['password']) || $data['password'] == 'null'?'':$data['password'];
        // On aura la possibilité d'avoir plusieurs fichiers (même pwd: si un fichier ne match pas, on renvoie un warning)
        if(empty($data['file'])){
            throw new \Exception('File missing', 412);
        }
        $ids = $errors = [];
        $anr = $this->get('anrTable')->getEntity($anrId); // on a une erreur si inconnue
        foreach($data['file'] as $f){
            if(isset($f['error']) && $f['error'] === UPLOAD_ERR_OK && file_exists($f['tmp_name'])){
                $file = json_decode(trim($this->decrypt(base64_decode(file_get_contents($f['tmp_name'])),$key)),true);
                if($file !== false && ($id = $this->get('objectExportService')->importFromArray($file,$anr)) !== false){
                    $ids[] = $id;
                }else{
                    $errors[] = 'The file "'.$f['name'].'" can\'t be imported';
                }
            }
        }

        return [$ids,$errors];
    }

    public function importFromArray($data,$anr,&$objectsCache = array()){
        if(isset($data['type']) && $data['type'] == 'asset' &&
            array_key_exists('version', $data) && $data['version'] == $this->getVersion()){
            $asset = current($this->get('table')->getEntityByFields(['anr'=>$anr->get('id'),'code'=>$data['asset']['code']]));
            if(!empty($asset)){
                $idAsset = $asset->get('id');
            }else{
                $asset = $this->get('entity');
                $asset->exchangeArray($data['asset']);
                $asset->set('id',null);
                $asset->set('anr',$anr->get('id'));
                $this->setDependencies($asset,['anr']);
                $idAsset = $this->get('table')->save($asset);
            }

            if(!empty($data['amvs']) && !empty($idAsset)){
                foreach($data['amvs'] as $amvArray){
                    $amv = $this->get('amvEntity');
                    $amv->set('asset',$idAsset);
                    $amv->set('anr',$anr->get('id'));
                    $amv->set('status',$amvArray['status']);
                    if(isset($data['threats'][$amvArray['threat']])){ // Threats
                        if(is_array($data['threats'][$amvArray['threat']])){
                            $threat = $this->get('threatTable')->getEntityByFields(['anr'=>$anr->get('id'),'code'=>$data['threats'][$amvArray['threat']]['code']]);
                            if($threat){
                                $threat = current($threat);
                                $data['threats'][$amvArray['threat']] = $threat->get('id');
                                // TODO: que fait-on si le theme est différent ?
                            }else{
                                $threat = $this->get('threatEntity');
                                $data['threats'][$amvArray['threat']]['id'] = null;
                                $themeArray = $data['threats'][$amvArray['threat']]['theme'];
                                unset($data['threats'][$amvArray['threat']]['theme']);
                                $threat->exchangeArray($data['threats'][$amvArray['threat']]);
                                $threat->set('anr',$anr->get('id'));
                                if(!empty($themeArray) && isset($data['themes'][$themeArray['id']])){ // Themes
                                    if(is_array($data['themes'][$themeArray['id']])){
                                        $theme = $this->get('themeEntity');
                                        $data['themes'][$themeArray['id']]['id'] = null;
                                        $theme->exchangeArray($data['themes'][$themeArray['id']]);
                                        $theme->set('anr',$anr->get('id'));
                                        $this->setDependencies($theme,['anr']);
                                        $idTheme = $this->get('themeTable')->save($theme);
                                        $data['themes'][$themeArray['id']] = $idTheme;
                                    }
                                    $threat->set('theme',$data['themes'][$themeArray['id']]);
                                }
                                $this->setDependencies($threat,['anr', 'theme']);
                                $objectsCache['threats'][$amvArray['threat']] = $data['threats'][$amvArray['threat']] = $this->get('threatTable')->save($threat);
                            }
                        }
                        $amv->set('threat',$data['threats'][$amvArray['threat']]);
                    }

                    if(isset($data['vuls'][$amvArray['vulnerability']])){ // Vulnerabilities
                        if(is_array($data['vuls'][$amvArray['vulnerability']])){
                            $vul = $this->get('vulnerabilityTable')->getEntityByFields(['anr'=>$anr->get('id'),'code'=>$data['vuls'][$amvArray['vulnerability']]['code']]);
                            if($vul){
                                $vul = current($vul);
                                $data['vuls'][$amvArray['vulnerability']] = $vul->get('id');
                            }else{
                                $vul = $this->get('vulnerabilityEntity');
                                $data['vuls'][$amvArray['vulnerability']]['id'] = null;
                                $vul->exchangeArray($data['vuls'][$amvArray['vulnerability']]);
                                $vul->set('anr',$anr->get('id'));
                                $this->setDependencies($vul,['anr']);
                                $objectsCache['vuls'][$amvArray['vulnerability']] = $data['vuls'][$amvArray['vulnerability']] = $this->get('vulnerabilityTable')->save($vul);
                            }
                        }
                        $amv->set('vulnerability',$data['vuls'][$amvArray['vulnerability']]);
                    }

                    for($i=1;$i<=3;$i++){
                        if(isset($data['measures'][$amvArray['measure'.$i]])){ // Measure 1 / 2 / 3
                            if(is_array($data['measures'][$amvArray['measure'.$i]])){
                                $measure = $this->get('measureTable')->getEntityByFields(['anr'=>$anr->get('id'),'code'=>$data['measures'][$amvArray['measure'.$i]]['code']]);
                                if($measure){
                                    $measure = current($measure);
                                    $data['measures'][$amvArray['measure'.$i]] = $measure->get('id');
                                }else{
                                    $measure = $this->get('measureEntity');
                                    $data['measures'][$amvArray['measure'.$i]]['id'] = null;
                                    $measure->exchangeArray($data['measures'][$amvArray['measure'.$i]]);
                                    $measure->set('anr',$anr->get('id'));
                                    $this->setDependencies($measure,['anr']);
                                    $objectsCache['measures'][$amvArray['measure'.$i]] = $data['measures'][$amvArray['measure'.$i]] = $this->get('measureTable')->save($measure);
                                }
                            }
                            $amv->set('measure'.$i,$data['measures'][$amvArray['measure'.$i]]);
                        }else{
                            $amv->set('measure'.$i,null);
                        }
                    }

                    $amvTest = $this->get('amvTable')->getEntityByFields([
                        'anr'=>$anr->get('id'),
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
