<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
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
    protected $objectTable;
    protected $instanceTable;
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
     * @throws \Exception If the posted data is invalid, or ANR ID is ivalid
     */
    public function importFromFile($anrId, $data)
    {
        // Ensure we either have a password, or an empty string (and not 'null')
        $key = empty($data['password']) ? '' : $data['password'];

        // We can have multiple files imported with the same password (we'll emit warnings if the password mismatches)
        if (empty($data['file'])) {
            throw new \Exception('File missing', 412);
        }

        $ids = $errors = [];
        $anr = $this->get('anrTable')->getEntity($anrId); // throws Exception if invalid

        foreach ($data['file'] as $f) {
            if (isset($f['error']) && $f['error'] === UPLOAD_ERR_OK && file_exists($f['tmp_name'])) {
                $file = json_decode(trim($this->decrypt(base64_decode(file_get_contents($f['tmp_name'])), $key)), true);
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
     * @param array $data The asset's data fields
     * @param \MonarcFO\Model\Entity\Anr $anr The target ANR entity
     * @param array $objectsCache An object cache array reference to speed up processing
     * @return bool|int The ID of the generated asset, or false if an error occurred.
     */
    public function importFromArray($data, $anr, &$objectsCache = [])
    {
        // Ensure that we're importing an asset and that it has been exported from the same app version it's being
        // imported into (this is NOT a backup feature!)
        if (isset($data['type']) && $data['type'] == 'asset' &&
            array_key_exists('version', $data) && $data['version'] == $this->getVersion()
        ) {
            // Lookup if we already have the same asset, in which case we'll update it from the data. Otherwise,
            // we'll create a new one.
            $asset = current($this->get('table')->getEntityByFields(['anr' => $anr->get('id'), 'code' => $data['asset']['code']]));
            if (!empty($asset)) {
                $idAsset = $asset->get('id');
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
                                $data['threats'][$amvArray['threat']] = $threat->get('id');

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
                                $data['vuls'][$amvArray['vulnerability']] = $vul->get('id');
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

                    for ($i = 1; $i <= 3; $i++) {
                        if (isset($data['measures'][$amvArray['measure' . $i]])) { // Measure 1 / 2 / 3
                            if (is_array($data['measures'][$amvArray['measure' . $i]])) {
                                $measure = $this->get('measureTable')->getEntityByFields(['anr' => $anr->get('id'), 'code' => $data['measures'][$amvArray['measure' . $i]]['code']]);
                                if ($measure) {
                                    $measure = current($measure);
                                    $data['measures'][$amvArray['measure' . $i]] = $measure->get('id');
                                } else {
                                    $c = $this->get('measureTable')->getClass();
                                    $measure = new $c();
                                    $measure->setDbAdapter($this->get('measureTable')->getDb());
                                    $measure->setLanguage($this->getLanguage());
                                    $data['measures'][$amvArray['measure' . $i]]['id'] = null;
                                    $measure->exchangeArray($data['measures'][$amvArray['measure' . $i]]);
                                    $measure->set('anr', $anr->get('id'));
                                    $this->setDependencies($measure, ['anr']);
                                    $objectsCache['measures'][$amvArray['measure' . $i]] = $data['measures'][$amvArray['measure' . $i]] = $this->get('measureTable')->save($measure);
                                }
                            }
                            $amvData['measure' . $i] = $data['measures'][$amvArray['measure' . $i]];
                        } else {
                            $amvData['measure' . $i] = null;
                        }
                    }

                    $amvTest = current($this->get('amvTable')->getEntityByFields([
                        'anr' => $anr->get('id'),
                        'asset' => $amvData['asset'],
                        'threat' => $amvData['threat'],
                        'vulnerability' => $amvData['vulnerability'],
                    ]));
                    if (empty($amvTest)) { // on test que cet AMV sur cette ANR n'existe pas
                        $c = $this->get('amvTable')->getClass();
                        $amv = new $c();
                        $amv->setDbAdapter($this->get('amvTable')->getDb());
                        $amv->setLanguage($this->getLanguage());
                        $amv->exchangeArray($amvData, true);
                        $this->setDependencies($amv, ['anr', 'asset', 'threat', 'vulnerability', 'measure1', 'measure2', 'measure3']);
                        $idAmv = $this->get('amvTable')->save($amv);
                        $localAmv[] = $idAmv;

                        // On met à jour les instances
                        $objectTable = $this->get('objectTable');
                        $objects = $objectTable->getEntityByFields(['anr' => $anr->get('id'), 'asset' => $idAsset]);
                        foreach ($objects as $object) {
                            /** @var InstanceTable $instanceTable */
                            $instanceTable = $this->get('instanceTable');
                            $instances = $instanceTable->getEntityByFields(['anr' => $anr->get('id'), 'object' => $object->get('id')]);
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
                        $localAmv[] = $amvTest->get('id');
                    }
                }
            }

            /*
            On teste si des liens AMVs différents étaient présents, si oui
            on passe les risques liés en spécifiques et on supprime les liens AMVs
            */
            if (empty($localAmv)) {
                $risks = $this->get('instanceRiskTable')->getEntityByFields(['asset'=>$idAsset, 'anr'=>$anr->get('id'), 'amv' => ['op' => 'IS NOT', 'value' => null]]);
            } else {
                $risks = $this->get('instanceRiskTable')->getEntityByFields(['asset'=>$idAsset, 'anr'=>$anr->get('id'), 'amv' => ['op' => 'NOT IN', 'value' => $localAmv]]);
            }
            if (!empty($risks)) {
                $amvs = [];
                foreach ($risks as $a) {
                    $amv = $a->get('amv');
                    if (!empty($amv)) {
                        $amvs[$amv->getId()] = $amv->getId();
                        $a->set('amv', null);
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
                    'asset' => $idAsset,
                    'id' => ['op' => 'NOT IN', 'value' => $localAmv],
                ]);
                $idsAmv = [];
                foreach($amvs as $amv){
                    $idsAmv[$amv->get('id')] = $amv->get('id');
                }
                if (!empty($idsAmv)) {
                    $this->get('amvTable')->deleteList($idsAmv);
                }
            }

            return $idAsset;
        }
        return false;
    }
}