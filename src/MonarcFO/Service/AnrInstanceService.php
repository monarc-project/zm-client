<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Model\Entity\AnrSuperClass;
use MonarcFO\Model\Table\RecommandationTable;
use MonarcFO\Model\Table\UserAnrTable;
use \DateTime;
/**
 * This class is the service that handles instances in use within an ANR. Inherits most of the behavior from its
 * MonarcCore parent class.
 * @package MonarcFO\Service
 */
class AnrInstanceService extends \MonarcCore\Service\InstanceService
{
    /** @var UserAnrTable */
    protected $userAnrTable;

    /**
     * Imports a previously exported instance from an uploaded file into the current ANR. It may be imported using two
     * different modes: 'merge', which will update the existing instances using the file's data, or 'duplicate' which
     * will create a new instance using the data.
     * @param int $anrId The ANR ID
     * @param array $data The data that has been posted to the API
     * @return array An array where the first key is the generated IDs, and the second are import errors
     * @throws \MonarcCore\Exception\Exception If the uploaded data is invalid, or the ANR invalid
     */
    public function importFromFile($anrId, $data)
    {
        // Mode may either be 'merge' or 'duplicate'
        $mode = empty($data['mode']) ? 'merge' : $data['mode'];

        // The object may be imported at the root, or under an existing instance in the ANR instances tree
        $idParent = empty($data['idparent']) ? null : $data['idparent'];

        // We can have multiple files imported with the same password (we'll emit warnings if the password mismatches)
        if (empty($data['file'])) {
            throw new \MonarcCore\Exception\Exception('File missing', 412);
        }

        $ids = $errors = [];
        $anr = $this->get('anrTable')->getEntity($anrId); // throws an MonarcCore\Exception\Exception if invalid

        foreach ($data['file'] as $keyfile => $f) {
            // Ensure the file has been uploaded properly, silently skip the files that are erroneous
            if (isset($f['error']) && $f['error'] === UPLOAD_ERR_OK && file_exists($f['tmp_name'])) {
                $sharedData = [];

                $file = [];
                if (empty($data['password'])) {
                    $file = json_decode(trim(file_get_contents($f['tmp_name'])), true);
                    if ($file == false) { // support legacy export which were base64 encoded
                      $file = json_decode(trim($this->decrypt(base64_decode(file_get_contents($f['tmp_name'])), '')), true);
                    }
                } else {
                    // Decrypt the file and store the JSON data as an array in memory
                    $key = $data['password'];
                    $file = json_decode(trim($this->decrypt(file_get_contents($f['tmp_name']), $key)), true);
                    if ($file == false) { // support legacy export which were base64 encoded
                      $file = json_decode(trim($this->decrypt(base64_decode(file_get_contents($f['tmp_name'])), $key)), true);
                    }
                }

                if ($file !== false && ($id = $this->importFromArray($file, $anr, $idParent, $mode, false, $sharedData, true)) !== false) {
                    // Import was successful, store the ID
                    if (is_array($id)) {
                        $ids = array_merge($ids, $id);
                    } else {
                        $ids[] = $id;
                    }
                } else {
                    $errors[] = 'The file "' . $f['name'] . '" can\'t be imported';
                }
            }

            // Free up the memory in case we're handling big files
            unset($data['file'][$keyfile]);
        }

        // Free up the memory
        unset($data);

        return [$ids, $errors];
    }

