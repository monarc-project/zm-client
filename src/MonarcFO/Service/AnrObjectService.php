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
    public function importFromFile($anrId,$data){
        // on a bien un pwd (ou vide)
        $key = empty($data['password'])?'':$data['password'];
        $mode = empty($data['mode'])?'merge':$data['mode'];
        // On aura la possibilité d'avoir plusieurs fichiers (même pwd: si un fichier ne match pas, on renvoie un warning)
        if(empty($data['file'])){
            throw new \Exception('File missing', 412);
        }
        $ids = [];
        $anr = $this->get('anrTable')->getEntity($anrId); // on a une erreur si inconnue
        foreach($data['file'] as $f){
            if(isset($f['error']) && $f['error'] === UPLOAD_ERR_OK && file_exists($f['tmp_name'])){
                $file = json_decode(trim($this->decrypt(base64_decode(file_get_contents($f['tmp_name'])),$key)),true);
                if($file !== false && ($id = $this->get('objectExportService')->importFromArray($file,$anr,$mode)) !== false){
                    $ids[] = $id;
                }else{
                    $ids[] = 'The file "'.$f['name'].'" can\'t be imported';
                }
            }
        }

        return $ids;
    }
}