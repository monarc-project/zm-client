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
        $key = empty($data['password'])?'':$data['password'];
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
                $c = $this->get('table')->getClass();
                $asset = new $c();
                $asset->setDbAdapter($this->get('table')->getDb());
                $asset->setLanguage($this->getLanguage());
                $asset->exchangeArray($data['asset']);
                $asset->set('anr',$anr->get('id'));
                $this->setDependencies($asset,['anr']);
                $idAsset = $this->get('table')->save($asset);
            }

            if(!empty($data['amvs']) && !empty($idAsset)){
                foreach($data['amvs'] as $amvArray){
                    $amvData = [
                        'asset' => $idAsset,
                        'anr' => $anr->get('id'),
                        'status' => $amvArray['status'],
                    ];
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
                                        $c = $this->get('themeTable')->getClass();
                                        $theme = new $c();
                                        $theme->setDbAdapter($this->get('themeTable')->getDb());
                                        $theme->setLanguage($this->getLanguage());
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
                        $amvData['threat'] = $data['threats'][$amvArray['threat']];
                    }

                    if(isset($data['vuls'][$amvArray['vulnerability']])){ // Vulnerabilities
                        if(is_array($data['vuls'][$amvArray['vulnerability']])){
                            $vul = $this->get('vulnerabilityTable')->getEntityByFields(['anr'=>$anr->get('id'),'code'=>$data['vuls'][$amvArray['vulnerability']]['code']]);
                            if($vul){
                                $vul = current($vul);
                                $data['vuls'][$amvArray['vulnerability']] = $vul->get('id');
                            }else{
                                $c = $this->get('vulnerabilityTable')->getClass();
                                $vul = new $c();
                                $vul->setDbAdapter($this->get('vulnerabilityTable')->getDb());
                                $vul->setLanguage($this->getLanguage());
                                $data['vuls'][$amvArray['vulnerability']]['id'] = null;
                                $vul->exchangeArray($data['vuls'][$amvArray['vulnerability']]);
                                $vul->set('anr',$anr->get('id'));
                                $this->setDependencies($vul,['anr']);
                                $objectsCache['vuls'][$amvArray['vulnerability']] = $data['vuls'][$amvArray['vulnerability']] = $this->get('vulnerabilityTable')->save($vul);
                            }
                        }
                        $amvData['vulnerability'] = $data['vuls'][$amvArray['vulnerability']];
                    }

                    for($i=1;$i<=3;$i++){
                        if(isset($data['measures'][$amvArray['measure'.$i]])){ // Measure 1 / 2 / 3
                            if(is_array($data['measures'][$amvArray['measure'.$i]])){
                                $measure = $this->get('measureTable')->getEntityByFields(['anr'=>$anr->get('id'),'code'=>$data['measures'][$amvArray['measure'.$i]]['code']]);
                                if($measure){
                                    $measure = current($measure);
                                    $data['measures'][$amvArray['measure'.$i]] = $measure->get('id');
                                }else{
                                    $c = $this->get('measureTable')->getClass();
                                    $measure = new $c();
                                    $measure->setDbAdapter($this->get('measureTable')->getDb());
                                    $measure->setLanguage($this->getLanguage());
                                    $data['measures'][$amvArray['measure'.$i]]['id'] = null;
                                    $measure->exchangeArray($data['measures'][$amvArray['measure'.$i]]);
                                    $measure->set('anr',$anr->get('id'));
                                    $this->setDependencies($measure,['anr']);
                                    $objectsCache['measures'][$amvArray['measure'.$i]] = $data['measures'][$amvArray['measure'.$i]] = $this->get('measureTable')->save($measure);
                                }
                            }
                            $amvData['measure'.$i] = $data['measures'][$amvArray['measure'.$i]];
                        }else{
                            $amvData['measure'.$i] = null;
                        }
                    }

                    $amvTest = $this->get('amvTable')->getEntityByFields([
                        'anr'=>$anr->get('id'),
                        'asset'=>$amvData['asset'],
                        'threat'=>$amvData['threat'],
                        'vulnerability'=>$amvData['vulnerability'],
                    ]);
                    if(!$amvTest){ // on test que cet AMV sur cette ANR n'existe pas
                        $c = $this->get('amvTable')->getClass();
                        $amv = new $c();
                        $amv->setDbAdapter($this->get('amvTable')->getDb());
                        $amv->setLanguage($this->getLanguage());
                        $amv->exchangeArray($amvData,true);
                        $this->setDependencies($amv,['anr', 'asset', 'threat', 'vulnerability', 'measure1', 'measure2', 'measure3']);
                        $this->get('amvTable')->save($amv);
                    }
                }
            }
            return $idAsset;
        }
        return false;
    }
}
