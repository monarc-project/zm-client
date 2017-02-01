<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

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
     * Import From File
     *
     * @param $anrId
     * @param $data
     * @return array
     * @throws \Exception
     */
    public function importFromFile($anrId, $data)
    {
        // on a bien un pwd (ou vide)
        $key = empty($data['password']) ? '' : $data['password'];
        // On aura la possibilité d'avoir plusieurs fichiers (même pwd: si un fichier ne match pas, on renvoie un warning)
        if (empty($data['file'])) {
            throw new \Exception('File missing', 412);
        }
        $ids = $errors = [];
        $anr = $this->get('anrTable')->getEntity($anrId); // on a une erreur si inconnue
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
     * Import From Array
     *
     * @param $data
     * @param $anr
     * @param array $objectsCache
     * @return bool
     */
    public function importFromArray($data, $anr, &$objectsCache = [])
    {
        if (isset($data['type']) && $data['type'] == 'asset' &&
            array_key_exists('version', $data) && $data['version'] == $this->getVersion()
        ) {
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
                $risks = $this->get('instanceRiskTable')->getEntityByFields(['amv' => ['op' => 'IS NOT', 'value' => null]]);
            } else {
                $risks = $this->get('instanceRiskTable')->getEntityByFields(['amv' => ['op' => 'NOT IN', 'value' => $localAmv]]);
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

            return $idAsset;
        }
        return false;
    }
}