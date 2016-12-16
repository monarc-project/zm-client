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
        $data = $this->decrypt($data,$key);

        $anr = $this->get('anrTable')->getEntity($anrId); // on a une erreur si inconnue
        return $this->get('objectExportService')->importFromArray($data,$anr,$mode);
    }
}