    /**
     * Imports an instance from an exported data (json) array.
     * @see #importFromFile
     * @param array $data The instance data
     * @param AnrSuperClass $anr The target ANR
     * @param null|int $idParent The parent under which the instance should be imported, or null if at the root
     * @param string $modeImport Import mode, either 'merge' or 'duplicate'
     * @param bool $include_eval Whether or not to include evaluation data
     * @param array $sharedData Cached shared data array
     * @param bool $isRoot If the imported instance should be treated as a root instance
     * @return array|bool An array of created instances IDs, or false in case of error
     */
    public function importFromArray($data, $anr, $idParent = null, $modeImport = 'merge', $include_eval = false, &$sharedData = [], $isRoot = false)
    {
        // When importing huge instances trees, Zend can take up a whole lot of memory
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', -1);

        // Ensure we're importing an instance, from the same version (this is NOT a backup feature!)
        if (isset($data['type']) && $data['type'] == 'instance' &&
            array_key_exists('version', $data) && $data['version'] == $this->getVersion()
        ) {
            // On teste avant tout que l'on peux importer le fichier dans cette instance (level != LEVEL_INTER)
            if ($isRoot && !empty($idParent)) {
                $parent = $this->get('table')->getEntity($idParent);

                // On en profite pour vérifier qu'on n'importe pas le fichier dans une instance qui n'appartient
                // pas à l'ANR passée en param
                if ($parent->get('level') == \MonarcCore\Model\Entity\InstanceSuperClass::LEVEL_INTER
                    || $parent->get('anr')->get('id') != $anr->get('id')
                ) {
                    return false;
                }
            }


            // On s'occupe de l'évaluation. Le $data['scales'] n'est présent que sur la première instance, il ne l'est
            // plus après donc ce code n'est exécuté qu'une seule fois.
            $local_scale_impact = null;
            if ($data['with_eval'] && isset($data['scales'])) {
                $include_eval = true;
                $scales = $this->get('scaleTable')->getEntityByFields(['anr' => $anr->get('id')]);
                $temp = [];

                foreach ($scales as $sc) {
                    if ($sc->get('type') == \MonarcCore\Model\Entity\Scale::TYPE_IMPACT) {
                        // utile pour la gestion des conséquences
                        $local_scale_impact = $sc;
                    }

                    $temp[$sc->get('type')]['min'] = $sc->get('min');
                    $temp[$sc->get('type')]['max'] = $sc->get('max');
                }
                unset($scales);
                $sharedData['scales']['dest'] = $temp;
                $sharedData['scales']['orig'] = $data['scales'];
            } elseif ($data['with_eval']) {
                $include_eval = true;
            }

            // On importe l'objet
            if (!isset($sharedData['objects'])) {
                $sharedData['objects'] = [];
            }
            $idObject = $this->get('objectExportService')->importFromArray($data['object'], $anr, $modeImport, $sharedData);
            if (!$idObject) {
                return false;
            }

            // Instance
            $class = $this->get('table')->getClass();
            $instance = new $class();
            $instance->setDbAdapter($this->get('table')->getDb());
            $instance->setLanguage($this->getLanguage());
            $toExchange = $data['instance'];
            unset($toExchange['id']);
            unset($toExchange['position']);
            $toExchange['anr'] = $anr->get('id');
            $toExchange['object'] = $idObject;
            $toExchange['asset'] = null;
            $obj = $this->get('objectExportService')->get('table')->getEntity($idObject);
            if ($obj) {
                $toExchange['asset'] = $obj->get('asset')->get('id');
                if ($modeImport == 'duplicate') {
                    for ($i = 1; $i <= 4; $i++) {
                        $toExchange['name' . $i] = $obj->get('name' . $i);
                    }
                }
            }
            $toExchange['parent'] = $idParent;
            $toExchange['implicitPosition'] = \MonarcCore\Model\Entity\AbstractEntity::IMP_POS_END;
            if ($isRoot) {
                // On force en level "ROOT" lorsque c'est le 1er niveau de l'import. Pour les autres, on laisse les
                // levels définis de l'export
                $toExchange['level'] = \MonarcCore\Model\Entity\InstanceSuperClass::LEVEL_ROOT;
            }

            $instance->exchangeArray($toExchange);
            $this->setDependencies($instance, ['anr', 'object', 'asset', 'parent']);
            $instanceId = $this->get('table')->save($instance);

            $this->get('instanceRiskService')->createInstanceRisks($instanceId, $anr->get('id'), $obj);

            // Gestion des conséquences
            if ($include_eval) {
                $ts = ['c', 'i', 'd'];
                foreach ($ts as $t) {
                    if ($instance->get($t . 'h')) {
                        $instance->set($t . 'h', 1);
                        $instance->set($t, -1);
                    } else {
                        $instance->set($t . 'h', 0);
                        $instance->set($t, $this->approximate(
                            $instance->get($t),
                            $sharedData['scales']['orig'][\MonarcCore\Model\Entity\Scale::TYPE_IMPACT]['min'],
                            $sharedData['scales']['orig'][\MonarcCore\Model\Entity\Scale::TYPE_IMPACT]['max'],
                            $sharedData['scales']['dest'][\MonarcCore\Model\Entity\Scale::TYPE_IMPACT]['min'],
                            $sharedData['scales']['dest'][\MonarcCore\Model\Entity\Scale::TYPE_IMPACT]['max']
                        ));
                    }
                }

                if (!empty($data['consequences'])) {
                    if (empty($local_scale_impact)) {
                        $local_scale_impact = current($this->get('scaleTable')->getEntityByFields(['anr' => $anr->get('id'), 'type' => \MonarcCore\Model\Entity\Scale::TYPE_IMPACT]));
                    }
                    $scalesImpactType = $this->get('scaleImpactTypeTable')->getEntityByFields(['anr' => $anr->get('id')]);
                    $localScalesImpactType = [];
                    foreach ($scalesImpactType as $sc) {
                        $localScalesImpactType[$sc->get('label' . $this->getLanguage())] = $sc->get('id');
                    }
                    unset($scalesImpactType);

                    foreach ($data['consequences'] as $conseq) {
                        if (!isset($localScalesImpactType[$conseq['scaleImpactType']['label' . $this->getLanguage()]])) {
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
                            $this->setDependencies($scaleImpT, ['anr', 'scale']);
                            $localScalesImpactType[$conseq['scaleImpactType']['label' . $this->getLanguage()]] = $this->get('scaleImpactTypeTable')->save($scaleImpT);
                        }
                        $ts = ['c', 'i', 'd'];

                        // Maintenant on peut alimenter le tableau de conséquences comme si ça venait d'un formulaire
                        foreach ($ts as $t) {
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
                        $toExchange['scaleImpactType'] = $localScalesImpactType[$conseq['scaleImpactType']['label' . $this->getLanguage()]];
                        $class = $this->get('instanceConsequenceTable')->getClass();
                        $consequence = new $class();
                        $consequence->setDbAdapter($this->get('instanceConsequenceTable')->getDb());
                        $consequence->setLanguage($this->getLanguage());
                        $consequence->exchangeArray($toExchange);
                        $this->setDependencies($consequence, ['anr', 'object', 'instance', 'scaleImpactType']);
                        $this->get('instanceConsequenceTable')->save($consequence);
                    }
                }
            } else {
                // on génère celles par défaut
                $this->createInstanceConsequences($instanceId, $anr->get('id'), $obj);
            }
            $this->refreshImpactsInherited($anr->get('id'), $idParent, $instance);

            if (!empty($data['risks'])) {
                // On charge les "threats" existants
                $sharedData['ithreats'] = $tCodes = [];
                foreach ($data['threats'] as $t) {
                    $tCodes[$t['code']] = $t['code'];
                }
                $existingRisks = $this->get('instanceRiskService')->get('threatTable')->getEntityByFields(['anr' => $anr->get('id'), 'code' => $tCodes]);
                foreach ($existingRisks as $t) {
                    $sharedData['ithreats'][$t->get('code')] = $t->get('id');
                }
                // On charge les "vulnerabilities" existants
                $sharedData['ivuls'] = $vCodes = [];
                foreach ($data['vuls'] as $v) {
                    $vCodes[$v['code']] = $v['code'];
                }
                $existingRisks = $this->get('instanceRiskService')->get('vulnerabilityTable')->getEntityByFields(['anr' => $anr->get('id'), 'code' => $vCodes]);
                foreach ($existingRisks as $t) {
                    $sharedData['ivuls'][$t->get('code')] = $t->get('id');
                }

                foreach ($data['risks'] as $risk) {
                    // le risque spécifique doit être créé même si on n'inclut pas les évaluations
                    if ($risk['specific']) {
                        // on doit le créer localement mais pour ça il nous faut les pivots sur les menaces et vulnérabilités
                        // on checke si on a déjà les menaces et les vulnérabilités liées, si c'est pas le cas, faut les créer
                        if (!isset($sharedData['ithreats'][$data['threats'][$risk['threat']]['code']])) {
                            $toExchange = $data['threats'][$risk['threat']];
                            unset($toExchange['id']);
                            $toExchange['anr'] = $anr->get('id');
                            $class = $this->get('instanceRiskService')->get('threatTable')->getClass();
                            $threat = new $class();
                            $threat->setDbAdapter($this->get('instanceConsequenceTable')->getDb());
                            $threat->setLanguage($this->getLanguage());
                            $threat->exchangeArray($toExchange);
                            $this->setDependencies($threat, ['anr']);
                            $sharedData['ithreats'][$data['threats'][$risk['threat']]['code']] = $this->get('instanceConsequenceTable')->save($threat);
                        }

                        if (!isset($sharedData['ivuls'][$data['vuls'][$risk['vulnerability']]['code']])) {
                            $toExchange = $data['vuls'][$risk['vulnerability']];
                            unset($toExchange['id']);
                            $toExchange['anr'] = $anr->get('id');
                            $class = $this->get('instanceRiskService')->get('vulnerabilityTable')->getClass();
                            $vul = new $class();
                            $vul->setDbAdapter($this->get('instanceRiskService')->get('vulnerabilityTable')->getDb());
                            $vul->setLanguage($this->getLanguage());
                            $vul->exchangeArray($toExchange);
                            $this->setDependencies($vul, ['anr']);
                            $sharedData['ivuls'][$data['vuls'][$risk['vulnerability']]['code']] = $this->get('instanceRiskService')->get('vulnerabilityTable')->save($vul);
                        }

                        $toExchange = $risk;
                        unset($toExchange['id']);
                        $toExchange['anr'] = $anr->get('id');
                        $toExchange['instance'] = $instanceId;
                        $toExchange['asset'] = $obj ? $obj->get('asset')->get('id') : null;
                        $toExchange['amv'] = null;
                        $toExchange['threat'] = $sharedData['ithreats'][$data['threats'][$risk['threat']]['code']];
                        $toExchange['vulnerability'] = $sharedData['ivuls'][$data['vuls'][$risk['vulnerability']]['code']];
                        $class = $this->get('instanceRiskService')->get('table')->getClass();
                        $r = new $class();
                        $r->setDbAdapter($this->get('instanceRiskService')->get('table')->getDb());
                        $r->setLanguage($this->getLanguage());
                        $r->exchangeArray($toExchange);
                        $this->setDependencies($r, ['anr', 'amv', 'instance', 'asset', 'threat', 'vulnerability']);
                        $idRisk = $this->get('instanceRiskService')->get('table')->save($r);
                        $r->set('id', $idRisk);
                    } else {
                        $r = current($this->get('instanceRiskService')->get('table')->getEntityByFields([
                            'anr' => $anr->get('id'),
                            'instance' => $instanceId,
                            'asset' => $obj ? $obj->get('asset')->get('id') : null,
                            'threat' => $sharedData['ithreats'][$data['threats'][$risk['threat']]['code']],
                            'vulnerability' => $sharedData['ivuls'][$data['vuls'][$risk['vulnerability']]['code']]
                        ]));
                    }
                    if (!empty($r) && $include_eval) {
                        $r->set('threatRate', $this->approximate(
                            $risk['threatRate'],
                            $sharedData['scales']['orig'][\MonarcCore\Model\Entity\Scale::TYPE_THREAT]['min'],
                            $sharedData['scales']['orig'][\MonarcCore\Model\Entity\Scale::TYPE_THREAT]['max'],
                            $sharedData['scales']['dest'][\MonarcCore\Model\Entity\Scale::TYPE_THREAT]['min'],
                            $sharedData['scales']['dest'][\MonarcCore\Model\Entity\Scale::TYPE_THREAT]['max']
                        ));
                        $r->set('vulnerabilityRate', $this->approximate(
                            $risk['vulnerabilityRate'],
                            $sharedData['scales']['orig'][\MonarcCore\Model\Entity\Scale::TYPE_VULNERABILITY]['min'],
                            $sharedData['scales']['orig'][\MonarcCore\Model\Entity\Scale::TYPE_VULNERABILITY]['max'],
                            $sharedData['scales']['dest'][\MonarcCore\Model\Entity\Scale::TYPE_VULNERABILITY]['min'],
                            $sharedData['scales']['dest'][\MonarcCore\Model\Entity\Scale::TYPE_VULNERABILITY]['max']
                        ));
                        $r->set('kindOfMeasure', $risk['kindOfMeasure']);
                        $r->set('comment', $risk['comment']);
                        $r->set('commentAfter', $risk['commentAfter']);

                        // La valeur -1 pour le reduction_amount n'a pas de sens, c'est 0 le minimum. Le -1 fausse
                        // les calculs.
                        // Cas particulier, faudrait pas mettre n'importe quoi dans cette colonne si on part d'une scale
                        // 1 - 7 vers 1 - 3 on peut pas avoir une réduction de 4, 5, 6 ou 7
                        $r->set('reductionAmount', ($risk['reductionAmount'] != -1) ? $this->approximate($risk['reductionAmount'], 0, $risk['vulnerabilityRate'], 0, $r->get('vulnerabilityRate'),0) : 0);
                        $idRisk = $this->get('instanceRiskService')->get('table')->save($r);

                        // Recommandations
                        if (!empty($data['recos'][$risk['id']])) {
                            foreach ($data['recos'][$risk['id']] as $reco) {
                                // La recommandation
                                if (isset($sharedData['recos'][$reco['id']])) { // Cette recommandation a déjà été gérée dans cet import
                                    if ($risk['kindOfMeasure'] != \MonarcCore\Model\Entity\InstanceRiskSuperClass::KIND_NOT_TREATED) {
                                        $aReco = $this->get('recommandationTable')->getEntity($sharedData['recos'][$reco['id']]);
                                        if ($aReco->get('position') <= 0 || is_null($aReco->get('position'))) {
                                            $pos = count($this->get('recommandationTable')->getEntityByFields(['anr' => $anr->get('id'), 'position' => ['op' => 'IS NOT', 'value' => null]], ['position' => 'ASC'])) + 1;
                                            $aReco->set('position', $pos);
                                            $this->get('recommandationTable')->save($aReco);
                                        }
                                    }
                                } else {
                                    // sinon, on teste sa présence
                                    $toExchange = $reco;
                                    unset($toExchange['id']);
                                    unset($toExchange['commentAfter']); // data du link
                                    $toExchange['anr'] = $anr->get('id');

                                    // on test l'unicité du code
                                    $aReco = current($this->get('recommandationTable')->getEntityByFields(['anr' => $anr->get('id'), 'code' => $reco['code']]));
                                    if (empty($aReco)) { // Code absent, on crée une nouvelle recommandation
                                        $class = $this->get('recommandationTable')->getClass();
                                        $aReco = new $class();
                                        if ($risk['kindOfMeasure'] == \MonarcCore\Model\Entity\InstanceRiskSuperClass::KIND_NOT_TREATED) {
                                            $toExchange['position'] = null;
                                        } else {
                                            $toExchange['position'] = count($this->get('recommandationTable')->getEntityByFields(['anr' => $anr->get('id'), 'position' => ['op' => 'IS NOT', 'value' => null]], ['position' => 'ASC'])) + 1;
                                        }
                                    } elseif (($aReco->get('position') <= 0 || is_null($aReco->get('position'))) && $risk['kindOfMeasure'] != \MonarcCore\Model\Entity\InstanceRiskSuperClass::KIND_NOT_TREATED) {
                                        $toExchange['position'] = count($this->get('recommandationTable')->getEntityByFields(['anr' => $anr->get('id'), 'position' => ['op' => 'IS NOT', 'value' => null]], ['position' => 'ASC'])) + 1;
                                    }
                                    $aReco->setDbAdapter($this->get('recommandationTable')->getDb());
                                    $aReco->setLanguage($this->getLanguage());
                                    $aReco->exchangeArray($toExchange, $aReco->get('id') > 0);
                                    $this->setDependencies($aReco, ['anr']);
                                    if(isset($toExchange['duedate']['date']))
                                      $aReco->setDueDate(new DateTime($toExchange['duedate']['date']));
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
                                    'objectGlobal' => (($obj && $obj->get('scope') == \MonarcCore\Model\Entity\ObjectSuperClass::SCOPE_GLOBAL) ? $obj->get('id') : null),
                                    'asset' => $r->get('asset')->get('id'),
                                    'threat' => $r->get('threat')->get('id'),
                                    'vulnerability' => $r->get('vulnerability')->get('id'),
                                    'commentAfter' => $reco['commentAfter'],
                                    'op' => 0,
                                    'risk' => $idRisk,
                                ];
                                $rr->exchangeArray($toExchange);
                                $this->setDependencies($rr, ['anr', 'recommandation', 'instanceRisk', 'instance', 'objectGlobal', 'asset', 'threat', 'vulnerability']);
                                $this->get('recommandationRiskTable')->save($rr);

                                // Recommandation <-> Measures
                                if (!empty($data['recolinks'][$reco['id']])) {
                                    foreach ($data['recolinks'][$reco['id']] as $mid) {
                                        if (isset($sharedData['measures'][$mid])) { // Cette measure a déjà été gérée dans cet import
                                        } elseif ($data['measures'][$mid]) {
                                            // on teste sa présence
                                            $toExchange = $data['measures'][$mid];
                                            unset($toExchange['id']);
                                            $toExchange['anr'] = $anr->get('id');
                                            $measure = current($this->get('amvService')->get('measureTable')->getEntityByFields(['anr' => $anr->get('id'), 'code' => $data['measures'][$mid]['code']]));
                                            if (empty($measure)) {
                                                $class = $this->get('amvService')->get('measureTable')->getClass();
                                                $measure = new $class();
                                                $measure->setDbAdapter($this->get('amvService')->get('measureTable')->getDb());
                                                $measure->setLanguage($this->getLanguage());
                                            }
                                            $measure->exchangeArray($toExchange);
                                            $this->setDependencies($measure, ['anr']);
                                            $sharedData['measures'][$mid] = $this->get('amvService')->get('measureTable')->save($measure);
                                        }

                                        if (isset($sharedData['measures'][$mid])) {
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
                                            $this->setDependencies($lk, ['anr', 'recommandation', 'measure']);
                                            $this->get('recommandationMeasureTable')->save($lk);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                // on met finalement à jour les risques en cascade
                $this->updateRisks($anr->get('id'), $instanceId);
            }

            if (!empty($data['risksop'])) {
                $toApproximate = [
                    \MonarcCore\Model\Entity\Scale::TYPE_THREAT => [
                        'netProb',
                        'targetedProb',
                    ],
                    \MonarcCore\Model\Entity\Scale::TYPE_IMPACT => [
                        'netR',
                        'netO',
                        'netL',
                        'netF',
                        'targetedR',
                        'targetedO',
                        'targetedL',
                        'targetedF',
                    ],
                ];
                $toInit = [];
                if ($anr->get('showRolfBrut')) {
                    $toApproximate[\MonarcCore\Model\Entity\Scale::TYPE_THREAT][] = 'brutProb';
                    $toApproximate[\MonarcCore\Model\Entity\Scale::TYPE_IMPACT][] = 'brutR';
                    $toApproximate[\MonarcCore\Model\Entity\Scale::TYPE_IMPACT][] = 'brutO';
                    $toApproximate[\MonarcCore\Model\Entity\Scale::TYPE_IMPACT][] = 'brutL';
                    $toApproximate[\MonarcCore\Model\Entity\Scale::TYPE_IMPACT][] = 'brutF';
                } else {
                    $toInit = [
                        'brutProb',
                        'brutR',
                        'brutO',
                        'brutL',
                        'brutF',
                    ];
                }
                foreach ($data['risksop'] as $ro) {
                    // faut penser à actualiser l'anr_id, l'instance_id, l'object_id. Le risk_id quant à lui n'est pas repris dans l'export, on s'en moque donc
                    $class = $this->get('instanceRiskOpService')->get('table')->getClass();
                    $r = new $class();
                    $ro['rolfRisk'] = null;

                    $toExchange = $ro;
                    unset($toExchange['id']);
                    $toExchange['anr'] = $anr->get('id');
                    $toExchange['instance'] = $instanceId;
                    $toExchange['object'] = $idObject;

                    // traitement de l'évaluation -> c'est complètement dépendant des échelles locales
                    if ($include_eval) {
                        // pas d'impact des subscales, on prend les échelles nominales
                        foreach ($toInit as $i) {
                            $toExchange[$i] = -1;
                        }
                        foreach ($toApproximate as $type => $list) {
                            foreach ($list as $i) {
                                $toExchange[$i] = $this->approximate(
                                    $toExchange[$i],
                                    $sharedData['scales']['orig'][$type]['min'],
                                    $sharedData['scales']['orig'][$type]['max'],
                                    $sharedData['scales']['dest'][$type]['min'],
                                    $sharedData['scales']['dest'][$type]['max']
                                );
                            }
                        }
                    }
                    $r->setDbAdapter($this->get('instanceRiskOpService')->get('table')->getDb());
                    $r->setLanguage($this->getLanguage());
                    $r->exchangeArray($toExchange);
                    $this->setDependencies($r, ['anr', 'instance', 'object', 'rolfRisk']);
                    $idRiskOp = $this->get('instanceRiskOpService')->get('table')->save($r);

                    // Recommandations
                    if ($include_eval && !empty($data['recosop'][$ro['id']]) && !empty($idRiskOp)) {
                        foreach ($data['recosop'][$ro['id']] as $reco) {
                            // La recommandation
                            if (isset($sharedData['recos'][$reco['id']])) {
                                // Cette recommandation a déjà été gérée dans cet import
                                if ($ro['kindOfMeasure'] != \MonarcCore\Model\Entity\InstanceRiskSuperClass::KIND_NOT_TREATED) {
                                    $aReco = $this->get('recommandationTable')->getEntity($sharedData['recos'][$reco['id']]);
                                    if ($aReco->get('position') <= 0 || is_null($aReco->get('position'))) {
                                        $pos = count($this->get('recommandationTable')->getEntityByFields(['anr' => $anr->get('id'), 'position' => ['op' => 'IS NOT', 'value' => null]], ['position' => 'ASC'])) + 1;
                                        $aReco->set('position', $pos);
                                        $this->get('recommandationTable')->save($aReco);
                                    }
                                }
                            } else {
                                // sinon, on teste sa présence
                                $toExchange = $reco;
                                unset($toExchange['id']);
                                unset($toExchange['commentAfter']); // data du link
                                $toExchange['anr'] = $anr->get('id');

                                // on test l'unicité du code
                                $aReco = current($this->get('recommandationTable')->getEntityByFields(['anr' => $anr->get('id'), 'code' => $reco['code']]));
                                if (empty($aReco)) {
                                    // Code absent, on crée une nouvelle recommandation
                                    $class = $this->get('recommandationTable')->getClass();
                                    $aReco = new $class();
                                    if ($ro['kindOfMeasure'] == \MonarcCore\Model\Entity\InstanceRiskSuperClass::KIND_NOT_TREATED) {
                                        $toExchange['position'] = null;
                                    } else {
                                        $toExchange['position'] = count($this->get('recommandationTable')->getEntityByFields(['anr' => $anr->get('id'), 'position' => ['op' => 'IS NOT', 'value' => null]], ['position' => 'ASC'])) + 1;
                                    }
                                } else if (($aReco->get('position') <= 0 || is_null($aReco->get('position'))) && $ro['kindOfMeasure'] != \MonarcCore\Model\Entity\InstanceRiskSuperClass::KIND_NOT_TREATED) {
                                    $toExchange['position'] = count($this->get('recommandationTable')->getEntityByFields(['anr' => $anr->get('id'), 'position' => ['op' => 'IS NOT', 'value' => null]], ['position' => 'ASC'])) + 1;
                                }

                                $aReco->setDbAdapter($this->get('recommandationTable')->getDb());
                                $aReco->setLanguage($this->getLanguage());
                                $aReco->exchangeArray($toExchange, $aReco->get('id') > 0);
                                $this->setDependencies($aReco, ['anr']);
                                if(isset($toExchange['duedate']['date']))
                                  $aReco->setDueDate(new DateTime($toExchange['duedate']['date']));
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
                                'instanceRiskOp' => $idRiskOp,
                                'instance' => $instanceId,
                                'objectGlobal' => (($obj && $obj->get('scope') == \MonarcCore\Model\Entity\ObjectSuperClass::SCOPE_GLOBAL) ? $obj->get('id') : null),
                                'asset' => null,
                                'threat' => null,
                                'vulnerability' => null,
                                'commentAfter' => $reco['commentAfter'],
                                'op' => 1,
                                'risk' => $idRiskOp,
                            ];
                            $rr->exchangeArray($toExchange);
                            $this->setDependencies($rr, ['anr', 'recommandation', 'instanceRiskOp', 'instance', 'objectGlobal', 'asset', 'threat', 'vulnerability']);
                            $this->get('recommandationRiskTable')->save($rr);

                            // Recommandation <-> Measures
                            if (!empty($data['recolinks'][$reco['id']])) {
                                foreach ($data['recolinks'][$reco['id']] as $mid) {
                                    if (isset($sharedData['measures'][$mid])) { // Cette measure a déjà été gérée dans cet import
                                    } else if ($data['measures'][$mid]) {
                                        // on teste sa présence
                                        $toExchange = $data['measures'][$mid];
                                        unset($toExchange['id']);
                                        $toExchange['anr'] = $anr->get('id');
                                        $measure = current($this->get('amvService')->get('measureTable')->getEntityByFields(['anr' => $anr->get('id'), 'code' => $data['measures'][$mid]['code']]));
                                        if (empty($measure)) {
                                            $class = $this->get('amvService')->get('measureTable')->getClass();
                                            $measure = new $class();
                                            $measure->setDbAdapter($this->get('amvService')->get('measureTable')->getDb());
                                            $measure->setLanguage($this->getLanguage());
                                        }
                                        $measure->exchangeArray($toExchange);
                                        $this->setDependencies($measure, ['anr']);
                                        $sharedData['measures'][$mid] = $this->get('amvService')->get('measureTable')->save($measure);
                                    }

                                    if (isset($sharedData['measures'][$mid])) {
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
                                        $this->setDependencies($lk, ['anr', 'recommandation', 'measure']);
                                        $this->get('recommandationMeasureTable')->save($lk);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if (!empty($data['children'])) {
                foreach ($data['children'] as $child) {
                    $this->importFromArray($child, $anr, $instanceId, $modeImport, $include_eval, $sharedData);//et ainsi de suite ...
                }
                $this->updateChildrenImpacts($instanceId);
            }

            // Duplicate from AnrRecommandationRiskService::initPosition()
            $recoRisks = $this->get('recommandationRiskTable')->getEntityByFields([
                'anr' => $anr->id,
            ]);
            $idReco = [];
            foreach ($recoRisks as $rr) {
                if ($rr->instanceRisk && $rr->instanceRisk->kindOfMeasure != \MonarcCore\Model\Entity\InstanceRiskSuperClass::KIND_NOT_TREATED) {
                    $idReco[$rr->recommandation->id] = $rr->recommandation->id;
                }
                if ($rr->instanceRiskOp && $rr->instanceRiskOp->kindOfMeasure != \MonarcCore\Model\Entity\InstanceRiskOpSuperClass::KIND_NOT_TREATED) {
                    $idReco[$rr->recommandation->id] = $rr->recommandation->id;
                }
            }

            if (!empty($idReco)) {
                // Retrieve recommandations
                /** @var RecommandationTable $recommandationTable */
                $recommandationTable = $this->get('recommandationTable');
                $recommandations = $recommandationTable->getEntityByFields(['anr' => $anr->id, 'id' => $idReco], ['importance' => 'DESC', 'code' => 'ASC']);

                $i = 1;
                $nbRecommandations = count($recommandations);
                foreach ($recommandations as $recommandation) {
                    $recommandation->position = $i;
                    $recommandationTable->save($recommandation, ($i == $nbRecommandations));
                    $i++;
                }
            }

            return $instanceId;
        } else if (isset($data['type']) && $data['type'] == 'anr' &&
            array_key_exists('version', $data) && $data['version'] == $this->getVersion()
        ) {
            $first = true;
            $instanceIds = [];
            foreach ($data['instances'] as $inst) {
                if ($first) {
                    if ($data['with_eval'] && isset($data['scales'])) {
                        $inst['with_eval'] = $data['with_eval'];
                        $inst['scales'] = $data['scales'];
                        $include_eval = true;
                    }
                    $first = false;
                }
                if (($instanceId = $this->importFromArray($inst, $anr, $idParent, $modeImport, $include_eval, $sharedData, $isRoot)) !== false) {
                    $instanceIds[] = $instanceId;
                }
            }
            return $instanceIds;
        }
        return false;
    }

    /**
     * Method to approximate the value within new bounds, typically when the exported object had a min/max bound
     * bigger than the target's ANR bounds.
     * @param int $x The value to approximate
     * @param int $minorig The source min bound
     * @param int $maxorig The source max bound
     * @param int $mindest The target min bound
     * @param int $maxdest The target max bound
     * @return int|mixed The approximated value
     */
    protected function approximate($x, $minorig, $maxorig, $mindest, $maxdest, $defaultvalue = -1)
    {
        if ($x == $maxorig){
            return $maxdest;
        } elseif ($x != -1 && ($maxorig - $minorig) != -1) {
            return min(max(round(($x / ($maxorig - $minorig + 1)) * ($maxdest - $mindest + 1)), $mindest), $maxdest);
        } else {
            return $defaultvalue;
        }
    }
}
