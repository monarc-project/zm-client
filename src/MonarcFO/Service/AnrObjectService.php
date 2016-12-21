<?php
namespace MonarcFO\Service;

/**
 * ANR Object Service
 *
 * Class AnrObjectService
 * @package MonarcFO\Service
 */
class AnrObjectService extends \MonarcCore\Service\ObjectService
{
    protected $selfCoreService;

    public function importFromFile($anrId,$data){
        // on a bien un pwd (ou vide)
        $key = empty($data['password'])?'':$data['password'];
        $mode = empty($data['mode'])?'merge':$data['mode'];
        // On aura la possibilité d'avoir plusieurs fichiers (même pwd: si un fichier ne match pas, on renvoie un warning)
        if(empty($data['file'])){
            throw new \Exception('File missing', 412);
        }
        $ids = $errors = [];
        $anr = $this->get('anrTable')->getEntity($anrId); // on a une erreur si inconnue
        foreach($data['file'] as $f){
            if(isset($f['error']) && $f['error'] === UPLOAD_ERR_OK && file_exists($f['tmp_name'])){
                $file = json_decode(trim($this->decrypt(base64_decode(file_get_contents($f['tmp_name'])),$key)),true);
                if($file !== false && ($id = $this->get('objectExportService')->importFromArray($file,$anr,$mode)) !== false){
                    $ids[] = $id;
                }else{
                    $errors[] = 'The file "'.$f['name'].'" can\'t be imported';
                }
            }
        }

        return [$ids,$errors];
    }

    public function getCommonObjects($anrId){
        $anr = $this->get('anrTable')->getEntity($anrId); // on a une erreur si inconnue
        $objects = $this->get('selfCoreService')->getAnrObjects(1, -1, 'name'.$anr->get('language'), null, null, $anr->get('model'), null);
        $fields = ['id','mode','scope','name'.$anr->get('language'),'label'.$anr->get('language'),'disponibility','position'];
        $fields = array_combine($fields, $fields);
        foreach($objects as $k => $o){
            foreach($o as $k2 => $v2){
                if(!isset($fields[$k2])){
                    unset($objects[$k][$k2]);
                }
            }
            if($o['category']){
                $objects[$k]['category'] = $o['category']->getJsonArray(['id','root','parent','label'.$anr->get('language'),'position']);
            }
            $objects[$k]['asset'] = $o['asset']->getJsonArray(['id','label'.$anr->get('language'),'description'.$anr->get('language'),'mode','type','status']);
        }
        return $objects;
    }

    public function getCommonEntity($anrId, $id){
        if(empty($anrId)){
            throw new \Exception('Anr id missing', 412);
        }
        $anr = $this->get('anrTable')->getEntity($anrId); // on a une erreur si inconnue
        $object = current($this->get('selfCoreService')->getAnrObjects(1, -1, 'name'.$anr->get('language'), [], ['id'=>$id], $anr->get('model'), null));
        if(!empty($object)){
            return $this->get('selfCoreService')->getCompleteEntity($id);
        }else{
            throw new \Exception('Object not found',412);
        }
    }


    public function importFromCommon($id,$data){
        if(empty($data['anr'])){
            throw new \Exception('Anr id missing', 412);
        }
        $anr = $this->get('anrTable')->getEntity($data['anr']); // on a une erreur si inconnue
        $object = current($this->get('selfCoreService')->getAnrObjects(1, -1, 'name'.$anr->get('language'), [], ['id'=>$id], $anr->get('model'), null));
        if(!empty($object)){
            // Export
            $json = $this->get('selfCoreService')->get('objectExportService')->generateExportArray($id);
            if($json){
                return $this->get('objectExportService')->importFromArray($json,$anr, isset($data['mode'])?$data['mode']:'merge');
            }
        }else{
            throw new \Exception('Object not found',412);
        }
    }
}