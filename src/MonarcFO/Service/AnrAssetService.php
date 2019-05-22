<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

/**
 * This class is the service that handles assets in use within an ANR.
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
    protected $instanceRiskTable;
    protected $MonarcObjectTable;
    protected $instanceTable;
    protected $soaCategoryCommonTable;
    protected $referentialCommonTable;
    protected $referentialTable;
    protected $soaTable;
    protected $dependencies = ['anr'];
    protected $filterColumns = [
        'label1', 'label2', 'label3', 'label4',
        'description1', 'description2', 'description3', 'description4',
        'code',
    ];

    /**
     * Imports an asset that has been exported into a file.
     * @param int $anrId The target ANR ID
     * @param array $data The data that has been posted to the API (password, file)
     * @return array An array where the first key is an array of generated IDs, and the second the eventual errors
     * @throws \MonarcCore\Exception\Exception If the posted data is invalid, or ANR ID is ivalid
     */
    public function importFromFile($anrId, $data)
    {
        // We can have multiple files imported with the same password (we'll emit warnings if the password mismatches)
        if (empty($data['file'])) {
            throw new \MonarcCore\Exception\Exception('File missing', 412);
        }

        $ids = $errors = [];
        $anr = $this->get('anrTable')->getEntity($anrId); // throws MonarcCore\Exception\Exception if invalid

        foreach ($data['file'] as $f) {
            if (isset($f['error']) && $f['error'] === UPLOAD_ERR_OK && file_exists($f['tmp_name'])) {
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

                if ($file !== false && ($id = $this->get('objectExportService')->importFromArray($file, $anr)) !== false) {
                    $ids[] = $id;
                } else {
                    $errors[] = 'The file "' . $f['name'] . '" can\'t be imported';
                }
            }
        }

        return [$ids, $errors];
    }

    /**
     * Imports an asset from a data array. This data is generally what has been exported into a file.
     * @param string $monarc_version version of monarc which the assets come from
     * @param array $data The asset's data fields
     * @param \MonarcFO\Model\Entity\Anr $anr The target ANR entity
     * @param array $objectsCache An object cache array reference to speed up processing
     * @return bool|int The ID of the generated asset, or false if an error occurred.
     */
    public function importFromArray($monarc_version,$data, $anr, &$objectsCache = [])
    {
        if(isset($data['type']) && $data['type'] == 'asset')
        {
          $referentialPresent = false;

          if (version_compare ($monarc_version, "2.8.2")>=0 ) { //TO DO:set the right value with the uuid version
            // Lookup if we already have the same asset, in which case we'll update it from the data. Otherwise,
            // we'll create a new one.
            $asset = current($this->get('table')->getEntityByFields(['anr' => $anr->get('id'), 'uuid' => $data['asset']['uuid']]));
            if (!empty($asset)) {
                $idAsset = is_string($asset->get('uuid'))?$asset->get('uuid'):$asset->get('uuid')->toString();
            } else {
                $c = $this->get('table')->getClass();
                $asset = new $c();
                $asset->setDbAdapter($this->get('table')->getDb());
                $asset->setLanguage($this->getLanguage());
                $asset->exchangeArray($data['asset']);
                $asset->set('anr', $anr->get('id'));
                $this->setDependencies($asset, ['anr']);
                $idAsset = $this->get('table')->save($asset);
            }

            // Match the AMV Links with the asset
            $newAmvs = [];
            if (!empty($data['amvs']) && !empty($idAsset)) {
              /*****
              * THREATS : uuid is the pivot for theme label is the pivot
              ******/
                $localThemes = [];
                $themes = $this->get('themeTable')->getEntityByFields(['anr' => $anr->get('id')]);
                foreach ($themes as $t) {
                    $localThemes[$t->get('label' . $this->getLanguage())] = $t;
                }
                unset($themes);

                foreach ($data['threats'] as $keyThreat => $valueThreat) {
                  $threat = $this->get('threatTable')->getEntityByFields(['anr' => $anr->get('id'), 'uuid' => $keyThreat]);
                  if ($threat) { // the threat exists
                      $threat = current($threat);
                      $newTheme = null;

                      // check the theme
                      $theme = $threat->get('theme');
                        if(isset($valueThreat['theme']) && !is_null($valueThreat['theme'])){ //a theme is present in the data sent
                          if(is_null($theme) || $theme->get('label' . $this->getLanguage()) != $data['themes'][$valueThreat['theme']]['label' . $this->getLanguage()]) //the theme is not the same
                          {
                            if(isset($localThemes[$data['themes'][$valueThreat['theme']]['label' . $this->getLanguage()]])){ //theme exists
                              $newTheme = $localThemes[$data['themes'][$valueThreat['theme']]['label' . $this->getLanguage()]];
                            }else {
                              $c = $this->get('themeTable')->getClass();
                              $newTheme = new $c();
                              $newTheme->setDbAdapter($this->get('themeTable')->getDb());
                              $newTheme->setLanguage($this->getLanguage());
                              $newTheme->exchangeArray($data['themes'][$valueThreat['theme']]);
                              $newTheme->set('anr', $anr->get('id'));
                              $this->setDependencies($newTheme, ['anr']);
                              $idTheme = $this->get('themeTable')->save($newTheme);
                            }
                            $threat->set('theme', $newTheme);
                            $this->setDependencies($threat, ['theme']);
                            $this->get('threatTable')->save($threat);
                          }
                        }
                    }else { //threat doesn't exist
                      $newTheme = null;
                      if(isset($localThemes[$data['themes'][$valueThreat['theme']]['label' . $this->getLanguage()]])){ //theme exists
                        $newTheme = $localThemes[$data['themes'][$valueThreat['theme']]['label' . $this->getLanguage()]];
                      }else if(is_null($data['themes'][$valueThreat['theme']])){
                        $newTheme = null;
                      }
                      else{ //theme doesn't exist
                        $c = $this->get('themeTable')->getClass();
                        $newTheme = new $c();
                        $newTheme->setDbAdapter($this->get('themeTable')->getDb());
                        $newTheme->setLanguage($this->getLanguage());
                        $newTheme->exchangeArray($data['themes'][$valueThreat['theme']]);
                        $newTheme->set('anr', $anr->get('id'));
                        $this->setDependencies($newTheme, ['anr']);
                        $idTheme = $this->get('themeTable')->save($newTheme);
                      }
                      $c = $this->get('threatTable')->getClass();
                      $newThreat = new $c();
                      $newThreat->setDbAdapter($this->get('threatTable')->getDb());
                      $newThreat->setLanguage($this->getLanguage());
                      $newThreat->exchangeArray($valueThreat);
                      $newThreat->set('anr', $anr->get('id'));
                      $newThreat->set('theme', $newTheme);
                      $this->setDependencies($newThreat, ['anr', 'theme']);
                      $idThreat = $this->get('threatTable')->save($newThreat);
                      $objectsCache['threats'][$newThreat->uuid] =  $newThreat->uuid;
                    }
                  }

              /*****
              * VULNERABILITIES : uuid is the pivot
              ******/
                foreach ($data['vuls'] as $keyVul => $valueVul) {
                  $vulnerability = $this->get('vulnerabilityTable')->getEntityByFields(['anr' => $anr->get('id'), 'uuid' => $keyVul]);
                  if (!$vulnerability) {//the vulnerability doesn't exist
                    $c = $this->get('vulnerabilityTable')->getClass();
                    $newVul = new $c();
                    $newVul->setDbAdapter($this->get('vulnerabilityTable')->getDb());
                    $newVul->setLanguage($this->getLanguage());
                    $newVul->exchangeArray($valueVul);
                    $newVul->set('anr', $anr->get('id'));
                    $this->setDependencies($newVul, ['anr']);
                    $idVul = $this->get('vulnerabilityTable')->save($newVul);
                    $objectsCache['vuls'][$newVul->uuid] =  $newVul->uuid;
                  }
                }

              /*****
              * AMVS : uuid is the pivot
              ******/
                foreach ($data['amvs'] as $keyAmv => $valueAmv) {
                  $amv = $this->get('amvTable')->getEntityByFields(['anr' => $anr->get('id'), 'uuid' => $keyAmv]);
                  if (!$amv) {//the $amv doesn't exist
                    // file_put_contents('php://stderr', print_r('$valueAmv', TRUE).PHP_EOL);
                    // file_put_contents('php://stderr', print_r($valueAmv, TRUE).PHP_EOL);
                    $c = $this->get('amvTable')->getClass();
                    $newAmv = new $c();
                    $newAmv->setDbAdapter($this->get('amvTable')->getDb());
                    $newAmv->setLanguage($this->getLanguage());
                    $newAmv->exchangeArray($valueAmv);
                    $newAmv->set('anr', $anr->get('id'));
                    $newAmv->set('asset', $asset);
                    $newAmv->set('measures', null);
                    $this->setDependencies($newAmv, ['anr','asset','threat','vulnerability','measures']);

                    $idAmv = $this->get('amvTable')->save($newAmv);
                    $newAmvs[] = $keyAmv;
                    // link the measures we only link the measures if they are in the DB (potential copyright issue)
                    foreach ($valueAmv['measures'] as $m) {
                      try{
                        $measure = $this->get('measureTable')->getEntity(['anr'=>$anr->id , 'uuid' =>$m]);
                        $measure->addAmv($newAmv);
                      }catch (\MonarcCore\Exception\Exception $e) {}
                    }

                    //update instances
                    $MonarcObjectTable = $this->get('MonarcObjectTable');
                    $objects = $MonarcObjectTable->getEntityByFields(['anr' => $anr->get('id'), 'asset' => ['anr' => $anr->get('id'), 'uuid' =>$idAsset]]);
                    foreach ($objects as $object) {
                        /** @var InstanceTable $instanceTable */
                        $instanceTable = $this->get('instanceTable');
                        $instances = $instanceTable->getEntityByFields(['anr' => $anr->get('id'), 'object' => ['anr' => $anr->get('id'), 'uuid' => $object->get('uuid')->toString()]]);
                        $i = 1;
                        $nbInstances = count($instances);
                        foreach ($instances as $instance) {
                            $c = $this->get('instanceRiskTable')->getClass();
                            $instanceRisk = new $c();
                            $instanceRisk->setLanguage($this->getLanguage());
                            $instanceRisk->setDbAdapter($this->get('instanceRiskTable')->getDb());
                            $instanceRisk->set('anr', $anr->get('id'));
                            $instanceRisk->set('amv', $newAmv);
                            $instanceRisk->set('asset', $idAsset);
                            $instanceRisk->set('instance', $instance);
                            $instanceRisk->set('threat', $valueAmv['threat']);
                            $instanceRisk->set('vulnerability', $valueAmv['vulnerability']);
                            $this->setDependencies($instanceRisk, ['anr', 'amv', 'asset', 'threat', 'vulnerability']);

                            $this->get('instanceRiskTable')->save($instanceRisk, ($i == $nbInstances));
                            $i++;
                        }
                    }
                  }else { //the amv exists
                    $newAmvs[] = $keyAmv;
                  }
                }

                //set the olds amvs to specific and delete them
                $amvsToDelete = [];
                $oldAmvs = $this->get('amvTable')->getEntityByFields(['anr' => $anr->get('id'), 'asset' => ['anr' => $anr->get('id'),'uuid' => $idAsset]]);
                foreach ($oldAmvs as $oldAmv) {
                  if(!in_array($oldAmv->getUuid(), $newAmvs)){ //it's an old amv

                      //we fetch the instances risks wich contains the amv to set the risk to specific
                      $oldIRs = $this->get('instanceRiskTable')->getEntityByFields(['anr' => $anr->get('id'), 'amv' => ['anr' => $anr->get('id'), 'uuid' => $oldAmv->getUuid()->toString()]]);
                      foreach ($oldIRs as $oldIR) {
                        $oldIR->set('amv',null);
                        $oldIR->set('anr',null);
                        $oldIR->set('specific',1);
                        $this->get('instanceRiskTable')->save($oldIR, false);
                      }
                      $this->get('instanceRiskTable')->getDb()->flush();
                      foreach ($oldIRs as $oldIR) { //set the value DB becausse the set amv=null erase the value
                        $oldIR->set('anr',$anr);
                        $this->get('instanceRiskTable')->save($oldIR, false);
                      }
                      $this->get('instanceRiskTable')->getDb()->flush();
                      $amvsToDelete[] = ['uuid' => $oldAmv->getUuid()->toString(), 'anr' => $anr->get('id')];
                  }
                }

                if(!empty($amvsToDelete)){
                  $this->get('amvTable')->deleteList($amvsToDelete);
                }
              }
            return $idAsset;

          }

          else{ //old version
              // Lookup if we already have the same asset, in which case we'll update it from the data. Otherwise,
              // we'll create a new one.
              $asset = current($this->get('table')->getEntityByFields(['anr' => $anr->get('id'), 'code' => $data['asset']['code']]));
              if (!empty($asset)) {
                  $idAsset = $asset->get('uuid')->ToString();
              } else {
                  $c = $this->get('table')->getClass();
                  $asset = new $c();
                  $asset->setDbAdapter($this->get('table')->getDb());
                  $asset->setLanguage($this->getLanguage());
                  $asset->exchangeArray($data['asset']);
                  $asset->set('anr', $anr->get('id'));
                  $this->setDependencies($asset, ['anr']);
                  $idAsset = $this->get('table')->save($asset);
              }

              // Match the AMV Links with the asset
              $localAmv = [];
              if (!empty($data['amvs']) && !empty($idAsset)) {
                  $localThemes = [];
                  $themes = $this->get('themeTable')->getEntityByFields(['anr' => $anr->get('id')]);
                  foreach ($themes as $t) {
                      $localThemes[$t->get('label' . $this->getLanguage())] = $t->get('id');
                  }
                  unset($themes);

                  foreach ($data['amvs'] as $amvArray) {
                      $amvData = [
                          'asset' => $idAsset,
                          'anr' => $anr->get('id'),
                          'status' => $amvArray['status'],
                      ];
                      if (isset($data['threats'][$amvArray['threat']])) { // Threats
                          if (is_array($data['threats'][$amvArray['threat']])) {
                              // Theme
                              $idTheme = null;
                              if (!empty($data['threats'][$amvArray['threat']]['theme']) && !empty($data['themes'][$data['threats'][$amvArray['threat']]['theme']])) {
                                  $t = $data['themes'][$data['threats'][$amvArray['threat']]['theme']];
                                  if (isset($localThemes[$t['label' . $this->getLanguage()]])) {
                                      $idTheme = $data['themes'][$data['threats'][$amvArray['threat']]['theme']]['newid'] = $localThemes[$t['label' . $this->getLanguage()]];
                                  } elseif (!empty($data['themes'][$data['threats'][$amvArray['threat']]['theme']]['newid'])) {
                                      $idTheme = $localThemes[$t['label' . $this->getLanguage()]] = $data['themes'][$data['threats'][$amvArray['threat']]['theme']]['newid'];
                                  } else {
                                      $c = $this->get('themeTable')->getClass();
                                      $theme = new $c();
                                      $theme->setDbAdapter($this->get('themeTable')->getDb());
                                      $theme->setLanguage($this->getLanguage());
                                      $t['id'] = null;
                                      $theme->exchangeArray($t);
                                      $theme->set('anr', $anr->get('id'));
                                      $this->setDependencies($theme, ['anr']);
                                      $idTheme = $this->get('themeTable')->save($theme);
                                      $localThemes[$t['label' . $this->getLanguage()]] = $data['themes'][$data['threats'][$amvArray['threat']]['theme']]['newid'] = $idTheme;
                                  }
                              }

                              $threat = $this->get('threatTable')->getEntityByFields(['anr' => $anr->get('id'), 'code' => $data['threats'][$amvArray['threat']]['code']]);
                              if ($threat) {
                                  $threat = current($threat);
                                  $data['threats'][$amvArray['threat']] = $threat->get('uuid')->toString();

                                  // Update du theme
                                  $theme = $threat->get('theme');
                                  $oldTheme = empty($theme) ? null : $theme->get('id');
                                  if ($oldTheme != $idTheme) {
                                      $threat->set('theme', $idTheme);
                                      $this->setDependencies($threat, ['anr', 'theme']);
                                      $this->get('threatTable')->save($threat);
                                  }
                              } else {
                                  $c = $this->get('threatTable')->getClass();
                                  $threat = new $c();
                                  $threat->setDbAdapter($this->get('threatTable')->getDb());
                                  $threat->setLanguage($this->getLanguage());
                                  $data['threats'][$amvArray['threat']]['id'] = null;

                                  $data['threats'][$amvArray['threat']]['theme'] = $idTheme;
                                  $threat->exchangeArray($data['threats'][$amvArray['threat']]);
                                  $threat->set('anr', $anr->get('id'));
                                  $this->setDependencies($threat, ['anr', 'theme']);
                                  $objectsCache['threats'][$amvArray['threat']] = $data['threats'][$amvArray['threat']] = $this->get('threatTable')->save($threat);
                              }
                          }
                          $amvData['threat'] = $data['threats'][$amvArray['threat']];
                      }

                      if (isset($data['vuls'][$amvArray['vulnerability']])) { // Vulnerabilities
                          if (is_array($data['vuls'][$amvArray['vulnerability']])) {
                              $vul = $this->get('vulnerabilityTable')->getEntityByFields(['anr' => $anr->get('id'), 'code' => $data['vuls'][$amvArray['vulnerability']]['code']]);
                              if ($vul) {
                                  $vul = current($vul);
                                  $data['vuls'][$amvArray['vulnerability']] = $vul->get('uuid')->toString();
                              } else {
                                  $c = $this->get('vulnerabilityTable')->getClass();
                                  $vul = new $c();
                                  $vul->setDbAdapter($this->get('vulnerabilityTable')->getDb());
                                  $vul->setLanguage($this->getLanguage());
                                  $data['vuls'][$amvArray['vulnerability']]['id'] = null;
                                  $vul->exchangeArray($data['vuls'][$amvArray['vulnerability']]);
                                  $vul->set('anr', $anr->get('id'));
                                  $this->setDependencies($vul, ['anr']);
                                  $objectsCache['vuls'][$amvArray['vulnerability']] = $data['vuls'][$amvArray['vulnerability']] = $this->get('vulnerabilityTable')->save($vul);
                              }
                          }
                          $amvData['vulnerability'] = $data['vuls'][$amvArray['vulnerability']];
                      }
                        if(array_key_exists ( 'measure1' , $amvArray)){ //old version without uuid
                          //we need to create ISO 27002, we check if the common if it's present or not
                            $referential = false;
                            $referentialCli = current($this->get('referentialTable')->getEntityByFields(['anr' => $anr->id, 'uuid' =>'98ca84fb-db87-11e8-ac77-0800279aaa2b']));
                            if(!$referentialCli)
                              $referential = current($this->get('referentialCommonTable')->getEntityByFields(['uuid' =>'98ca84fb-db87-11e8-ac77-0800279aaa2b']));
                            if($referential){
                              $measures = $referential->getMeasures();
                              $referential->setMeasures(null);

                              // duplicate the referential
                              $newReferential = new \MonarcFO\Model\Entity\Referential($referential);
                              $newReferential->setAnr($anr);

                              // duplicate categories
                              $categoryNewIds = [];
                              $category = $referential->categories;
                              foreach ($category as $cat) {
                                  $newCategory = new \MonarcFO\Model\Entity\SoaCategory($cat);
                                  $newCategory->set('id', null);
                                  $newCategory->setAnr($anr);
                                  $newCategory->setMeasures(null);
                                  $newCategory->setReferential($newReferential);
                                  $categoryNewIds[$cat->id] = $newCategory;
                              }

                              $newReferential->setCategories($categoryNewIds);

                              // duplicate the measures
                              $measuresNewIds = [];
                              foreach ($measures as $measure) {
                                  // duplicate and link the measures to the current referential
                                  $newMeasure = new \MonarcFO\Model\Entity\Measure($measure);
                                  $newMeasure->setAnr($anr);
                                  $newMeasure->setReferential($newReferential);
                                  $newMeasure->setCategory($categoryNewIds[$measure->category->id]);
                                  $newMeasure->rolfRisks = new \Doctrine\Common\Collections\ArrayCollection;
                                  $newMeasure->amvs =  new \Doctrine\Common\Collections\ArrayCollection; // need to initialize the amvs link
                                  $newMeasure->setMeasuresLinked(null); //old analysis can't have measuresLinked
                                  $this->get('measureTable')->save($newMeasure,false);
                                  $newSoa = new \MonarcFO\Model\Entity\Soa();
                                  $newSoa->setAnr($anr);
                                  $newSoa->setMeasure($newMeasure);
                                  $this->get('soaTable')->save($newSoa,false);
                              }
                              $referentialPresent = true;
                              $this->get('measureTable')->getDb()->flush();
                            }

                          for ($i = 1; $i <= 3; $i++) {
                            if (isset($data['measures'][$amvArray['measure' . $i]])) { // Measure 1 / 2 / 3
                                if (is_array($data['measures'][$amvArray['measure' . $i]])) {
                                    $measure = $this->get('measureTable')->getEntityByFields(['anr' => $anr->get('id'), 'code' => $data['measures'][$amvArray['measure' . $i]]['code']]);
                                    if ($measure) {
                                        $measure = current($measure);
                                        $data['measures'][$amvArray['measure' . $i]] = $measure->get('uuid')->toString();
                                     } //else {
                                    //     $c = $this->get('measureTable')->getClass();
                                    //     $measure = new $c();
                                    //     $measure->setDbAdapter($this->get('measureTable')->getDb());
                                    //     $measure->setLanguage($this->getLanguage());
                                    //     $data['measures'][$amvArray['measure' . $i]]['id'] = null;
                                    //     if (array_key_exists('description' . $this->getLanguage(),$data['measures'][$amvArray['measure' . $i]])) {
                                    //       for ($j=1; $j <= 4; $j++) {
                                    //         $data['measures'][$amvArray['measure' . $i]]['label'. $j] = $data['measures'][$amvArray['measure' . $i]]['description' . $j];
                                    //       }
                                    //     }
                                    //     $measure->exchangeArray($data['measures'][$amvArray['measure' . $i]]);
                                    //     $measure->set('anr', $anr->get('id'));
                                    //     $this->setDependencies($measure, ['anr']);
                                    //     $objectsCache['measures'][$amvArray['measure' . $i]] = $data['measures'][$amvArray['measure' . $i]] = $this->get('measureTable')->save($measure);
                                    // }
                                }
                                $amvData['measures'][] = $data['measures'][$amvArray['measure' . $i]];
                             } //else  {
                            //     $amvData['measures'] = null;
                            // }
                        }
                      }

                      $amvTest = current($this->get('amvTable')->getEntityByFields([
                          'anr' => $anr->get('id'),
                          'asset' => ['anr' => $anr->get('id'),'uuid' => $amvData['asset']],
                          'threat' => ['anr' => $anr->get('id'),'uuid' => $amvData['threat']],
                          'vulnerability' => ['anr' => $anr->get('id'),'uuid' => $amvData['vulnerability']],
                      ]));
                      if (empty($amvTest)) { // on test que cet AMV sur cette ANR n'existe pas
                          $c = $this->get('amvTable')->getClass();
                          $amv = new $c();
                          $amv->setDbAdapter($this->get('amvTable')->getDb());
                          $amv->setLanguage($this->getLanguage());
                          $measuresAmvs = (array_key_exists ( 'measures' , $amvData)) ? $amvData['measures'] : null;
                          unset($amvData['measures']);
                          $amv->exchangeArray($amvData, true);
                          $this->setDependencies($amv, ['anr', 'asset', 'threat', 'vulnerability',]);
                          $idAmv = $this->get('amvTable')->save($amv);
                          if(isset($amvArray['measures'])){ //version with uuid
                            foreach ($amvArray['measures'] as $m) {
                              try{
                                $measure = $this->get('measureTable')->getEntity(['anr'=>$anr->id , 'uuid' =>$m]);
                                $measure->addAmv($amv);
                              }catch (\MonarcCore\Exception\Exception $e) {}
                            }
                          }else if(isset($measuresAmvs)){ // old version without uuid
                            foreach ($measuresAmvs as $m) {
                              try{
                                $measure = $this->get('measureTable')->getEntity(['anr'=>$anr->id , 'uuid' =>$m]);
                                $measure->addAmv($amv);
                              }catch (\MonarcCore\Exception\Exception $e) {}
                            }
                          }
                          $localAmv[] = $idAmv;

                          // On met à jour les instances
                          $MonarcObjectTable = $this->get('MonarcObjectTable');
                          $objects = $MonarcObjectTable->getEntityByFields(['anr' => $anr->get('id'), 'asset' => ['anr' => $anr->get('id'), 'uuid' =>$idAsset]]);
                          foreach ($objects as $object) {
                              /** @var InstanceTable $instanceTable */
                              $instanceTable = $this->get('instanceTable');
                              $instances = $instanceTable->getEntityByFields(['anr' => $anr->get('id'), 'object' => ['anr' => $anr->get('id'), 'uuid' => $object->get('uuid')->toString()]]);
                              $i = 1;
                              $nbInstances = count($instances);
                              foreach ($instances as $instance) {
                                  $c = $this->get('instanceRiskTable')->getClass();
                                  $instanceRisk = new $c();
                                  $instanceRisk->setLanguage($this->getLanguage());
                                  $instanceRisk->setDbAdapter($this->get('instanceRiskTable')->getDb());
                                  $instanceRisk->set('anr', $anr->get('id'));
                                  $instanceRisk->set('amv', $idAmv);
                                  $instanceRisk->set('asset', $amvData['asset']);
                                  $instanceRisk->set('instance', $instance);
                                  $instanceRisk->set('threat', $amvData['threat']);
                                  $instanceRisk->set('vulnerability', $amvData['vulnerability']);
                                  $this->setDependencies($instanceRisk, ['anr', 'amv', 'asset', 'threat', 'vulnerability']);

                                  $this->get('instanceRiskTable')->save($instanceRisk, ($i == $nbInstances));
                                  $i++;
                              }
                          }
                      } else {
                          $localAmv[] = $amvTest->get('uuid')->toString();
                          if(isset($amvArray['measures'])){ //version with uuid
                            foreach ($amvArray['measures'] as $m) {
                              try{
                                $measure = $this->get('measureTable')->getEntity(['anr'=>$anr->id , 'uuid' =>$m]);
                                $measure->addAmv($amvTest);
                              }catch (\MonarcCore\Exception\Exception $e) {}
                            }
                          }else if(isset($amvData['measures'])){ //old version before uuid
                            foreach ($amvData['measures'] as $m) {
                              try{
                                $measure = $this->get('measureTable')->getEntity(['anr'=>$anr->id , 'uuid' =>$m]);
                                $measure->addAmv($amvTest);
                              }catch (\MonarcCore\Exception\Exception $e) {}
                            }
                          }
                      }
                  }
              }

              /*
              On teste si des liens AMVs différents étaient présents, si oui
              on passe les risques liés en spécifiques et on supprime les liens AMVs
              */
              if (empty($localAmv)) {
                   $risks = $this->get('instanceRiskTable')->getEntityByFields(['asset'=>['anr'=>$anr->get('id'),'uuid' => $idAsset], 'anr'=>$anr->get('id'),
                   'amv' => ['op' => '!=', 'value' =>['anr'=>$anr->get('id'), 'uuid' => null]]]);
              } else {
                  $risks = $this->get('instanceRiskTable')->getEntityByFields(['asset'=>['anr'=>$anr->get('id'),'uuid' => $idAsset], 'anr'=>$anr->get('id'),
                  'amv' => ['op' => 'NOT IN', 'value' =>['anr'=>$anr->get('id'),'uuid' => $localAmv]]]);
              }
              if (!empty($risks)) {
                  $amvs = [];
                  foreach ($risks as $a) {
                      $amv = $a->get('amv');
                      if (!empty($amv)) {
                          $amvs[] = ['anr'=>$anr->get('id'), 'uuid' => $amv->getUuid()->toString()];
                          $a->set('amv', null);
                          $a->set('anr', null);
                          $this->get('instanceRiskTable')->save($a);
                          $a->setAnr($anr); //TO IMPROVE we set the anr because it's delete by the line before amv = [uuid + anr]
                          $a->set('specific', 1);
                          $this->get('instanceRiskTable')->save($a);
                      }
                  }
                  if (!empty($amvs)) {
                      $this->get('amvTable')->deleteList($amvs);
                  }
              }
              if(empty($risks)){
                  $amvs = $this->get('amvTable')->getEntityByFields([
                      'asset' => ['anr'=>$anr->get('id'), 'uuid' => $idAsset],
                      'uuid' => ['op' => 'NOT IN', 'value' => $localAmv],
                  ]);
                  $idsAmv = [];
                  foreach($amvs as $amv){
                      $idsAmv[] = ['anr'=>$anr->get('id'), 'uuid' => $amv->getUuid()->toString()];
                  }
                  if (!empty($idsAmv)) {
                      $this->get('amvTable')->deleteList($idsAmv);
                  }
              }

              return $idAsset;
          }
        }
        return false;
    }
}
