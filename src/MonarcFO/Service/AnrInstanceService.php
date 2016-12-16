<?php
namespace MonarcFO\Service;

/**
 * ANR Instance Service
 *
 * Class AnrInstanceService
 * @package MonarcFO\Service
 */
class AnrInstanceService extends \MonarcCore\Service\InstanceService
{
    public function importFromFile($anrId,$data){
        // on a bien un pwd (ou vide)
        $key = empty($data['password'])?'':$data['password'];
        $mode = empty($data['mode'])?'merge':$data['mode'];
        // On aura la possibilité d'avoir plusieurs fichiers (même pwd: si un fichier ne match pas, on renvoie un warning)
        $data = $this->decrypt($data,$key);

        $anr = $this->get('anrTable')->getEntity($anrId); // on a une erreur si inconnue
        return $this->importFromArray($data,$anr,null, $mode);
    }

    public function importFromArray($data,$anr, $idParent = null, $modeImport = 'merge', $include_eval = false, &$sharedData = array()){
        if(isset($data['type']) && $data['type'] == 'instance' &&
            array_key_exists('version', $data) && $data['version'] == $this->getVersion()){

            // on s'occupe de l'évaluation
            // le $data['scales'] n'est présent que sur la première instance, il ne l'est plus après
            // donc ce code n'est executé qu'une seule fois
            $local_scale_impact = null;
            if($data['with_eval'] && isset($data['scales'])){
                $include_eval = true;
                $scales = $this->get('scaleTable')->getEntityByFields(['anr' => $anr->get('id')]);
                $temp = [];
                foreach($scales as $sc){
                    if($sc->get('type') == \MonarcCore\Model\Entity\Scale::TYPE_IMPACT){
                        $local_scale_impact = $sc; // utile pour la gestion des conséquences
                    }
                    $temp[$sc->get('type')]['min'] = $sc->get('min');
                    $temp[$sc->get('type')]['max'] = $sc->get('max');
                }
                $sharedData['scales']['dest'] = $temp;
                $sharedData['scales']['orig'] = $data['scales'];
            }

            // On importe l'objet
            if(!isset($sharedData['objects'])){
                $sharedData['objects'] = [];
            }
            $idObject = $this->get('objectExportService')->importFromArray($data['object'],$anr, $modeImport, $sharedData);

            // Instance
            $class = $this->get('table')->getClass();
            $instance = new $class();
            $instance->setDbAdapter($this->get('table')->getDb());
            $instance->setLanguage($this->getLanguage());
            $toExchange = $data['instance'];
            unset($toExchange['id']);
            $toExchange['anr'] = $anr->get('id');
            $toExchange['object'] = $idObject;
            $toExchange['asset'] = null;
            $obj = $this->get('objectExportService')->get('table')->getEntity($idObject);
            if($obj){
                $toExchange['asset'] = $obj->get('asset')->get('id');
            }
            $toExchange['parent'] = $idParent;
            $toExchange['root'] = null;
            $toExchange['implicitPosition'] = 2;
            $instance->exchangeArray($toExchange);
            $this->setDependencies($instance,['anr', 'object', 'asset', 'parent']);
            $instanceId = $this->get('table')->save($instance);

            $this->get('instanceRiskService')->createInstanceRisks($instanceId, $anr->get('id'), $obj);
            $this->get('instanceRiskOpService')->createInstanceRisksOp($instanceId, $anr->get('id'), $obj);
            $this->createInstanceConsequences($instanceId, $anr->get('id'), $obj);

            // Gestion des conséquences
            if($include_eval){
                $ts = ['c', 'i', 'd'];
                foreach($ts as $t){
                    if($instance->get($t.'h')){
                        $instance->set($t.'h',1);
                        $instance->set($t,-1);
                    }else{
                        $instance->set($t.'h',0);
                        $instance->set($t, $this->approximate(
                                            $instance->get($t),
                                            $sharedData['scales']['orig'][\MonarcCore\Model\Entity\Scale::TYPE_IMPACT]['min'],
                                            $sharedData['scales']['orig'][\MonarcCore\Model\Entity\Scale::TYPE_IMPACT]['max'],
                                            $sharedData['scales']['dest'][\MonarcCore\Model\Entity\Scale::TYPE_IMPACT]['min'],
                                            $sharedData['scales']['dest'][\MonarcCore\Model\Entity\Scale::TYPE_IMPACT]['max']
                                        ));
                    }
                }

                if(!empty($data['consequences'])){
                    if(empty($local_scale_impact)){
                        $local_scale_impact = current($this->get('scaleTable')->getEntityByFields(['anr' => $anr->get('id'), 'type' => \MonarcCore\Model\Entity\Scale::TYPE_IMPACT]));
                    }
                    $scalesImpactType = $this->get('scaleImpactTypeTable')->getEntityByFields(['anr' => $anr->get('id')]);
                    $localScalesImpactType = [];
                    foreach($scalesImpactType as $sc){
                        $localScalesImpactType[$sc->get('label'.$this->getLanguage())] = $sc->get('id');
                    }
                    unset($scalesImpactType);

                    foreach($data['consequences'] as $conseq){
                        if(!isset($localScalesImpactType[$conseq['scaleImpactType']['label'.$this->getLanguage()]])){
                            $toExchange = $conseq['scaleImpactType'];
                            unset($toExchange['id']);
                            $toExchange['anr'] = $anr->get('id');
                            $toExchange['scale'] = $local_scale_impact->get('id');
                            $toExchange['implicitPosition'] = 2;

                            $class = $this->get('scaleImpactTypeTable')->getClass();
                            $scaleImpT = new $class();
                            $scaleImpT->setDbAdapter($this->get('table')->getDb());
                            $scaleImpT->setLanguage($this->getLanguage());
                            $scaleImpT->exchangeArray($toExchange);
                            $this->setDependencies($scaleImpT,['anr', 'scale']);
                            $localScalesImpactType[$conseq['scaleImpactType']['label'.$this->getLanguage()]] = $this->get('scaleImpactTypeTable')->save($scaleImpT);
                        }
                        $ts = ['c', 'i', 'd'];
                        // maintenant on peut alimenter le tableau de conséquences comme si ça venait d'un formulaire
                        foreach($ts as $t){
                            $conseq[$t] = $conseq['isHidden'] ? -1 : $this->approximate(
                                                                                    $conseq[$t],
                                                                                    $sharedData['scales']['orig'][\MonarcCore\Model\Entity\Scale::TYPE_IMPACT]['min'],
                                                                                    $sharedData['scales']['orig'][\MonarcCore\Model\Entity\Scale::TYPE_IMPACT]['max'],
                                                                                    $sharedData['scales']['dest'][\MonarcCore\Model\Entity\Scale::TYPE_IMPACT]['min'],
                                                                                    $sharedData['scales']['dest'][\MonarcCore\Model\Entity\Scale::TYPE_IMPACT]['max']
                                                                                );
                        }
                        $toExchange = $conseq;
                        unset($toExchange['id']);
                        $toExchange['anr'] = $anr->get('id');
                        $toExchange['instance'] = $instanceId;
                        $toExchange['object'] = $idObject;
                        $toExchange['scale'] = $local_scale_impact->get('id');
                        $toExchange['scaleImpactType'] = $localScalesImpactType[$conseq['scaleImpactType']['label'.$this->getLanguage()]];
                        $class = $this->get('instanceConsequenceTable')->getClass();
                        $consequence = new $class();
                        $consequence->setDbAdapter($this->get('instanceConsequenceTable')->getDb());
                        $consequence->setLanguage($this->getLanguage());
                        $consequence->exchangeArray($toExchange);
                        $this->setDependencies($consequence,['anr', 'object', 'instance', 'scaleImpactType']);
                        $this->get('instanceConsequenceTable')->save($consequence);
                    }

                    // on met finalement à jour les risques en cascade
                    $this->updateRisks($anr->get('id'), $instanceId);
                }
            }

            if(!empty($data['risks'])){
                // On charge les "threats" existants
                $sharedData['ithreats'] = $tCodes = [];
                foreach($data['threats'] as $t){
                    $tCodes[$t['code']] = $t['code'];
                }
                $existingRisks = $this->get('threatTable')->getEntityByFields(['anr'=>$anr->get('id'),'code'=>$tCodes]);
                foreach($existingRisks as $t){
                    $sharedData['ithreats'][$t->get('code')] = $t->get('id');
                }
                // On charge les "vulnerabilities" existants
                $sharedData['ivuls'] = $vCodes = [];
                foreach($data['vuls'] as $v){
                    $vCodes[$v['code']] = $v['code'];
                }
                $existingRisks = $this->get('vulnerabilityTable')->getEntityByFields(['anr'=>$anr->get('id'),'code'=>$vCodes]);
                foreach($existingRisks as $t){
                    $sharedData['ivuls'][$t->get('code')] = $t->get('id');
                }

                foreach($data['risks'] as $risk){
                    // le risque spécifique doit être créé même si on n'inclut pas les évaluations
                    if($risk['specific']){
                        // on doit le créer localement mais pour ça il nous faut les pivots sur les menaces et vulnérabilités
                        // on checke si on a déjà les menaces et les vulnérabilités liées, si c'est pas le cas, faut les créer
                        if(!isset($sharedData['ithreats'][$data['threats'][$risk['threat']]['code']])){
                            $toExchange = $data['threats'][$risk['threat']];
                            unset($toExchange['id']);
                            $toExchange['anr'] = $anr->get('id');
                            $class = $this->get('threatTable')->getClass();
                            $threat = new $class();
                            $threat->setDbAdapter($this->get('instanceConsequenceTable')->getDb());
                            $threat->setLanguage($this->getLanguage());
                            $threat->exchangeArray($toExchange);
                            $this->setDependencies($threat,['anr']);
                            $sharedData['ithreats'][$data['threats'][$risk['threat']]['code']] = $this->get('instanceConsequenceTable')->save($threat);
                        }

                        if(!isset($sharedData['ivuls'][$data['vuls'][$risk['vulnerability']]['code']])){
                            $toExchange = $data['vuls'][$risk['vulnerability']];
                            unset($toExchange['id']);
                            $toExchange['anr'] = $anr->get('id');
                            $class = $this->get('vulnerabilityTable')->getClass();
                            $vul = new $class();
                            $vul->setDbAdapter($this->get('vulnerabilityTable')->getDb());
                            $vul->setLanguage($this->getLanguage());
                            $vul->exchangeArray($toExchange);
                            $this->setDependencies($vul,['anr']);
                            $sharedData['ivuls'][$data['vuls'][$risk['vulnerability']]['code']] = $this->get('vulnerabilityTable')->save($vul);
                        }

                        $toExchange = $risk;
                        unset($toExchange['id']);
                        $toExchange['anr'] = $anr->get('id');
                        $toExchange['instance'] = $instanceId;
                        $toExchange['asset'] = $obj?$obj->get('asset')->get('id'):null;
                        $toExchange['amv'] = null;
                        $toExchange['threat'] = $sharedData['ithreats'][$data['threats'][$risk['threat']]['code']];
                        $toExchange['vulnerability'] = $sharedData['ivuls'][$data['vuls'][$risk['vulnerability']]['code']];
                        $class = $this->get('instanceRiskTable')->getClass();
                        $r = new $class();
                        $r->setDbAdapter($this->get('instanceRiskTable')->getDb());
                        $r->setLanguage($this->getLanguage());
                        $r->exchangeArray($toExchange);
                        $this->setDependencies($r,['anr','amv','instance','asset','threat','vulnerability']);
                        $idRisk = $this->get('instanceRiskTable')->save($r);
                        $r->set('id',$idRisk);
                    }else{
                        $r = current($this->get('instanceRiskTable')->getEntityByFields([
                            'anr' => $anr->get('id'),
                            'instance' => $instanceId,
                            'asset' => $obj?$obj->get('asset')->get('id'):null,
                            'threat' => $sharedData['ithreats'][$data['threats'][$risk['threat']]['code']],
                            'vulnerability' => $sharedData['ivuls'][$data['vuls'][$risk['vulnerability']]['code']]
                        ]));
                    }
                    if(!empty($r) && $include_eval){
                        $r->set('threatRate',$this->approximate(
                            $risk['threatRate'],
                            $sharedData['scales']['orig'][\MonarcCore\Model\Entity\Scale::TYPE_IMPACT]['min'],
                            $sharedData['scales']['orig'][\MonarcCore\Model\Entity\Scale::TYPE_IMPACT]['max'],
                            $sharedData['scales']['dest'][\MonarcCore\Model\Entity\Scale::TYPE_IMPACT]['min'],
                            $sharedData['scales']['dest'][\MonarcCore\Model\Entity\Scale::TYPE_IMPACT]['max']
                        ));
                        $r->set('vulnerabilityRate',$this->approximate(
                            $risk['vulnerabilityRate'],
                            $sharedData['scales']['orig'][\MonarcCore\Model\Entity\Scale::TYPE_IMPACT]['min'],
                            $sharedData['scales']['orig'][\MonarcCore\Model\Entity\Scale::TYPE_IMPACT]['max'],
                            $sharedData['scales']['dest'][\MonarcCore\Model\Entity\Scale::TYPE_IMPACT]['min'],
                            $sharedData['scales']['dest'][\MonarcCore\Model\Entity\Scale::TYPE_IMPACT]['max']
                        ));
                        // la valeur -1 pour le reduction_amount n'a pas de sens, c'est 0 le minimum. Le -1 fausse les calculs
                        // cas particulier, faudrait pas mettre nimp dans cette colonne si on part d'une scale 1 - 7 vers 1 - 3 on peut pas avoir une réduction de 4, 5, 6 ou 7
                        $r->set('reductionAmount', ($risk['reductionAmount'] != -1) ? $this->approximate($risk['reductionAmount'], 0, $risk['vulnerabilityRate'], 0, $localrisk->get('vulnerabilityRate')) : 0 );
                        $idRisk = $this->get('instanceRiskTable')->save($r);

                        // Recommandations
                        if(!empty($data['recos'][$r->get('id')])){
                            foreach($data['recos'][$r->get('id')] as $reco){
                                // La recommandation
                                if(isset($sharedData['recos'][$reco['id']])){ // Cette recommandation a déjà été gérée dans cet import
                                }else{ // sinon, on teste sa présence
                                    $toExchange = $reco;
                                    unset($toExchange['id']);
                                    unset($toExchange['commentAfter']); // data du link
                                    $toExchange['anr'] = $anr->get('id');
                                    // on test l'unicité du code
                                    $aReco = current($this->get('recommandationTable')->getEntityByFields(['anr' => $anr->get('id'),'code' => $reco['code']]));
                                    if(empty($aReco)){ // Code absent, on crée une nouvelle recommandation
                                        $class = $this->get('recommandationTable')->getClass();
                                        $aReco = new $class();
                                        $aReco->setDbAdapter($this->get('recommandationTable')->getDb());
                                        $aReco->setLanguage($this->getLanguage());
                                        $toExchange['implicitPosition'] = 2;
                                    }
                                    $aReco->exchangeArray($toExchange);
                                    $this->setDependencies($aReco,['anr']);
                                    $sharedData['recos'][$reco['id']] = $this->get('recommandationTable')->save($aReco);
                                }

                                // Le lien recommandation <-> risk
                                $class = $this->get('recommandationRiskTable')->getClass();
                                $rr = new $class();
                                $rr->setDbAdapter($this->get('recommandationRiskTable')->getDb());
                                $rr->setLanguage($this->getLanguage());
                                $toExchange = [
                                    'anr' => $anr->get('id'),
                                    'recommandation' => $sharedData['recos'][$reco['id']],
                                    'instanceRisk' => $idRisk,
                                    'instance' => $instanceId,
                                    'objectGlobal' => (($obj && $obj->get('scope') == \MonarcCore\Model\Entity\ObjectSuperClass::SCOPE_GLOBAL)?$obj->get('id'):null),
                                    'asset' => $r->get('asset')->get('id'),
                                    'threat' => $r->get('threat')->get('id'),
                                    'vulnerability' => $r->get('vulnerability')->get('id'),
                                    'commentAfter' => $reco['commentAfter'],
                                ];
                                $rr->exchangeArray($toExchange);
                                $this->setDependencies($rr,['anr','recommandation','instanceRisk','instance','objectGlobal','asset','threat','vulnerability']);
                                $this->get('recommandationRiskTable')->save($rr);

                                // Recommandation <-> Measures
                                if(!empty($data['recolinks'][$reco['id']])){
                                    foreach($data['recolinks'][$reco['id']] as $mid){
                                        if(isset($sharedData['measures'][$mid])){ // Cette measure a déjà été gérée dans cet import
                                        }elseif($data['measures'][$mid]){
                                            // on teste sa présence
                                            $toExchange = $data['measures'][$mid];
                                            unset($toExchange['id']);
                                            $toExchange['anr'] = $anr->get('id');
                                            $measure = current($this->get('measureTable')->getEntityByFields(['anr' => $anr->get('id'),'code' => $data['measures'][$mid]['code']]));
                                            if(empty($measure)){
                                                $class = $this->get('measureTable')->getClass();
                                                $measure = new $class();
                                                $measure->setDbAdapter($this->get('measureTable')->getDb());
                                                $measure->setLanguage($this->getLanguage());
                                            }
                                            $measure->exchangeArray($toExchange);
                                            $this->setDependencies($measure,['anr']);
                                            $sharedData['measures'][$mid] = $this->get('measureTable')->save($measure);
                                        }

                                        if(isset($sharedData['measures'][$mid])){
                                            // On crée le lien
                                            $class = $this->get('recommandationMeasureTable')->getClass();
                                            $lk = new $class();
                                            $lk->setDbAdapter($this->get('recommandationMeasureTable')->getDb());
                                            $lk->setLanguage($this->getLanguage());
                                            $lk->exchangeArray([
                                                'anr' => $anr->get('id'),
                                                'recommandation' => $sharedData['recos'][$reco['id']],
                                                'measure' => $sharedData['measures'][$mid],
                                            ]);
                                            $this->setDependencies($lk,['anr','recommandation','measure']);
                                            $this->get('recommandationMeasureTable')->save($lk);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if(!empty($data['riskop'])){
                $toApproximate = [
                    'netProb',
                    'targetedProb',
                    'netR',
                    'netO',
                    'netL',
                    'netF',
                    'targetedR',
                    'targetedO',
                    'targetedL',
                    'targetedF',
                ];
                $toInit = [];
                if($anr->get('showRolfBrut')){
                    $toApproximate[] = 'brutProb';
                    $toApproximate[] = 'brutR';
                    $toApproximate[] = 'brutO';
                    $toApproximate[] = 'brutL';
                    $toApproximate[] = 'brutF';
                }else{
                    $toInit = [
                        'brutProb',
                        'brutR',
                        'brutO',
                        'brutL',
                        'brutF',
                    ];
                }
                foreach($data['riskop'] as $ro){
                    // faut penser à actualiser l'anr_id, l'instance_id, l'object_id. Le risk_id quant à lui n'est pas repris dans l'export, on s'en moque donc
                    $toExchange = $ro;
                    unset($toExchange['id']);
                    $toExchange['anr'] = $anr->get('id');
                    $toExchange['instance'] = $instanceId;
                    $toExchange['object'] = $idObject;
                    $toExchange['rolfRisk'] = null;
                    // traitement de l'évaluation -> c'est complètement dépendant des échelles locales
                    if($include_eval){ // pas d'impact des subscales, on prend les échelles nominales
                        foreach($toInit as $i){
                            $toExchange[$i] = -1;
                        }
                        foreach($toApproximate as $i){
                            $toExchange[$i] = $this->approximate(
                                $toExchange[$i],
                                $sharedData['scales']['orig'][\MonarcCore\Model\Entity\Scale::TYPE_IMPACT]['min'],
                                $sharedData['scales']['orig'][\MonarcCore\Model\Entity\Scale::TYPE_IMPACT]['max'],
                                $sharedData['scales']['dest'][\MonarcCore\Model\Entity\Scale::TYPE_IMPACT]['min'],
                                $sharedData['scales']['dest'][\MonarcCore\Model\Entity\Scale::TYPE_IMPACT]['max']
                            );
                        }
                    }
                    $class = $this->get('instanceRiskOpService')->get('table')->getClass();
                    $r = new $class();
                    $r->setDbAdapter($this->get('instanceRiskOpService')->get('table')->getDb());
                    $r->setLanguage($this->getLanguage());
                    $r->exchangeArray($toExchange);
                    $this->setDependencies($r,['anr','instance','object','rolfRisk']);
                    $idRisk = $this->get('instanceRiskOpService')->get('table')->save($r);
                }
            }
            
            if(!empty($data['children'])){
                foreach($data['children'] as $child){
                    $this->importFromArray($child, $anr, $instanceId, $modeImport, $include_eval, $sharedData);//et ainsi de suite ...
                }
            }
            return $instanceId;
        }
        return false;
    }

    protected function approximate($x, $minorig, $maxorig, $mindest, $maxdest){
        if($x == $maxorig) return $maxdest;
        else if($x == -1) return -1;
        else if(($maxorig - $minorig) != -1) return min( max( round( ( $x / ( $maxorig - $minorig + 1 ) ) * ( $maxdest - $mindest + 1 ) ) , $mindest ), $maxdest );
        else return -1;
    }
}
