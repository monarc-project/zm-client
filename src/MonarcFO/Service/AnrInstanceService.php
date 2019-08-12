<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Model\Entity\AnrSuperClass;
use MonarcFO\Model\Table\RecommandationTable;
use MonarcFO\Model\Table\UserAnrTable;
use Ramsey\Uuid\Uuid;
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
    protected $questionTable;
    protected $questionChoiceTable;
    protected $threatTable;
    protected $scaleCommentTable;
    protected $scaleTable;
    protected $scaleCommentService;
    protected $interviewTable;
    protected $themeTable;
    protected $deliveryTable;
    protected $instanceRiskTable;
    protected $referentialTable;
    protected $soaCategoryTable;
    protected $measureTable;
    protected $measureMeasureTable;
    protected $soaTable;
    protected $recordService;

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
        $monarc_version = isset($data['monarc_version'])?$data['monarc_version']:null;

        // Ensure we're importing an instance, from the same version (this is NOT a backup feature!)
        if (isset($data['type']) && $data['type'] == 'instance'
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

            // Import the object
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
            $obj = $this->get('objectExportService')->get('table')->getEntity(['anr' => $anr, 'uuid' => $idObject]);
            if ($obj) {
                $toExchange['asset'] = is_string($obj->get('asset')->get('uuid'))?$obj->get('asset')->get('uuid'):$obj->get('asset')->get('uuid')->toString();
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

            // Update impacts from brothers for global assets

            $instanceBrothers = current($this->get('table')->getEntityByFields([ // Get instance risk of brother
                  'id' => ['op' => '!=', 'value' => $instanceId],
                  'anr' => $anr->get('id'),
                  'asset' => ['anr' => $anr->get('id'), 'uuid' => is_string($instance->get('asset')->get('uuid'))?$instance->get('asset')->get('uuid'):$instance->get('asset')->get('uuid')->toString()],
                  'object' => ['anr' => $anr->get('id'), 'uuid' => is_string($instance->get('object')->get('uuid'))?$instance->get('object')->get('uuid'):$instance->get('object')->get('uuid')->toString()]]));

            if (!empty($instanceBrothers)) {
                if ($instance->get('object')->get('scope') == \MonarcCore\Model\Entity\MonarcObject::SCOPE_GLOBAL &&
                    $modeImport == 'merge') {

                    $instanceConseqBrothers = $this->get('instanceConsequenceTable')->getEntityByFields([ // Get consequences of brother
                      'anr' => $anr->get('id'),
                      'instance' => $instanceBrothers,
                      'object' => ['anr' => $anr->get('id'), 'uuid' => is_string($instance->get('object')->get('uuid'))?$instance->get('object')->get('uuid'):$instance->get('object')->get('uuid')->toString()]]);

                    foreach ($instanceConseqBrothers as $icb) { //Update consequences for all brothers
                      $this->get('instanceConsequenceService')->updateBrothersConsequences($anr->get('id'), $icb->get('id'));
                    }
                    $ts = ['c', 'i', 'd'];
                    foreach ($ts as $t) { //Update impacts in instance
                        if ($instanceBrothers->get($t.'h') == 0) {
                            $instance->set($t.'h', 0);
                            $instance->set($t, $instanceBrothers->$t);
                        }elseif ($instance->get('parent')) {
                          $instance->set($t.'h', 1);
                          $instance->set($t, $instance->get('parent')->get($t));
                        }else {
                          $instance->set($t.'h', 1);
                          $instance->set($t, $instanceBrothers->$t);
                        }
                    }
                }
            }

            $this->refreshImpactsInherited($anr->get('id'), $idParent, $instance);

          if (!empty($data['risks'])) {
                // load of the existing value
                $sharedData['ivuls'] = $vCodes = [];
                $sharedData['ithreats'] = $tCodes = [];
                if(version_compare ($monarc_version, "2.8.2")>=0 ) { //TO DO:set the right value with the uuid version
                  foreach ($data['threats'] as $t) {
                      $tCodes[] = $t['uuid'];
                  }
                  $existingRisks = $this->get('instanceRiskService')->get('threatTable')->getEntityByFields(['anr' => $anr->get('id'), 'uuid' => $tCodes]);
                  foreach ($existingRisks as $t) {
                      $sharedData['ithreats'][] = is_string($t->get('uuid'))?$t->get('uuid'):$t->get('uuid')->toString();
                  }
                  foreach ($data['vuls'] as $v) {
                      $vCodes[] = $v['uuid'];
                  }
                  $existingRisks = $this->get('instanceRiskService')->get('vulnerabilityTable')->getEntityByFields(['anr' => $anr->get('id'), 'uuid' => $vCodes]);
                  foreach ($existingRisks as $t) {
                      $sharedData['ivuls'][] = is_string($t->get('uuid'))?$t->get('uuid'):$t->get('uuid')->toString();
                  }
                }
                else{
                  foreach ($data['threats'] as $t) {
                      $tCodes[$t['code']] = $t['code'];
                  }
                  $existingRisks = $this->get('instanceRiskService')->get('threatTable')->getEntityByFields(['anr' => $anr->get('id'), 'code' => $tCodes]);
                  foreach ($existingRisks as $t) {
                      $sharedData['ithreats'][$t->get('code')] = is_string($t->get('uuid'))?$t->get('uuid'):$t->get('uuid')->toString();
                  }
                  foreach ($data['vuls'] as $v) {
                      $vCodes[$v['code']] = $v['code'];
                  }
                  $existingRisks = $this->get('instanceRiskService')->get('vulnerabilityTable')->getEntityByFields(['anr' => $anr->get('id'), 'code' => $vCodes]);
                  foreach ($existingRisks as $t) {
                      $sharedData['ivuls'][$t->get('code')] = is_string($t->get('uuid'))?$t->get('uuid'):$t->get('uuid')->toString();
                  }
                }

                //Recommandations Sets
                $uuidRecSet = '';
                if (!empty($data['recSets'])){
                    foreach ($data['recSets'] as $recSet_UUID => $recSet_array) {
                        // check if the recommendation set is not already present in the analysis
                        $recommandationsSets = $this->get('recommandationSetTable')
                                                ->getEntityByFields(['anr' => $anr->id, 'uuid' => $recSet_UUID]);
                        if (empty($recommandationsSets)) {
                            $newRecommandationSet = new \MonarcFO\Model\Entity\RecommandationSet($recSet_array);
                            $newRecommandationSet->setAnr($anr);
                            $sharedData['recSets'][$recSet_UUID] = $this->get('recommandationSetTable')->save($newRecommandationSet);
                        }
                    }
                }
                //2.8.3
                else if (version_compare($monarc_version, "2.8.2")==-1){
                    $recommandationsSets = $this->get('recommandationSetTable')
                                                ->getEntityByFields(['anr' => $anr->id, 'label1' => "Recommandations importées"]);
                    if(!empty($recommandationsSets)){
                        $uuidRecSet = $recommandationsSets[0]->uuid->toString();
                    }
                    else{
                        $toExchange = [
                        'anr' => $anr->get('id'),
                        'label1' => 'Recommandations importées',
                        'label2' => 'Imported recommendations',
                        'label3' => 'Importierte empfehlungen',
                        'label4' => 'Geïmporteerde aanbevelingen',
                        ];
                        $class = $this->get('recommandationSetTable')->getClass();
                        $rS = new $class();
                        $rS->setDbAdapter($this->get('recommandationSetTable')->getDb());
                        $rS->setLanguage($this->getLanguage());
                        $rS->exchangeArray($toExchange);
                        $this->setDependencies($rS, ['anr']);
                        $uuidRecSet = $this->get('recommandationSetTable')->save($rS);
                    }
                    
                }

                //Recommandations unlinked to a recommandation risk
                if(!empty($data['recs'])){
                    foreach ($data['recs'] as $rec_UUID => $rec_array) {
                        // check if the recommendation is not already present in the analysis
                        $recommandations = $this->get('recommandationTable')
                                                ->getEntityByFields(['anr' => $anr->id, 'uuid' => $rec_UUID]);
                        if (empty($recommandations)) {
                            $recSets = $this->get('recommandationSetTable')->getEntityByFields(['anr' => $anr->id, 'uuid' => $rec_array['recommandationSet']]);
                            $newRecommandation = new \MonarcFO\Model\Entity\Recommandation($rec_array);
                            $newRecommandation->setAnr($anr);
                            $newRecommandation->setRecommandationSet($recSets[0]);
                            $sharedData['recs'][$rec_UUID] = $this->get('recommandationTable')->save($newRecommandation);
                        }

                    }
                }

                foreach ($data['risks'] as $risk) {
                  //uuid id now the pivot instead of code
                    if ($risk['specific']) {
                        // on doit le créer localement mais pour ça il nous faut les pivots sur les menaces et vulnérabilités
                        // on checke si on a déjà les menaces et les vulnérabilités liées, si c'est pas le cas, faut les créer
                        if (( !in_array($risk['threat'],$sharedData['ithreats']) && version_compare ($monarc_version, "2.8.2")>=0) ||
                              (version_compare ($monarc_version, "2.8.2")==-1 && !isset($sharedData['ithreats'][$data['threats'][$risk['threat']]['code']]))
                            ) {
                            $toExchange = $data['threats'][$risk['threat']];
                            unset($toExchange['id']);
                            $toExchange['anr'] = $anr->get('id');
                            $class = $this->get('instanceRiskService')->get('threatTable')->getClass();
                            $threat = new $class();
                            $threat->setDbAdapter($this->get('instanceConsequenceTable')->getDb());
                            $threat->setLanguage($this->getLanguage());
                            $threat->exchangeArray($toExchange);
                            $this->setDependencies($threat, ['anr']);
                            $tuuid = $this->get('instanceConsequenceTable')->save($threat,false);
                            if(!version_compare ($monarc_version, "2.8.2")==-1 )
                              $sharedData['ithreats'][$data['threats'][$risk['threat']]['code']] = $tuuid;
                        }


                        if (( !in_array($risk['vulnerability'],$sharedData['ivuls']) && version_compare ($monarc_version, "2.8.2")>=0) ||
                              (version_compare ($monarc_version, "2.8.2")==-1&& !isset($sharedData['ivuls'][$data['vuls'][$risk['vulnerability']]['code']]))
                            ) {
                            $toExchange = $data['vuls'][$risk['vulnerability']];
                            unset($toExchange['id']);
                            $toExchange['anr'] = $anr->get('id');
                            $class = $this->get('instanceRiskService')->get('vulnerabilityTable')->getClass();
                            $vul = new $class();
                            $vul->setDbAdapter($this->get('instanceRiskService')->get('vulnerabilityTable')->getDb());
                            $vul->setLanguage($this->getLanguage());
                            $vul->exchangeArray($toExchange);
                            $this->setDependencies($vul, ['anr']);
                            $vuuid = $this->get('instanceRiskService')->get('vulnerabilityTable')->save($vul,false);
                            if(version_compare ($monarc_version, "2.8.2")==-1 )
                              $sharedData['ivuls'][$data['vuls'][$risk['vulnerability']]['code']] = $vuuid;
                        }

                        if (isset($obj)) {
                            $instanceBrothers = $this->get('table')->getEntityByFields([ // Get the Instance of brothers
                                'id' => ['op' => '!=', 'value' => $instanceId],
                                'anr' => $anr->get('id'),
                                'asset' => ['anr' => $anr->get('id'),'uuid' => is_string($instance->get('asset')->get('uuid'))?$instance->get('asset')->get('uuid'):$instance->get('asset')->get('uuid')->toString()],
                                'object' => ['anr' => $anr->get('id'),'uuid' => is_string($obj->get('uuid'))?$obj->get('uuid'):$obj->get('uuid')->toString()]]);

                            // Creation of specific risks to brothers
                            foreach ($instanceBrothers as $ib) {
                              $toExchange = $risk;
                              unset($toExchange['id']);
                              $toExchange['anr'] = $anr->get('id');
                              $toExchange['instance'] = $ib->get('id');
                              $toExchange['asset'] = $obj->get('asset')->get('uuid')->toString();
                              $toExchange['amv'] = null;
                              $toExchange['threat'] = Uuid::isValid($risk['threat'])?$risk['threat']:$sharedData['ithreats'][$data['threats'][$risk['threat']]['code']];
                              $toExchange['vulnerability'] = Uuid::isValid($risk['vulnerability'])?$risk['vulnerability']:$sharedData['ivuls'][$data['vuls'][$risk['vulnerability']]['code']];
                              $class = $this->get('instanceRiskService')->get('table')->getClass();
                              $rToBrother = new $class();
                              $rToBrother->setDbAdapter($this->get('instanceRiskService')->get('table')->getDb());
                              $rToBrother->setLanguage($this->getLanguage());
                              $rToBrother->exchangeArray($toExchange);
                              $this->setDependencies($rToBrother, ['anr', 'amv', 'instance', 'asset', 'threat', 'vulnerability']);
                              $idRiskSpecific = $this->get('instanceRiskService')->get('table')->save($rToBrother,false);
                              $rToBrother->set('id', $idRiskSpecific);
                            }
                            //$this->get('table')->getDb()->flush();

                        }

                        $toExchange = $risk;
                        unset($toExchange['id']);
                        $toExchange['anr'] = $anr->get('id');
                        $toExchange['instance'] = $instanceId;
                        $toExchange['asset'] = $obj ?( is_string($obj->get('asset')->get('uuid'))?$obj->get('asset')->get('uuid'):$obj->get('asset')->get('uuid')->toString()) : null;
                        $toExchange['amv'] = null;
                        $toExchange['threat'] = Uuid::isValid($risk['threat'])?$risk['threat']:$sharedData['ithreats'][$data['threats'][$risk['threat']]['code']];
                        $toExchange['vulnerability'] = Uuid::isValid($risk['vulnerability'])?$risk['vulnerability']:$sharedData['ivuls'][$data['vuls'][$risk['vulnerability']]['code']];
                        $class = $this->get('instanceRiskService')->get('table')->getClass();
                        $r = new $class();
                        $r->setDbAdapter($this->get('instanceRiskService')->get('table')->getDb());
                        $r->setLanguage($this->getLanguage());
                        $r->exchangeArray($toExchange);
                        $this->setDependencies($r, ['anr', 'amv', 'instance', 'asset', 'threat', 'vulnerability']);
                        $idRisk = $this->get('instanceRiskService')->get('table')->save($r,false);
                        $r->set('id', $idRisk);
                    } else {
                        $tuuid = Uuid::isValid($risk['threat'])?$risk['threat']:$sharedData['ithreats'][$data['threats'][$risk['threat']]['code']];
                        $vuuid = Uuid::isValid($risk['vulnerability'])?$risk['vulnerability']:$sharedData['ivuls'][$data['vuls'][$risk['vulnerability']]['code']];
                        $r = current($this->get('instanceRiskService')->get('table')->getEntityByFields([
                            'anr' => $anr->get('id'),
                            'instance' => $instanceId,
                            'asset' => $obj ? ['anr' => $anr->get('id'), 'uuid' => is_string($obj->get('asset')->get('uuid'))?$obj->get('asset')->get('uuid'):$obj->get('asset')->get('uuid')->toString()] : null,
                            'threat' => ['anr' => $anr->get('id'), 'uuid' => $tuuid],
                            'vulnerability' => ['anr' => $anr->get('id'), 'uuid' => $vuuid]
                        ]));
                        if($r == false)$r = null;
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
                        $r->set('mh', $risk['mh']);
                        $r->set('kindOfMeasure', $risk['kindOfMeasure']);
                        $r->set('comment', $risk['comment']);
                        $r->set('commentAfter', $risk['commentAfter']);

                        // La valeur -1 pour le reduction_amount n'a pas de sens, c'est 0 le minimum. Le -1 fausse
                        // les calculs.
                        // Cas particulier, faudrait pas mettre n'importe quoi dans cette colonne si on part d'une scale
                        // 1 - 7 vers 1 - 3 on peut pas avoir une réduction de 4, 5, 6 ou 7
                        $r->set('reductionAmount', ($risk['reductionAmount'] != -1) ? $this->approximate($risk['reductionAmount'], 0, $risk['vulnerabilityRate'], 0, $r->get('vulnerabilityRate'),0) : 0);
                        $idRisk = $this->get('instanceRiskService')->get('table')->save($r,false);

                        // Merge all fields for global assets

                        if ($instance->get('object')->get('scope') == \MonarcCore\Model\Entity\MonarcObject::SCOPE_GLOBAL &&
                            $r->get('specific') == 0 &&
                            $modeImport == 'merge') {

                            $objectIdsBrothers = $this->get('instanceTable')->getEntityByFields([ // Get object Ids of brother
                                'anr' => $anr->get('id'),
                                'object' => ['anr' => $anr->get('id'), 'uuid' => is_string($instance->get('object')->get('uuid'))?$instance->get('object')->get('uuid'):$instance->get('object')->get('uuid')->toString() ]]);

                            $instanceRiskBrothers = current($this->get('instanceRiskTable')->getEntityByFields([ // Get instance risk of brother
                                'anr' => $anr->get('id'),
                                'instance' => ['op' => 'IN', 'value' => $objectIdsBrothers],
                                'amv' => ['anr' => $anr->get('id'), 'uuid' => is_string($r->get('amv')->get('uuid'))?$r->get('amv')->get('uuid'):$r->get('amv')->get('uuid')->toString()]]));

                            if (!empty($instanceRiskBrothers)) {
                                $dataUpdate = [];
                                $dataUpdate['anr'] = $anr->get('id');
                                $dataUpdate['threatRate'] = $instanceRiskBrothers->threatRate; // Merge threat rate
                                $dataUpdate['vulnerabilityRate'] = $instanceRiskBrothers->vulnerabilityRate; // Merge vulnerability rate
                                $dataUpdate['kindOfMeasure'] = $instanceRiskBrothers->kindOfMeasure; // Merge kind Of Measure
                                $dataUpdate['reductionAmount'] = $instanceRiskBrothers->reductionAmount; // Merge reduction amount
                                if (strcmp($instanceRiskBrothers->comment, $r->get('comment')) !== 0 && // Check if comment is different
                                    strpos($instanceRiskBrothers->comment, $r->get('comment')) == false){ // Check if comment is not exist yet

                                    $dataUpdate['comment'] = $instanceRiskBrothers->comment . "\n\n" . $r->get('comment'); // Merge comments
                                } else {
                                    $dataUpdate['comment'] = $instanceRiskBrothers->comment;
                                }

                                $this->get('instanceRiskService')->update($r->get('id'),$dataUpdate,true); // Finally update the risks
                              }
                        }

                        // Recommandations
                        if (!empty($data['recos'][$risk['id']])) {           
                            foreach ($data['recos'][$risk['id']] as $reco) {
                                //2.8.3
                                if (version_compare($monarc_version, "2.8.2")==-1){
                                    unset($reco['id']);
                                    $recs = $this->get('recommandationTable')->getEntityByFields(['code' => $reco['code'], 'description' => $reco['description']]);
                                    if(!empty($recs)){
                                        $reco['uuid'] = $recs[0]->get('uuid')->toString();
                                    }
                                    $reco['recommandationSet'] = $uuidRecSet;
                                }
                                $recSets = $this->get('recommandationSetTable')->getEntityByFields(['anr' => $anr->id, 'uuid' => $reco['recommandationSet']]);
                                // La recommandation
                                if (isset($sharedData['recos'][$reco['uuid']])) { // Cette recommandation a déjà été gérée dans cet import
                                    if ($risk['kindOfMeasure'] != \MonarcCore\Model\Entity\InstanceRiskSuperClass::KIND_NOT_TREATED) {
                                        $aReco = $this->get('recommandationTable')->getEntity(['anr' => $anr->get('id'), 'uuid' => $reco['uuid']]);
                                        if ($aReco->get('position') <= 0 || is_null($aReco->get('position'))) {
                                            $pos = count($this->get('recommandationTable')->getEntityByFields(['anr' => $anr->get('id'), 'position' => ['op' => 'IS NOT', 'value' => null]], ['position' => 'ASC'])) + 1;
                                            $aReco->set('position', $pos);
                                            $aReco->setRecommandationSet($recSets[0]);
                                            $reco['uuid'] = $this->get('recommandationTable')->save($aReco);
                                        }
                                    }
                                } else {
                                    // sinon, on teste sa présence
                                    $toExchange = $reco;
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
                                    $aReco->exchangeArray($toExchange, $aReco->get('uuid'));
                                    $aReco->setRecommandationSet($recSets[0]);
                                    $this->setDependencies($aReco, ['anr']);
                                    if(isset($toExchange['duedate']['date']))
                                      $aReco->setDueDate(new DateTime($toExchange['duedate']['date']));          
                                    $reco['uuid'] = $this->get('recommandationTable')->save($aReco);
                                    $sharedData['recos'][$reco['uuid']] = $reco['uuid'];
                                }

                                // Le lien recommandation <-> risk
                                $class = $this->get('recommandationRiskTable')->getClass();
                                $rr = new $class();
                                $rr->setDbAdapter($this->get('recommandationRiskTable')->getDb());
                                $rr->setLanguage($this->getLanguage());
                                $toExchange = [
                                    'anr' => $anr->get('id'),
                                    'recommandation' => $reco['uuid'],
                                    'instanceRisk' => $idRisk,
                                    'instance' => $instanceId,
                                    'objectGlobal' => (($obj && $obj->get('scope') == \MonarcCore\Model\Entity\ObjectSuperClass::SCOPE_GLOBAL) ? is_string($obj->get('uuid'))?$obj->get('uuid'):$obj->get('uuid')->toString() : null),
                                    'asset' => is_string($r->get('asset')->get('uuid'))?$r->get('asset')->get('uuid'):$r->get('asset')->get('uuid')->toString(),
                                    'threat' => is_string($r->get('threat')->get('uuid'))?$r->get('threat')->get('uuid'):$r->get('threat')->get('uuid')->toString(),
                                    'vulnerability' => is_string($r->get('vulnerability')->get('uuid'))?$r->get('vulnerability')->get('uuid'):$r->get('vulnerability')->get('uuid')->toString(),
                                    'commentAfter' => $reco['commentAfter'],
                                    'op' => 0,
                                    'risk' => $idRisk,
                                ];
                                $rr->exchangeArray($toExchange);
                                $this->setDependencies($rr, ['anr', 'recommandation', 'instanceRisk', 'instance', 'objectGlobal', 'asset', 'threat', 'vulnerability']);
                                $this->get('recommandationRiskTable')->save($rr);

                                // Reply recommandation to brothers
                                if (!empty($toExchange['objectGlobal']) && $modeImport == 'merge') {
                                      $instances = $this->get('table')->getEntityByFields([ // Get the brothers
                                          'anr' => $anr->get('id'),
                                          'asset' => ['anr' => $anr->get('id'), 'uuid' => is_string($obj->get('asset')->get('uuid'))?$obj->get('asset')->get('uuid'):$obj->get('asset')->get('uuid')->toString()],
                                          'object' => ['anr' => $anr->get('id'), 'uuid' => is_string($obj->get('uuid'))?$obj->get('uuid'):$obj->get('uuid')->toString()]]);

                                      if (!empty($instances)) {
                                          foreach ($instances as $i) {
                                            if ($r->get('specific') == 0) {
                                              $brothers = $this->get('instanceRiskTable')->getEntityByFields([ // Get the risks of brothers
                                                  'anr' => $anr->get('id'),
                                                  'instance' => $i->get('id'),
                                                  'amv' => ['anr' => $anr->get('id'), 'uuid' => is_string($r->get('amv')->get('uuid'))?$r->get('amv')->get('uuid'):$r->get('amv')->get('uuid')->toString()]]);
                                            }else {
                                              $brothers = $this->get('instanceRiskTable')->getEntityByFields([ // Get the risks of brothers
                                                  'anr' => $anr->get('id'),
                                                  'specific' => 1,
                                                  'instance' => $i->get('id'),
                                                  'threat' => ['anr' => $anr->get('id'), 'uuid' => is_string($r->get('threat')->get('uuid'))?$r->get('threat')->get('uuid'):$r->get('threat')->get('uuid')->toString()],
                                                  'vulnerability' => ['anr' => $anr->get('id'), 'uuid' => is_string($r->get('vulnerability')->get('uuid'))?$r->get('threat')->get('uuid'):$r->get('threat')->get('uuid')->toString()]]);
                                            }

                                                  foreach ($brothers as $brother) {
                                                      $RecoCreated= $this->get('recommandationRiskTable')->getEntityByFields([ // Check if reco-risk link exist
                                                        'recommandation' => ['anr'=> $anr->get('id'), 'uuid'=> $reco['uuid']],
                                                        'instance' => $i->get('id'),
                                                        'instanceRisk' => $brother->id ]);

                                                        if (empty($RecoCreated)) { // Creation link
                                                              $rr = new $class();
                                                              $rr->setDbAdapter($this->get('recommandationRiskTable')->getDb());
                                                              $rr->setLanguage($this->getLanguage());
                                                              $toExchange['instanceRisk'] = $brother->id;
                                                              $toExchange['instance'] = $i->get('id');
                                                              $rr->exchangeArray($toExchange);
                                                              $this->setDependencies($rr, ['anr', 'recommandation', 'instanceRisk', 'instance', 'objectGlobal', 'asset', 'threat', 'vulnerability']);
                                                              $this->get('recommandationRiskTable')->save($rr,false);
                                                        }
                                                  }
                                            }
                                      }

                                }
                            }
                            $this->get('table')->getDb()->flush();
                        }
                    }

                  // Check recommendations from brothers
                  $instanceBrother = current($this->get('table')->getEntityByFields([ // Get instances of brothers (only one)
                      'id' => ['op' => '!=', 'value' => $instanceId],
                      'anr' => $anr->get('id'),
                      'asset' => ['anr' => $anr->get('id'), 'uuid' => is_string($obj->get('asset')->get('uuid'))?$obj->get('asset')->get('uuid'):$obj->get('asset')->get('uuid')->toString()],
                      'object' => ['anr' => $anr->get('id'), 'uuid' => is_string($obj->get('uuid'))?$obj->get('uuid'):$obj->get('uuid')->toString()]]));

                  if (!empty($instanceBrother) && !empty($r) && $r->get('specific') == 0 ) {
                            $instanceRiskBrothers = $this->get('instanceRiskTable')->getEntityByFields([ // Get instance risk of brother
                                'anr' => $anr->get('id'),
                                'instance' => $instanceBrother->get('id'),
                                'amv' => ['anr' => $anr->get('id'), 'uuid' => is_string($r->get('amv')->get('uuid'))?$r->get('amv')->get('uuid'):$r->get('amv')->get('uuid')->toString()]]);

                          foreach ($instanceRiskBrothers as $irb) {
                                $brotherRecoRisks = $this->get('recommandationRiskTable')->getEntityByFields([ // Get recommendation of brother
                                    'anr' => $anr->get('id'),
                                    'instanceRisk' => $irb->id,
                                    'instance' => ['op' => '!=', 'value' => $instanceId],
                                    'objectGlobal' => ['anr' => $anr->get('id'), 'uuid' => is_string($obj->get('uuid'))?$obj->get('uuid'):$obj->get('uuid')->toString()]]);

                                if (!empty($brotherRecoRisks)) {
                                      foreach ($brotherRecoRisks as $brr) {
                                            $RecoCreated= $this->get('recommandationRiskTable')->getEntityByFields([ // Check if reco-risk link exist
                                              'recommandation' => ['anr' => $anr->id, 'uuid' => is_string($brr->recommandation->uuid)?$brr->recommandation->uuid:$brr->recommandation->uuid->toString()],
                                              'instance' => $instanceId,
                                              'instanceRisk' => $r->get('id')]);

                                            if (empty($RecoCreated)) {// Creation of link reco -> risk
                                                    $class = $this->get('recommandationRiskTable')->getClass();
                                                    $rrb = new $class();
                                                    $rrb->setDbAdapter($this->get('recommandationRiskTable')->getDb());
                                                    $rrb->setLanguage($this->getLanguage());
                                                    $toExchange = [
                                                        'anr' => $anr->get('id'),
                                                        'recommandation' => $brr->recommandation->uuid->toString(),
                                                        'instanceRisk' => $r->get('id'),
                                                        'instance' => $instanceId,
                                                        'objectGlobal' => $brr->objectGlobal->uuid->toString(),
                                                        'asset' => $brr->asset->uuid->toString(),
                                                        'threat' => $brr->threat->uuid->toString(),
                                                        'vulnerability' => $brr->vulnerability->uuid->toString(),
                                                        'commentAfter' => $brr->commentAfter,
                                                        'op' => 0,
                                                        'risk' => $r->get('id'),
                                                    ];
                                                    $rrb->exchangeArray($toExchange);
                                                    $this->setDependencies($rrb, ['anr', 'recommandation', 'instanceRisk', 'instance', 'objectGlobal', 'asset', 'threat', 'vulnerability']);
                                                    $this->get('recommandationRiskTable')->save($rrb,false);

                                            }
                                      }
                                      $this->get('recommandationRiskTable')->getDb()->flush();
                                }
                          }
                  }
                }
                //$this->get('table')->getDb()->flush();

                // Check recommandations from specific risk of brothers
                $recoToCreate = [];
                $specificRisks = $this->get('instanceRiskTable')->getEntityByFields([ // Get all specific risks of instance
                    'anr' => $anr->get('id'),
                    'instance' => $instanceId,
                    'specific' => 1]);
                foreach ($specificRisks as $sr) {
                  $exitingRecoRisks = $this->get('recommandationRiskTable')->getEntityByFields([ // Get recommandations of brothers
                      'anr' => $anr->get('id'),
                      'asset' => ['anr' => $anr->get('id'), 'uuid' => $sr->get('asset')->get('uuid')->toString()],
                      'threat' => ['anr' => $anr->get('id'), 'uuid' => $sr->get('threat')->get('uuid')->toString()],
                      'vulnerability' => ['anr' => $anr->get('id'), 'uuid' => $sr->get('vulnerability')->get('uuid')->toString()]]);
                      foreach ($exitingRecoRisks as $err) {
                        if ($instanceId != $err->get('instance')->get('id')) {
                          $recoToCreate[] = $err;
                        }
                      }
                }
                foreach ($recoToCreate as $rtc) {
                  $RecoCreated = $this->get('recommandationRiskTable')->getEntityByFields([ // Check if reco-risk link exist
                    'recommandation' => ['anr' => $anr->get('id'), 'uuid' => $rtc->recommandation->uuid->toString()],
                    'instance' => $instanceId,
                    'asset' => ['anr' => $anr->get('id'), 'uuid' => $rtc->asset->uuid->toString()],
                    'threat' => ['anr' => $anr->get('id'), 'uuid' => $rtc->threat->uuid->toString()],
                    'vulnerability' => ['anr' => $anr->get('id'), 'uuid' => $rtc->vulnerability->uuid->toString()]]);

                  if (empty($RecoCreated)) {// Creation of link reco -> risk
                          $class = $this->get('recommandationRiskTable')->getClass();
                          $rrb = new $class();
                          $rrb->setDbAdapter($this->get('recommandationRiskTable')->getDb());
                          $rrb->setLanguage($this->getLanguage());
                          $toExchange = [
                              'anr' => $anr->get('id'),
                              'recommandation' => ['anr' => $anr->get('id'), 'uuid' => $rtc->recommandation->uuid->toString()],
                              'instanceRisk' => $idRiskSpecific = current($this->get('instanceRiskTable')->getEntityByFields([
                                                      'anr' => $anr->get('id'),
                                                      'instance' => $instanceId,
                                                      'specific' => 1,
                                                      'asset' => ['anr' => $anr->get('id'), 'uuid' => $rtc->asset->uuid->toString()],
                                                      'threat' => ['anr' => $anr->get('id'), 'uuid' => $rtc->threat->uuid->toString()],
                                                      'vulnerability' => ['anr' => $anr->get('id'), 'uuid' => $rtc->vulnerability->uuid->toString()]])),
                              'instance' => $instanceId,
                              'objectGlobal' => ['anr' => $anr->get('id'), 'uuid' => $rtc->objectGlobal->uuid->toString()],
                              'asset' =>['anr' => $anr->get('id'), 'uuid' =>  $rtc->asset->uuid->toString()],
                              'threat' => ['anr' => $anr->get('id'), 'uuid' => $rtc->threat->uuid->toString()],
                              'vulnerability' => ['anr' => $anr->get('id'), 'uuid' => $rtc->vulnerability->uuid->toString()],
                              'commentAfter' => $rtc->commentAfter,
                              'op' => 0,
                              'risk' => $idRiskSpecific,
                          ];
                          $rrb->exchangeArray($toExchange);
                          $this->setDependencies($rrb, ['anr', 'recommandation', 'instanceRisk', 'instance', 'objectGlobal', 'asset', 'threat', 'vulnerability']);
                          $this->get('recommandationRiskTable')->save($rrb,false);
                  }
                }
                $this->get('recommandationRiskTable')->getDb()->flush();

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
                        'netP',
                        'targetedR',
                        'targetedO',
                        'targetedL',
                        'targetedF',
                        'targetedP',
                    ],
                ];
                $toApproximate[\MonarcCore\Model\Entity\Scale::TYPE_THREAT][] = 'brutProb';
                $toApproximate[\MonarcCore\Model\Entity\Scale::TYPE_IMPACT][] = 'brutR';
                $toApproximate[\MonarcCore\Model\Entity\Scale::TYPE_IMPACT][] = 'brutO';
                $toApproximate[\MonarcCore\Model\Entity\Scale::TYPE_IMPACT][] = 'brutL';
                $toApproximate[\MonarcCore\Model\Entity\Scale::TYPE_IMPACT][] = 'brutF';
                $toApproximate[\MonarcCore\Model\Entity\Scale::TYPE_IMPACT][] = 'brutP';
                $k=0;

                //Recommandations Sets
                $uuidRecSet = '';
                if (!empty($data['recSets'])){
                    foreach ($data['recSets'] as $recSet_UUID => $recSet_array) {
                        // check if the recommendation set is not already present in the analysis
                        $recommandationsSets = $this->get('recommandationSetTable')
                                                ->getEntityByFields(['anr' => $anr->id, 'uuid' => $recSet_UUID]);
                        if (empty($recommandationsSets)) {
                            $newRecommandationSet = new \MonarcFO\Model\Entity\RecommandationSet($recSet_array);
                            $newRecommandationSet->setAnr($anr);
                            $sharedData['recSets'][$recSet_UUID] = $this->get('recommandationSetTable')->save($newRecommandationSet);
                        }
                    }
                }
                //2.8.3
                else if (version_compare($monarc_version, "2.8.2")==-1){
                    $recommandationsSets = $this->get('recommandationSetTable')
                                                ->getEntityByFields(['anr' => $anr->id, 'label1' => "Recommandations importées"]);
                    if(!empty($recommandationsSets)){
                        $uuidRecSet = $recommandationsSets[0]->uuid->toString();
                    }
                    else{
                        $toExchange = [
                        'anr' => $anr->get('id'),
                        'label1' => 'Recommandations importées',
                        'label2' => 'Imported recommendations',
                        'label3' => 'Importierte empfehlungen',
                        'label4' => 'Geïmporteerde aanbevelingen',
                        ];
                        $class = $this->get('recommandationSetTable')->getClass();
                        $rS = new $class();
                        $rS->setDbAdapter($this->get('recommandationSetTable')->getDb());
                        $rS->setLanguage($this->getLanguage());
                        $rS->exchangeArray($toExchange);
                        $this->setDependencies($rS, ['anr']);
                        $uuidRecSet = $this->get('recommandationSetTable')->save($rS);
                    }
                    
                }

                //Recommandations unlinked to a recommandation risk
                if(!empty($data['recs'])){
                    foreach ($data['recs'] as $rec_UUID => $rec_array) {
                        // check if the recommendation is not already present in the analysis
                        $recommandations = $this->get('recommandationTable')
                                                ->getEntityByFields(['anr' => $anr->id, 'uuid' => $rec_UUID]);
                        if (empty($recommandations)) {
                            $recSets = $this->get('recommandationSetTable')->getEntityByFields(['anr' => $anr->id, 'uuid' => $rec_array['recommandationSet']]);
                            $newRecommandation = new \MonarcFO\Model\Entity\Recommandation($rec_array);
                            $newRecommandation->setAnr($anr);
                            $newRecommandation->setRecommandationSet($recSets[0]);
                            $sharedData['recs'][$rec_UUID] = $this->get('recommandationTable')->save($newRecommandation);
                        }

                    }
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
                    $tagId = $this->get('objectExportService')->get('table')->getEntity(['anr' => $anr->get('id'),'uuid' =>$idObject])->get('rolfTag');
                    $rolfRisks = [];
                    if (null !== $tagId) {
                        $rolfRisks = $tagId->risks;
                        $toExchange['rolfRisk'] = $rolfRisks[$k]->id;
                        $toExchange['riskCacheCode'] = $rolfRisks[$k]->code;
                        $k++;
                    }

                    // traitement de l'évaluation -> c'est complètement dépendant des échelles locales
                    if ($include_eval) {
                        // pas d'impact des subscales, on prend les échelles nominales
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
                            //2.8.3
                            if (version_compare($monarc_version, "2.8.2")==-1){
                                unset($reco['id']);
                                $recs = $this->get('recommandationTable')->getEntityByFields(['code' => $reco['code'], 'description' => $reco['description']]);
                                if(!empty($recs)){
                                    $reco['uuid'] = $recs[0]->get('uuid')->toString();
                                }
                                $reco['recommandationSet'] = $uuidRecSet;
                            }
                            $recSets = $this->get('recommandationSetTable')->getEntityByFields(['anr' => $anr->id, 'uuid' => $reco['recommandationSet']]);
                            // La recommandation
                            if (isset($sharedData['recos'][$reco['uuid']])) {
                                // Cette recommandation a déjà été gérée dans cet import
                                if ($ro['kindOfMeasure'] != \MonarcCore\Model\Entity\InstanceRiskSuperClass::KIND_NOT_TREATED) {
                                    $aReco = $this->get('recommandationTable')->getEntity(['anr' => $anr->get('id'), 'uuid' => $reco['uuid']]);
                                    if ($aReco->get('position') <= 0 || is_null($aReco->get('position'))) {
                                        $pos = count($this->get('recommandationTable')->getEntityByFields(['anr' => $anr->get('id'), 'position' => ['op' => 'IS NOT', 'value' => null]], ['position' => 'ASC'])) + 1;
                                        $aReco->set('position', $pos);
                                        $aReco->setRecommandationSet($recSets[0]);
                                        $reco['uuid'] = $this->get('recommandationTable')->save($aReco);
                                    }
                                }
                            } else {
                                // sinon, on teste sa présence
                                $toExchange = $reco;
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
                                $aReco->exchangeArray($toExchange, $aReco->get('uuid'));
                                $this->setDependencies($aReco, ['anr']);
                                if(isset($toExchange['duedate']['date']))
                                  $aReco->setDueDate(new DateTime($toExchange['duedate']['date']));
                                $aReco->setRecommandationSet($recSets[0]);
                                $reco['uuid'] = $this->get('recommandationTable')->save($aReco);
                                $sharedData['recos'][$reco['uuid']] = $reco['uuid'];                            
                            }

                            // Le lien recommandation <-> risk
                            $class = $this->get('recommandationRiskTable')->getClass();
                            $rr = new $class();
                            $rr->setDbAdapter($this->get('recommandationRiskTable')->getDb());
                            $rr->setLanguage($this->getLanguage());
                            $toExchange = [
                                'anr' => $anr->get('id'),
                                'recommandation' => $reco['uuid'],
                                'instanceRiskOp' => $idRiskOp,
                                'instance' => $instanceId,
                                'objectGlobal' => (($obj && $obj->get('scope') == \MonarcCore\Model\Entity\ObjectSuperClass::SCOPE_GLOBAL) ? is_string($obj->get('uuid'))?$obj->get('uuid'):$obj->get('uuid')->toString() : null),
                                'asset' => null,
                                'threat' => null,
                                'vulnerability' => null,
                                'commentAfter' => $reco['commentAfter'],
                                'op' => 1,
                                'risk' => $idRiskOp,
                            ];
                            $rr->exchangeArray($toExchange);
                            $this->setDependencies($rr, ['anr', 'recommandation', 'instanceRiskOp', 'instance', 'objectGlobal', 'asset', 'threat', 'vulnerability']);
                            $rr->setAnr(null);
                            $this->get('recommandationRiskTable')->save($rr);
                            $rr->setAnr($anr);
                            $this->get('recommandationRiskTable')->save($rr, false);
                        }
                    }
                }
            }



            if (!empty($data['children'])) {
              usort($data['children'], function($a,$b){
                return $a['instance']['position'] <=> $b['instance']['position'];
              });
                foreach ($data['children'] as $child) {
                    $this->importFromArray($child, $anr, $instanceId, $modeImport, $include_eval, $sharedData); // and so on...
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
                    $idReco[$rr->recommandation->uuid] = $rr->recommandation->uuid;
                }
                if ($rr->instanceRiskOp && $rr->instanceRiskOp->kindOfMeasure != \MonarcCore\Model\Entity\InstanceRiskOpSuperClass::KIND_NOT_TREATED) {
                    $idReco[$rr->recommandation->uuid] = $rr->recommandation->uuid;
                }
            }

            if (!empty($idReco)) {
                // Retrieve recommandations
                /** @var RecommandationTable $recommandationTable */
                $recommandationTable = $this->get('recommandationTable');
                $recommandations = $recommandationTable->getEntityByFields(['anr' => $anr->id, 'uuid' => $idReco], ['importance' => 'DESC', 'code' => 'ASC']);

                $i = 1;
                $nbRecommandations = count($recommandations);
                foreach ($recommandations as $recommandation) {
                    $recommandation->position = $i;
                    $recommandationTable->save($recommandation, ($i == $nbRecommandations));
                    $i++;
                }
            }

            return $instanceId;
        } else if (isset($data['type']) && $data['type'] == 'anr'
        ) {

          // Method information
          if (!empty($data['method'])) { //Steps checkboxes
              if (!empty($data['method']['steps'])) {
                  $anrTable = $this->get('anrTable');
                  foreach ($data['method']['steps'] as $key => $v) {
                    if ($anr->get($key) === 0 ) {
                      $anr->set($key,$v);
                      $anrTable->save($anr,false);
                    }
                  }
                  $anrTable->getDb()->flush();
              }
              if (!empty($data['method']['data'])) { //Data of textboxes
                  $anrTable = $this->get('anrTable');
                  foreach ($data['method']['data'] as $key => $v) {
                    if (is_null($anr->get($key)) ) {
                      $anr->set($key,$v);
                      $anrTable->save($anr,false);
                    }
                  }
                  $anrTable->getDb()->flush();
              }
              if (!empty($data['method']['interviews'])) { //Data of interviews
                  foreach ($data['method']['interviews'] as $key => $v) {
                    $toExchange = $data['method']['interviews'][$key];
                    $toExchange['anr'] = $anr->get('id');
                    $class = $this->get('interviewTable')->getClass();
                    $newInterview = new $class();
                    $newInterview->setDbAdapter($this->get('interviewTable')->getDb());
                    $newInterview->setLanguage($this->getLanguage());
                    $newInterview->exchangeArray($toExchange);
                    $this->setDependencies($newInterview, ['anr']);
                    $this->get('interviewTable')->save($newInterview,false);
                  }
                  $this->get('interviewTable')->getDb()->flush();
              }
              if (!empty($data['method']['thresholds'])) { // Value of thresholds
                  $anrTable = $this->get('anrTable');
                  foreach ($data['method']['thresholds'] as $key => $v) {
                      $anr->set($key,$v);
                      $anrTable->save($anr,false);
                  }
                  $anrTable->getDb()->flush();
              }
              if (!empty($data['method']['deliveries'])) { // Data of deliveries generation
                  foreach ($data['method']['deliveries'] as $key => $v) {
                    $toExchange = $data['method']['deliveries'][$key];
                    $toExchange['anr'] = $anr->get('id');
                    $class = $this->get('deliveryTable')->getClass();
                    $newDelivery = new $class();
                    $newDelivery->setDbAdapter($this->get('deliveryTable')->getDb());
                    $newDelivery->setLanguage($this->getLanguage());
                    $newDelivery->exchangeArray($toExchange);
                    $this->setDependencies($newDelivery, ['anr']);
                    $this->get('deliveryTable')->save($newDelivery,false);
                  }
                  $this->get('deliveryTable')->getDb()->flush();
              }
              if (!empty($data['method']['questions'])) { // Questions of trends evaluation
                    $questions = $this->get('questionTable')->getEntityByFields(['anr' => $anr->id]);
                    foreach ($questions as $q) {
                    $this->get('questionTable')->delete($q->id);
                    }

                    $nbQuestions= count($data['method']['questions']);

                    foreach ($data['method']['questions'] as $q => $v) {

                      if ($data['method']['questions'][$q]['multichoice'] == 0){

                        $toExchange = $data['method']['questions'][$q];
                        $toExchange['anr'] = $anr->get('id');
                        $class = $this->get('questionTable')->getClass();
                        $newQuestion = new $class();
                        $newQuestion->setDbAdapter($this->get('questionTable')->getDb());
                        $newQuestion->setLanguage($this->getLanguage());
                        $newQuestion->exchangeArray($toExchange);
                        $newQuestion->set('position',$q);
                        $this->setDependencies($newQuestion, ['anr']);
                        $this->get('questionTable')->save($newQuestion);
                      } else { // Multichoice questions
                        $OldIdQuestion = $data['method']['questions'][$q]['id'];
                        $toExchange = $data['method']['questions'][$q];
                        $toExchange['anr'] = $anr->get('id');
                        $class = $this->get('questionTable')->getClass();
                        $newQuestion = new $class();
                        $newQuestion->setDbAdapter($this->get('questionTable')->getDb());
                        $newQuestion->setLanguage($this->getLanguage());
                        $newQuestion->exchangeArray($toExchange);
                        $newQuestion->set('position',$q);
                        $this->setDependencies($newQuestion, ['anr']);
                        $this->get('questionTable')->save($newQuestion);

                        foreach ($data['method']['questionChoice'] as $qc => $v ) { //Creation of Multichoice responses
                            if ($data['method']['questionChoice'][$qc]['question'] == $OldIdQuestion) {
                                $toExchange = $data['method']['questionChoice'][$qc];
                                $toExchange['anr'] = $anr->get('id');
                                $toExchange['question'] = $newQuestion->id;
                                $class = $this->get('questionChoiceTable')->getClass();
                                $newQuestionChoice = new $class();
                                $newQuestionChoice->setDbAdapter($this->get('questionChoiceTable')->getDb());
                                $newQuestionChoice->setLanguage($this->getLanguage());
                                $newQuestionChoice->exchangeArray($toExchange);
                                $this->setDependencies($newQuestionChoice, ['anr', 'question']);
                                $this->get('questionChoiceTable')->save($newQuestionChoice);
                            }
                        }
                      }
                    }

                    $questions = $this->get('questionTable')->getEntityByFields(['anr' => $anr->id]);

                    for ($pos=1; $pos <= $nbQuestions; $pos++) {
                    foreach ($questions as $q) {
                      if ($q->multichoice == 0){
                        if ($q->get('label' . $this->getLanguage()) == $data['method']['questions'][$pos]['label' . $this->getLanguage()] && $pos <= $nbQuestions) {
                          $q->response = $data['method']['questions'][$pos]['response'];
                          $this->get('questionTable')->save($q,($pos == $nbQuestions));
                          $pos++;
                        }
                      } else { // Match Multichoice responses
                        $replace = ["[","]"];
                        $OriginQc = [];
                        if(trim($data['method']['questions'][$pos]['response'])) {
                            $OriginQc = preg_split("/[,]/",str_replace($replace,"",$data['method']['questions'][$pos]['response']));
                        }
                        $NewQcIds = null;

                        foreach ($OriginQc as $qc ) {
                          file_put_contents('php://stderr', print_r($qc, TRUE).PHP_EOL);
                          file_put_contents('php://stderr', print_r($data['method']['questionChoice'], TRUE).PHP_EOL);
                          $DestQc[$qc] = $data['method']['questionChoice'][$qc];
                          $questionChoices = $this->get('questionChoiceTable')->getEntityByFields(['anr' => $anr->id , 'label' . $this->getLanguage() => $DestQc[$qc]['label' . $this->getLanguage()]]);
                          foreach ($questionChoices as $qc) {
                            $NewQcIds .= $qc->get('id') . ",";
                          }
                        }
                        $q->response = "[". substr($NewQcIds,0,-1) . "]";
                        $this->get('questionTable')->save($q,($pos == $nbQuestions));
                        $pos++;
                      }
                    }
                  }
              }
              if (!empty($data['method']['threats'])) { // Evaluation of threats
                    foreach ($data['method']['threats'] as $tId => $v) {
                      if (!empty($data['method']['threats'][$tId]['theme'])) {
                          $themes = $this->get('themeTable')->getEntityByFields(['anr' => $anr->id, 'label' . $this->getLanguage() => $data['method']['threats'][$tId]['theme']['label' . $this->getLanguage()]],['id' => 'ASC']);
                          if (empty($themes)) { // Creation of new theme if no exist
                            $toExchange = $data['method']['threats'][$tId]['theme'];
                            $toExchange['anr'] = $anr->get('id');
                            $class = $this->get('themeTable')->getClass();
                            $newTheme = new $class();
                            $newTheme->setDbAdapter($this->get('themeTable')->getDb());
                            $newTheme->setLanguage($this->getLanguage());
                            $newTheme->exchangeArray($toExchange);
                            $this->setDependencies($newTheme, ['anr']);
                            $this->get('themeTable')->save($newTheme);
                            $data['method']['threats'][$tId]['theme']['id'] = $newTheme->id;
                          } else {
                            foreach ($themes as $th) {
                              $data['method']['threats'][$tId]['theme']['id'] = $th->id;
                            }
                          }
                      }
                    $threats = $this->get('threatTable')->getEntityByFields(['anr' => $anr->id, 'code' => $data['method']['threats'][$tId]['code']],['uuid' => 'ASC']);
                    if (empty($threats)) {
                      $toExchange = $data['method']['threats'][$tId];
                      $toExchange['anr'] = $anr->get('id');
                      $toExchange['mode'] = 0;
                      $toExchange['theme'] = $data['method']['threats'][$tId]['theme']['id'];
                      $class = $this->get('threatTable')->getClass();
                      $newThreat = new $class();
                      $newThreat->setDbAdapter($this->get('threatTable')->getDb());
                      $newThreat->setLanguage($this->getLanguage());
                      $newThreat->exchangeArray($toExchange);
                      $this->setDependencies($newThreat, ['anr', 'theme']);
                      $this->get('threatTable')->save($newThreat);
                    } else {
                      foreach ($threats as $t) {
                        $t->set('trend', $data['method']['threats'][$tId]['trend']);
                        $t->set('comment', $data['method']['threats'][$tId]['comment']);
                        $t->set('qualification', $data['method']['threats'][$tId]['qualification']);
                        $this->get('threatTable')->save($t,false);
                      }
                      $this->get('threatTable')->getDb()->flush();
                    }
                    }
              }
          }
          // import the referentials
          if (isset($data['referentials'])) {
              foreach ($data['referentials'] as $referentialUUID => $referential_array) {
                  // check if the referential is not already present in the analysis
                  $referentials = $this->get('referentialTable')
                                        ->getEntityByFields(['anr' => $anr->id, 'uuid' => $referentialUUID]);
                  if (empty($referentials)) {
                      $newReferential = new \MonarcFO\Model\Entity\Referential($referential_array);
                      $newReferential->setAnr($anr);
                      $this->get('referentialTable')->save($newReferential);
                  }
              }
          }
          // import the soacategories
          if (isset($data['soacategories'])) {
              foreach ($data['soacategories'] as $soaCategory) {
                   // load the referential linked to the soacategory
                  $referentials = $this->get('referentialTable')
                                        ->getEntityByFields(['anr' => $anr->id,
                                        'uuid' => $soaCategory['referential']]);
                  $categories = $this->get('soaCategoryTable')
                                          ->getEntityByFields(['anr' => $anr->id,
                                          'label' . $this->getLanguage() => $soaCategory['label' . $this->getLanguage()],
                                          'referential' => ['anr' => $anr->id,
                                                            'uuid' => $referentials[0]->uuid]]);
                  if (empty($categories)) {
                    $newSoaCategory = new \MonarcFO\Model\Entity\SoaCategory($soaCategory);
                    $newSoaCategory->setAnr($anr);
                    $newSoaCategory->setReferential($referentials[0]);
                    $this->get('soaCategoryTable')->save($newSoaCategory,false);
                  };

              }
              $this->get('soaCategoryTable')->getDb()->flush();
          }
          // import the measures
          $measuresNewIds = [];
          if (isset($data['measures'])) {
              foreach ($data['measures'] as $measureUUID => $measure_array) {
                  // check if the measure is not already in the analysis
                  $measures = $this->get('measureTable')->getEntityByFields(['anr' => $anr->id, 'uuid' => $measureUUID]);
                  if (empty($measures)) {
                      // load the referential linked to the measure
                      $referentials = $this->get('referentialTable')
                                            ->getEntityByFields(['anr' => $anr->id,
                                            'uuid' => $measure_array['referential']]);
                      $soaCategories = $this->get('soaCategoryTable')
                                            ->getEntityByFields(['anr' => $anr->id,
                                                'label' . $this->getLanguage() => $measure_array['category']]);
                      if (!empty($referentials) && !empty($soaCategories)) {
                          // a measure must be linked to a referential and a category
                          $newMeasure = new \MonarcFO\Model\Entity\Measure($measure_array);
                          $newMeasure->setAnr($anr);
                          $newMeasure->setReferential($referentials[0]);
                          $newMeasure->setCategory($soaCategories[0]);
                          $newMeasure->amvs = new \Doctrine\Common\Collections\ArrayCollection; // need to initialize the amvs link
                          $newMeasure->rolfRisks = new \Doctrine\Common\Collections\ArrayCollection;
                          $this->get('measureTable')->save($newMeasure,false);
                          $measuresNewIds[$measureUUID] = $newMeasure;

                          if (! isset($data['soas'])) {
                              // if no SOAs in the analysis to import, create new ones
                              $newSoa = new \MonarcFO\Model\Entity\Soa();
                              $newSoa->setAnr($anr);
                              $newSoa->setMeasure($newMeasure);
                              $this->get('soaTable')->save($newSoa,false);
                          }
                      }
                  }
              }
              $this->get('measureTable')->getDb()->flush();
          }
          // import the measuresmeasures
          if (isset($data['measuresMeasures'])) {
              foreach ($data['measuresMeasures'] as $measureMeasure) {
                  // check if the measuremeasure is not already in the analysis
                  $measuresmeasures = $this->get('measureMeasureTable')
                                            ->getEntityByFields(['anr' => $anr->id,
                                                                'father' => $measureMeasure['father'],
                                                                'child' => $measureMeasure['child']]);
                  if (empty($measuresmeasures)) {
                      $newMeasureMeasure = new \MonarcFO\Model\Entity\MeasureMeasure($measureMeasure);
                      $newMeasureMeasure->setAnr($anr);
                      $this->get('measureMeasureTable')->save($newMeasureMeasure,false);
                  }
              }
              $this->get('measureMeasureTable')->getDb()->flush();
          }
          // import the SOAs
          if (isset($data['soas'])) {
            $measuresStoredId = $this->get('measureTable')->fetchAllFiltered(['uuid'],1,0,null,null,['anr'=>$anr->get('id')],null,null);
            $measuresStoredId  = array_map(function($elt){return is_string($elt['uuid'])?$elt['uuid']:$elt['uuid']->toString();},$measuresStoredId);
              foreach ($data['soas'] as $soa) {
                  // check if the corresponding measure has been created during
                  // this import
                  if (array_key_exists($soa['measure_id'], $measuresNewIds)) {
                      $newSoa = new \MonarcFO\Model\Entity\Soa($soa);
                      $newSoa->setAnr($anr);
                      $newSoa->setMeasure($measuresNewIds[$soa['measure_id']]);
                      $this->get('soaTable')->save($newSoa,false);
                  }else if (in_array($soa['measure_id'], $measuresStoredId)){ //measure exist so soa exist (normally)
                      $soaExistant = $this->get('soaTable')->getEntityByFields(['measure'=>['anr' => $anr->id, 'uuid' => $soa['measure_id']]]);
                      if(empty($soaExistant)){
                        $newSoa = new \MonarcFO\Model\Entity\Soa($soa);
                        $newSoa->setAnr($anr);
                        $newSoa->setMeasure($this->get('measureTable')->getEntity(['anr' => $anr->id, 'uuid' => $soa['measure_id']]));
                        $this->get('soaTable')->save($newSoa,false);
                      }else{
                        $soaExistant = $soaExistant[0];
                        $soaExistant->remarks = $soa['remarks'];
                        $soaExistant->evidences = $soa['evidences'];
                        $soaExistant->actions = $soa['actions'];
                        $soaExistant->compliance = $soa['compliance'];
                        $soaExistant->EX = $soa['EX'];
                        $soaExistant->LR = $soa['LR'];
                        $soaExistant->CO = $soa['CO'];
                        $soaExistant->BR = $soa['BR'];
                        $soaExistant->BP = $soa['BP'];
                        $soaExistant->RRA = $soa['RRA'];
                        $this->get('soaTable')->save($soaExistant,false);
                      }
                  }
              }
              $this->get('soaTable')->getDb()->flush();
          }
          // import the GDPR records
          if (!empty($data['records'])) { //Data of records
              foreach ($data['records'] as $v) {
                  $this->get('recordService')->importFromArray($v,$anr->get('id'));
              }
          }
          // import scales
          if (!empty($data['scales'])) {
            //Approximate values from destination analyse
            $ts = ['c', 'i', 'd'];
            $instances = $this->get('table')->getEntityByFields(['anr' => $anr->id]);
            $consequences = $this->get('instanceConsequenceTable')->getEntityByFields(['anr' => $anr->id]);
            $scalesOrig = [];
            $scales = $this->get('scaleTable')->getEntityByFields(['anr' => $anr->id]);
            foreach ($scales as $sc) {
                $scalesOrig[$sc->get('type')]['min'] = $sc->get('min');
                $scalesOrig[$sc->get('type')]['max'] = $sc->get('max');
            }

            $minScaleImpOrig = $scalesOrig[\MonarcCore\Model\Entity\Scale::TYPE_IMPACT]['min'];
            $maxScaleImpOrig = $scalesOrig[\MonarcCore\Model\Entity\Scale::TYPE_IMPACT]['max'];
            $minScaleImpDest = $data['scales'][\MonarcCore\Model\Entity\Scale::TYPE_IMPACT]['min'];
            $maxScaleImpDest = $data['scales'][\MonarcCore\Model\Entity\Scale::TYPE_IMPACT]['max'];

            //Instances
            foreach ($ts as $t) {
              foreach ($instances as $instance) {
                  if ($instance->get($t . 'h')) {
                      $instance->set($t . 'h', 1);
                      $instance->set($t, -1);
                  } else {
                      $instance->set($t . 'h', 0);
                      $instance->set($t, $this->approximate(
                          $instance->get($t),
                          $minScaleImpOrig,
                          $maxScaleImpOrig,
                          $minScaleImpDest,
                          $maxScaleImpDest
                      ));
                  }
                $this->refreshImpactsInherited($anr->id, $instance->parent? $instance->parent->id: 0,$instance);
              }
              //Impacts & Consequences
              foreach ($consequences as $conseq) {
                $conseq->set($t, $conseq->isHidden ? -1 : $this->approximate(
                  $conseq->get($t),
                  $minScaleImpOrig,
                  $maxScaleImpOrig,
                  $minScaleImpDest,
                  $maxScaleImpDest
                ));
                $this->get('instanceConsequenceTable')->save($conseq);
              }
            }

            // Threat Qualification
              $threats = $this->get('threatTable')->getEntityByFields(['anr' => $anr->get('id')]);
              foreach ($threats as $t) {
              $t->set('qualification', $this->approximate(
                $t->get('qualification'),
                $scalesOrig[\MonarcCore\Model\Entity\Scale::TYPE_THREAT]['min'],
                $scalesOrig[\MonarcCore\Model\Entity\Scale::TYPE_THREAT]['max'],
                $data['scales'][\MonarcCore\Model\Entity\Scale::TYPE_THREAT]['min'],
                $data['scales'][\MonarcCore\Model\Entity\Scale::TYPE_THREAT]['max']
              ));
              $this->get('threatTable')->save($t);
            }

            // Information Risks
            $risks = $this->get('instanceRiskService')->get('table')->getEntityByFields(['anr' => $anr->get('id')]);
            foreach ($risks as $r) {
              $r->set('threatRate', $this->approximate(
                $r->get('threatRate'),
                $scalesOrig[\MonarcCore\Model\Entity\Scale::TYPE_THREAT]['min'],
                $scalesOrig[\MonarcCore\Model\Entity\Scale::TYPE_THREAT]['max'],
                $data['scales'][\MonarcCore\Model\Entity\Scale::TYPE_THREAT]['min'],
                $data['scales'][\MonarcCore\Model\Entity\Scale::TYPE_THREAT]['max']
              ));
              $oldVulRate = $r->vulnerabilityRate;
              $r->set('vulnerabilityRate', $this->approximate(
                $r->get('vulnerabilityRate'),
                $scalesOrig[\MonarcCore\Model\Entity\Scale::TYPE_VULNERABILITY]['min'],
                $scalesOrig[\MonarcCore\Model\Entity\Scale::TYPE_VULNERABILITY]['max'],
                $data['scales'][\MonarcCore\Model\Entity\Scale::TYPE_VULNERABILITY]['min'],
                $data['scales'][\MonarcCore\Model\Entity\Scale::TYPE_VULNERABILITY]['max']
                            ));
              $newVulRate = $r->vulnerabilityRate;
              $r->set('reductionAmount', ($r->get('reductionAmount') != 0) ? $this->approximate(
                $r->get('reductionAmount'),
                0,
                $oldVulRate,
                0,
                $newVulRate,
                0) : 0);
                $this->get('instanceRiskService')->update($r->id,$risks);
            }

            //Operational Risks
            $risksOp = $this->get('instanceRiskOpService')->get('table')->getEntityByFields(['anr' => $anr->get('id')]);
            if (!empty($risksOp)) {
              foreach ($risksOp as $rOp) {
                $toApproximate = [
                    \MonarcCore\Model\Entity\Scale::TYPE_THREAT => [
                        'netProb',
                        'targetedProb',
                        'brutProb',
                    ],
                    \MonarcCore\Model\Entity\Scale::TYPE_IMPACT => [
                        'netR',
                        'netO',
                        'netL',
                        'netF',
                        'netP',
                        'brutR',
                        'brutO',
                        'brutL',
                        'brutF',
                        'brutP',
                        'targetedR',
                        'targetedO',
                        'targetedL',
                        'targetedF',
                        'targetedP',
                    ],
                ];
                    foreach ($toApproximate as $type => $list) {
                        foreach ($list as $i) {
                            $rOp->set($i, $this->approximate(
                                $rOp->get($i),
                                $scalesOrig[$type]['min'],
                                $scalesOrig[$type]['max'],
                                $data['scales'][$type]['min'],
                                $data['scales'][$type]['max']
                            ));
                        }
                    }
              $this->get('instanceRiskOpService')->update($rOp->id,$risksOp);
              }
            }

            // Finally update scales from import
            $scales = $this->get('scaleTable')->getEntityByFields(['anr' => $anr->id]);
            $types = [];
            $types = [
                \MonarcCore\Model\Entity\Scale::TYPE_IMPACT,
                \MonarcCore\Model\Entity\Scale::TYPE_THREAT,
                \MonarcCore\Model\Entity\Scale::TYPE_VULNERABILITY,
            ];
            foreach ($types as $type) {
              foreach ($scales as $s) {
                if ($s->type == $type) {
                    $s->min = $data['scales'][$type]['min'];
                    $s->max = $data['scales'][$type]['max'];
                }
              }
            }
          }

            $first = true;
            $instanceIds = [];
            $nbScaleImpactTypes = count($this->get('scaleImpactTypeTable')->getEntityByFields(['anr' => $anr->id]));
            usort($data['instances'], function($a,$b){
              return $a['instance']['position'] <=> $b['instance']['position'];
            });
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
            if (!empty($data['scalesComments'])) { // Scales comments
                  $pos = 1;
                  $siId = null;
                  $scIds = null;
                  $sId = null;

                  foreach ($data['scalesComments'] as $sc) {
                      $scIds[$pos] = $sc['id'];
                      $pos++;
                  }
                  $scaleComment = $this->get('scaleCommentTable')->getEntityByFields(['anr' => $anr->id],['id' => 'ASC']);
                  foreach ($scaleComment as $sc) {
                    if (is_null($sc->scaleImpactType) || $sc->scaleImpactType->isSys == 1) {
                      $this->get('scaleCommentTable')->delete($sc->id);
                    }
                  }
                  $nbComment= count($data['scalesComments']);

                  for ($pos=1; $pos <= $nbComment; $pos++) {
                    $scale = $this->get('scaleTable')->getEntityByFields(['anr' => $anr->id, 'type' => $data['scalesComments'][$scIds[$pos]]['scale']['type']]);
                      foreach ($scale as $s) {
                        $sId = $s->get('id');
                      }
                    $OrigPosition = (isset($data['scalesComments'][$scIds[$pos]]['scaleImpactType']['position'])) ? $data['scalesComments'][$scIds[$pos]]['scaleImpactType']['position'] : 0;
                    $position = ($OrigPosition > 8) ? $OrigPosition + ($nbScaleImpactTypes - 8) : $OrigPosition;
                    $scaleImpactType = $this->get('scaleImpactTypeTable')->getEntityByFields(['anr' => $anr->id, 'position' => $position ]);
                      foreach ($scaleImpactType as $si) {
                        $siId = $si->get('id');
                      }
                    $toExchange = $data['scalesComments'][$scIds[$pos]];
                    $toExchange['anr'] = $anr->get('id');
                    $toExchange['scale'] = $sId;
                    $toExchange['scaleImpactType'] = $siId;
                    $this->get('scaleCommentService')->create($toExchange);
                  }
            }

            //Add user consequences to all instances
            $instances = $this->get('table')->getEntityByFields(['anr' => $anr->id]);
            $scaleImpactTypes = $this->get('scaleImpactTypeTable')->getEntityByFields(['anr' => $anr->id]);
             foreach ($instances as $instance) {
               foreach ($scaleImpactTypes as $siType ) {
                 $instanceConsequence = $this->get('instanceConsequenceTable')->getEntityByFields(['anr' => $anr->id, 'instance' => $instance->id, 'scaleImpactType' => $siType->id]);
                 if (empty($instanceConsequence)) {
                   $class = $this->get('instanceConsequenceTable')->getClass();
                   $consequence = new $class();
                   $consequence->setDbAdapter($this->get('instanceConsequenceTable')->getDb());
                   $consequence->setLanguage($this->getLanguage());
                   $consequence->exchangeArray([
                       'anr' => $anr->get('id'),
                       'instance' => $instance->id,
                       'object' => $instance->object,
                       'scaleImpactType' => $siType->id,
                   ]);
                   $this->setDependencies($consequence, ['anr', 'object', 'instance', 'scaleImpactType']);
                   $this->get('instanceConsequenceTable')->save($consequence,false);
                 }
               }
             }
             $this->get('instanceConsequenceTable')->getDb()->flush();

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
