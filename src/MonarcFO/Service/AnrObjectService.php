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
        return $this->importFromArray($data,$anr,$mode);
    }

    public function importFromArray($data,$anr, $modeImport = 'merge', &$objectsCache = array()){
        if(isset($data['type']) && $data['type'] == 'object' &&
            array_key_exists('version', $data) && $data['version'] == $this->getVersion()){

            if(isset($data['object']['name'.$this->getLanguage()]) && isset($objectsCache[$data['object']['name'.$this->getLanguage()]])){
                return $objectsCache[$data['object']['name'.$this->getLanguage()]];
            }

            // import asset
            $assetId = $this->get('assetService')->importFromArray($data['object']['asset'],$anr);
            if($assetId){
                // import categories
                $idCateg = $this->importFromArrayCategories($data['categories'],$data['object']['category'],$anr->get('id'));
                
                /*
                 * INFO:
                 * Selon le mode d'import, la contruction de l'objet ne sera pas la même
                 * Seul un objet SCOPE_GLOBAL (scope) pourra être dupliqué
                 * Sinon c'est automatiquement une fusion
                 */
                if($data['objet']['scope'] == \MonarcCore\Entity\ObjectSuperClass::SCOPE_GLOBAL &&
                    $modeImport == 'duplicate'){
                    // Cela sera traité après le "else"
                }else{ // Fuusion
                    /*
                     * Le pivot pour savoir si on peut faire le merge est:
                     * 1. Même nom
                     * 2. Même catégorie
                     * 3. Même type d'actif
                     * 4. Même scope
                     */
                    $object = current($this->get('table')->getEntityByFields([
                        'anr' => $anr->get('id'),
                        'name'.$this->getLanguage() => $data['objet']['name'.$this->getLanguage()],
                        // il faut que le scope soit le même sinon souci potentiel sur l'impact des valeurs dans les instances (ex : on passe de local à global, toutes les instances choperaient la valeur globale)
                        'scope' => $data['objet']['scope'],
                        // il faut bien sûr que le type d'actif soit identique sinon on mergerait des torchons et des serviettes, ça donne des torchettes et c'est pas cool
                        'asset' => $assetId,
                        'category' => $idCateg
                    ]));
                    // Si il existe, c'est bien, on ne fera pas de "new"
                    // Sinon, on passera dans la création d'un nouvel "object"
                }

                $toExchange = $data['objet'];
                if(empty($object)){
                    $class = $this->get('table')->getClass();
                    $object = new $class();
                    $object->setDbAdapter($this->get('table')->getDb());
                    $object->setLanguage($this->getLanguage());
                    // Si on passe ici, c'est qu'on est en mode "duplication", il faut donc vérifier qu'on n'est pas plusieurs fois le même "name"
                    $suffixe = 0;
                    $current = $object = current($this->get('table')->getEntityByFields([
                        'anr' => $anr->get('id'),
                        'name'.$this->getLanguage() => $toExchange['name'.$this->getLanguage()]
                    ]));
                    while(!empty($current)){
                        $suffixe++;
                        $current = $object = current($this->get('table')->getEntityByFields([
                            'anr' => $anr->get('id'),
                            'name'.$this->getLanguage() => $toExchange['name'.$this->getLanguage()].' - Imp. #'.$suffixe
                        ]));
                    }
                    if($suffixe > 0){ // sinon inutile de modifier le nom, on garde celui de la source
                        for($i=1;$i<=4;$i++){
                            if(!empty($toExchange['name'.$i])){ // on ne modifie que pour les langues renseignées
                                $toExchange['name'.$i] .= ' - Imp. #'.$suffixe;
                            }
                        }
                    }
                }else{
                    // Si l'objet existe déjà, on risque de lui recréer des fils qu'il a déjà, dans ce cas faut détacher tous ses fils avant de lui re-rattacher (après import)
                    $links = $this->get('objectObjectTable')->getEntityByFields([
                        'anr' => $anr->get('id'),
                        'father' => $object->get('id')
                    ],['position' => 'DESC']);
                    foreach($links as $l){
                        if(!empty($l)){
                            $this->get('objectObjectTable')->delete($l->get('id'));
                        }
                    }
                }
                unset($toExchange['id']);
                $toExchange['anr'] = $anr->get('id');
                $toExchange['asset'] = $assetId;
                $toExchange['category'] = $idCateg;
                $object->exchangeArray($toExchange);
                $this->setDependencies($object,['anr', 'category', 'asset']);
                $idObj = $this->get('table')->save($object);

                $objectsCache[$data['object']['name'.$this->getLanguage()]] = $idObj;

                //on s'occupe des enfants
                if(!empty($data['children'])){
                    foreach($data['children'] as $c){
                        $child = $this->importFromArray($c, $anr, $modeImport, $objectsCache);

                        if($child){
                            $class = $this->get('objectObjectTable')->getClass();
                            $oo = new $class();
                            $oo->setDbAdapter($this->get('objectObjectTable')->getDb());
                            $oo->setLanguage($this->getLanguage());
                            $oo->exchangeArray([
                                'father' => $idObj,
                                'child' => $child,
                                'implicitPosition' => 2
                            ]);
                            $this->setDependencies($oo,['father', 'child']);
                            $this->get('objectObjectTable')->save($oo);
                        }
                    }
                }
                return $idObj;
            }
        }
        return false;
    }

    protected function importFromArrayCategories($data,$idCateg,$anrId){
        $return = null;
        if(!empty($data[$idCateg])){
            // On commence par le parent
            $idParent = $this->importFromArrayCategories($data,$data[$idCateg]['parent'],$anrId);

            $categ = current($this->get('categoryTable')->getEntityByFields([
                'anr' => $anrId,
                'parent' => $idParent,
                'label'.$this->getLanguage() => $data[$idCateg]['label'.$this->getLanguage()]
            ]));
            if(empty($categ)){ // on crée une nouvelle catégorie
                $class = $this->get('categoryTable')->getClass();
                $categ = new $class();
                $categ->setDbAdapter($this->get('categoryTable')->getDb());
                $categ->setLanguage($this->getLanguage());

                $toExchange = $data[$idCateg];
                unset($toExchange['id']);
                $toExchange['anr'] = $anrId;
                $toExchange['parent'] = $idParent;
                $toExchange['implicitPosition'] = 2;
                // le "exchangeArray" permet de gérer la position de façon automatique & de mettre à jour le "root"
                $categ->exchangeArray($toExchange);
                $this->setDependencies($categ,['anr','parent']);
                
                $return = $this->get('categoryTable')->save($categ);
            }else{ // sinon on utilise l'éxistant
                $return = $categ->get('id');
            }

        }
        return $return;
    }
}