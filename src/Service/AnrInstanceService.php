<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\OptimisticLockException;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Model\Entity\AbstractEntity;
use Monarc\Core\Model\Entity\InstanceRiskOpSuperClass;
use Monarc\Core\Model\Entity\InstanceRiskSuperClass;
use Monarc\Core\Model\Entity\InstanceSuperClass;
use Monarc\Core\Model\Entity\MonarcObject;
use Monarc\Core\Model\Entity\QuestionChoiceSuperClass;
use Monarc\Core\Model\Entity\QuestionSuperClass;
use Monarc\Core\Model\Entity\Scale;
use Monarc\Core\Service\InstanceService;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Delivery;
use Monarc\FrontOffice\Model\Entity\Instance;
use Monarc\FrontOffice\Model\Entity\InstanceConsequence;
use Monarc\FrontOffice\Model\Entity\InstanceRisk;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOp;
use Monarc\FrontOffice\Model\Entity\Interview;
use Monarc\FrontOffice\Model\Entity\Measure;
use Monarc\FrontOffice\Model\Entity\MeasureMeasure;
use Monarc\FrontOffice\Model\Entity\Question;
use Monarc\FrontOffice\Model\Entity\QuestionChoice;
use Monarc\FrontOffice\Model\Entity\Recommandation;
use Monarc\FrontOffice\Model\Entity\RecommandationRisk;
use Monarc\FrontOffice\Model\Entity\RecommandationSet;
use Monarc\FrontOffice\Model\Entity\Referential;
use Monarc\FrontOffice\Model\Entity\Soa;
use Monarc\FrontOffice\Model\Entity\SoaCategory;
use Monarc\FrontOffice\Model\Entity\Theme;
use Monarc\FrontOffice\Model\Entity\Threat;
use Monarc\FrontOffice\Model\Entity\Vulnerability;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\DeliveryTable;
use Monarc\FrontOffice\Model\Table\InstanceConsequenceTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskOpTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskTable;
use Monarc\FrontOffice\Model\Table\InstanceTable;
use Monarc\FrontOffice\Model\Table\MeasureMeasureTable;
use Monarc\FrontOffice\Model\Table\MeasureTable;
use Monarc\FrontOffice\Model\Table\MonarcObjectTable;
use Monarc\FrontOffice\Model\Table\QuestionChoiceTable;
use Monarc\FrontOffice\Model\Table\QuestionTable;
use Monarc\FrontOffice\Model\Table\RecommandationRiskTable;
use Monarc\FrontOffice\Model\Table\RecommandationSetTable;
use Monarc\FrontOffice\Model\Table\RecommandationTable;
use Monarc\FrontOffice\Model\Table\ReferentialTable;
use Monarc\FrontOffice\Model\Table\ScaleImpactTypeTable;
use Monarc\FrontOffice\Model\Table\ScaleTable;
use Monarc\FrontOffice\Model\Table\SoaCategoryTable;
use Monarc\FrontOffice\Model\Table\SoaTable;
use Monarc\FrontOffice\Model\Table\ThemeTable;
use Monarc\FrontOffice\Model\Table\ThreatTable;
use Monarc\FrontOffice\Model\Table\UserAnrTable;
use Monarc\FrontOffice\Model\Table\VulnerabilityTable;
use Monarc\FrontOffice\Service\Traits\RecommendationsPositionsUpdateTrait;
use Ramsey\Uuid\Uuid;
use DateTime;

/**
 * This class is the service that handles instances in use within an ANR. Inherits most of the behavior from its
 * Monarc\Core parent class.
 * @package Monarc\FrontOffice\Service
 */
class AnrInstanceService extends InstanceService
{
    use RecommendationsPositionsUpdateTrait;

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
    protected $instanceRiskOpTable;
    protected $referentialTable;
    protected $soaCategoryTable;
    protected $measureTable;
    protected $measureMeasureTable;
    protected $soaTable;
    protected $recordService;

    /** @var string|null */
    private $monarcVersion;

    /** @var array */
    private $sharedData = [];

    /** @var int */
    private $currentAnalyseMaxRecommendationPosition;

    /** @var int */
    private $initialAnalyseMaxRecommendationPosition;

    public function delete($id)
    {
        /** @var InstanceTable $instanceTable */
        $instanceTable = $this->get('table');
        $anr = $instanceTable->findById($id)->getAnr();

        parent::delete($id);

        // Reset related recommendations positions to 0.
        /** @var RecommandationTable $recommendationTable */
        $recommendationTable = $this->get('recommandationTable');
        $unlinkedRecommendations = $recommendationTable->findUnlinkedWithNotEmptyPositionByAnr($anr);
        $recommendationsToResetPositions = [];
        foreach ($unlinkedRecommendations as $unlinkedRecommendation) {
            $recommendationsToResetPositions[$unlinkedRecommendation->getUuid()] = $unlinkedRecommendation;
        }
        if (!empty($recommendationsToResetPositions)) {
            $this->resetRecommendationsPositions($anr, $recommendationsToResetPositions);
        }
    }

    /**
     * Imports a previously exported instance from an uploaded file into the current ANR. It may be imported using two
     * different modes: 'merge', which will update the existing instances using the file's data, or 'duplicate' which
     * will create a new instance using the data.
     *
     * @param int $anrId The ANR ID
     * @param array $data The data that has been posted to the API
     *
     * @return array An array where the first key is the generated IDs, and the second are import errors
     * @throws Exception If the uploaded data is invalid, or the ANR invalid
     */
    public function importFromFile($anrId, $data)
    {
        // Mode may either be 'merge' or 'duplicate'
        $mode = empty($data['mode']) ? 'merge' : $data['mode'];

        // The object may be imported at the root, or under an existing instance in the ANR instances tree
        $parentInstance = null;
        if (!empty($data['idparent'])) {
            /** @var InstanceTable $instanceTable */
            $instanceTable = $this->get('table');
            $parentInstance = $instanceTable->findById($data['idparent']);
        }

        // We can have multiple files imported with the same password (we'll emit warnings if the password mismatches)
        if (empty($data['file'])) {
            throw new Exception('File missing', 412);
        }
        $ids = [];
        $errors = [];
        /** @var AnrTable $anrTable */
        $anrTable = $this->get('anrTable');
        $anr = $anrTable->findById($anrId);

        // When importing huge instances trees, Zend can take up a whole lot of memory
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', -1);

        foreach ($data['file'] as $keyfile => $f) {
            // Ensure the file has been uploaded properly, silently skip the files that are erroneous
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

                /** @var RecommandationTable $recommendationTable */
                $recommendationTable = $this->get('recommandationTable');
                $this->initialAnalyseMaxRecommendationPosition = $recommendationTable->getMaxPositionByAnr($anr);
                $this->currentAnalyseMaxRecommendationPosition = $this->initialAnalyseMaxRecommendationPosition;
                if ($file !== false
                    && ($id = $this->importFromArray($file, $anr, $parentInstance, $mode)) !== false) {
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

        return [$ids, $errors];
    }

    /**
     * Imports an instance from an exported data (json) array.
     *
     * @param array $data The instance data
     * @param Anr $anr The target ANR
     * @param null|Instance $parent The parent under which the instance should be imported or null if it is root.
     * @param string $modeImport Import mode, either 'merge' or 'duplicate'
     * @param bool $isRoot If the imported instance should be treated as a root instance
     *
     * @return array|bool An array of created instances IDs, or false in case of error
     * @throws Exception
     */
    public function importFromArray(
        array $data,
        Anr $anr,
        ?Instance $parent = null,
        string $modeImport = 'merge',
        bool $isRoot = false
    ) {
        if (isset($data['monarc_version'])) {
            $this->monarcVersion = strpos($data['monarc_version'], 'master') === false ? $data['monarc_version'] : '99';
        }

        // Ensure we're importing an instance, from the same version (this is NOT a backup feature!)
        if (isset($data['type']) && $data['type'] === 'instance') {
            return $this->importInstanceFromArray($data, $anr, $parent, $modeImport, $isRoot);
        }

        if (isset($data['type']) && $data['type'] === 'anr') {
            return $this->importAnrFromArray($data, $anr, $parent, $modeImport, $isRoot);
        }

        return false;
    }

    /**
     * @return bool|int
     * @throws Exception
     */
    private function importInstanceFromArray(
        array $data,
        Anr $anr,
        ?Instance $parent,
        string $modeImport,
        bool $isRoot = false
    ) {
        /** @var AnrInstanceRiskService $instanceRiskService */
        $instanceRiskService = $this->get('instanceRiskService');
        /** @var InstanceConsequenceTable $instanceConsequenceTable */
        $instanceConsequenceTable = $this->get('instanceConsequenceTable');

        $monarcVersion = $this->getMonarcVersion();

        // On teste avant tout que l'on peux importer le fichier dans cette instance (level != LEVEL_INTER)
        if ($isRoot && $parent !== null) {
            // On en profite pour vérifier qu'on n'importe pas le fichier dans une instance qui n'appartient
            // pas à l'ANR passée en param
            if ($parent->getLevel() === InstanceSuperClass::LEVEL_INTER
                || $parent->getAnr() !== $anr
            ) {
                return false;
            }
        }

        // On s'occupe de l'évaluation. Le $data['scales'] n'est présent que sur la première instance, il ne l'est
        // plus après donc ce code n'est exécuté qu'une seule fois.
        $localScaleImpact = null;
        $includeEval = !empty($data['with_eval']);
        if ($includeEval && !empty($data['scales'])) {
            /** @var ScaleTable $scaleTable */
            $scaleTable = $this->get('scaleTable');
            $scales = $scaleTable->findByAnr($anr);
            $this->sharedData['scales']['dest'] = [];
            $this->sharedData['scales']['orig'] = $data['scales'];

            foreach ($scales as $scale) {
                if ($scale->getType() === Scale::TYPE_IMPACT) {
                    // utile pour la gestion des conséquences
                    $localScaleImpact = $scale;
                }
                $this->sharedData['scales']['dest'][$scale->get('type')] = [
                    'min' => $scale->getMin(),
                    'max' => $scale->getMax(),
                ];
            }
            unset($scales);
        }

        // Import the object
        if (!isset($this->sharedData['objects'])) {
            $this->sharedData['objects'] = [];
        }
        /** @var ObjectExportService $objectExportService */
        $objectExportService = $this->get('objectExportService');
        // TODO: We should not pass the shared data, as it can be heavy, use ObjectExportService prop for the purpose.
        // TODO: instead of object UUID return the object itself then we don't need to query the db.
        $idObject = $objectExportService->importFromArray($data['object'], $anr, $modeImport, $this->sharedData);
        if (!$idObject) {
            return false;
        }

        /** @var InstanceTable $instanceTable */
        $instanceTable = $this->get('table');

        $instance = new Instance();

        // TODO: use setters and get rid of exchangeArray!

        $instance->setDbAdapter($instanceTable->getDb());
        $instance->setLanguage($this->getLanguage());
        $toExchange = $data['instance'];
        unset($toExchange['id']);
        unset($toExchange['position']);
        $toExchange['asset'] = null;
        /** @var MonarcObjectTable $monarcObjectTable */
        $monarcObjectTable = $this->get('objectTable');
        $monarcObject = $monarcObjectTable->findByAnrAndUuid($anr, $idObject);
        if ($modeImport === 'duplicate') {
            for ($i = 1; $i <= 4; $i++) {
                $toExchange['name' . $i] = $monarcObject->get('name' . $i);
            }
        }
        $toExchange['implicitPosition'] = AbstractEntity::IMP_POS_END;
        if ($isRoot) {
            // On force en level "ROOT" lorsque c'est le 1er niveau de l'import. Pour les autres, on laisse les
            // levels définis de l'export
            $toExchange['level'] = InstanceSuperClass::LEVEL_ROOT;
        }

        $instance->exchangeArray($toExchange);

        $instance->setAnr($anr)
            ->setObject($monarcObject)
            ->setAsset($monarcObject->getAsset())
            ->setParent($parent);

        $instanceTable->saveEntity($instance);

        $instanceRiskService->createInstanceRisks($instance, $anr, $monarcObject);

        $labelKey = 'label' . $this->getLanguage();

        // Gestion des conséquences
        if ($includeEval) {
            $ts = ['c', 'i', 'd'];
            foreach ($ts as $t) {
                if ($instance->get($t . 'h')) {
                    $instance->set($t . 'h', 1);
                    $instance->set($t, -1);
                } else {
                    $instance->set($t . 'h', 0);
                    $instance->set($t, $this->approximate(
                        $instance->get($t),
                        $this->sharedData['scales']['orig'][Scale::TYPE_IMPACT]['min'],
                        $this->sharedData['scales']['orig'][Scale::TYPE_IMPACT]['max'],
                        $this->sharedData['scales']['dest'][Scale::TYPE_IMPACT]['min'],
                        $this->sharedData['scales']['dest'][Scale::TYPE_IMPACT]['max']
                    ));
                }
            }

            if (!empty($data['consequences'])) {
                if (empty($localScaleImpact)) {
                    $localScaleImpact = current($this->get('scaleTable')->getEntityByFields([
                        'anr' => $anr->getId(),
                        'type' => Scale::TYPE_IMPACT
                    ]));
                }
                $scalesImpactType = $this->get('scaleImpactTypeTable')->getEntityByFields(['anr' => $anr->getId()]);
                $localScalesImpactType = [];
                foreach ($scalesImpactType as $scale) {
                    $localScalesImpactType[$scale->get($labelKey)] = $scale->get('id');
                }
                unset($scalesImpactType);

                foreach ($data['consequences'] as $conseq) {
                    if (!isset($localScalesImpactType[$conseq['scaleImpactType'][$labelKey]])) {
                        $toExchange = $conseq['scaleImpactType'];
                        unset($toExchange['id']);
                        $toExchange['anr'] = $anr->getId();
                        $toExchange['scale'] = $localScaleImpact->get('id');
                        $toExchange['implicitPosition'] = 2;

                        $class = $this->get('scaleImpactTypeTable')->getEntityClass();
                        $scaleImpT = new $class();
                        $scaleImpT->setDbAdapter($instanceTable->getDb());
                        $scaleImpT->setLanguage($this->getLanguage());
                        $scaleImpT->exchangeArray($toExchange);
                        $this->setDependencies($scaleImpT, ['anr', 'scale']);
                        $localScalesImpactType[$conseq['scaleImpactType'][$labelKey]]
                            = $this->get('scaleImpactTypeTable')->save($scaleImpT, false);
                    }

                    $ts = ['c', 'i', 'd'];

                    // Maintenant on peut alimenter le tableau de conséquences comme si ça venait d'un formulaire
                    foreach ($ts as $t) {
                        $conseq[$t] = $conseq['isHidden'] ? -1 : $this->approximate(
                            $conseq[$t],
                            $this->sharedData['scales']['orig'][Scale::TYPE_IMPACT]['min'],
                            $this->sharedData['scales']['orig'][Scale::TYPE_IMPACT]['max'],
                            $this->sharedData['scales']['dest'][Scale::TYPE_IMPACT]['min'],
                            $this->sharedData['scales']['dest'][Scale::TYPE_IMPACT]['max']
                        );
                    }
                    $toExchange = $conseq;
                    unset($toExchange['id']);
                    $toExchange['anr'] = $anr->getId();
                    $toExchange['instance'] = $instance->getId();
                    $toExchange['object'] = $idObject;
                    $toExchange['scale'] = $localScaleImpact->get('id');
                    $toExchange['scaleImpactType'] =
                        $localScalesImpactType[$conseq['scaleImpactType'][$labelKey]];
                    $class = $instanceConsequenceTable->getEntityClass();
                    $consequence = new $class();
                    $consequence->setDbAdapter($instanceConsequenceTable->getDb());
                    $consequence->setLanguage($this->getLanguage());
                    $consequence->exchangeArray($toExchange);
                    $this->setDependencies($consequence, ['anr', 'object', 'instance', 'scaleImpactType']);
                    $instanceConsequenceTable->save($consequence, false);
                }

                $instanceConsequenceTable->getDb()->flush();
            }
        } else {
            $this->createInstanceConsequences($instance->getId(), $anr->getId(), $monarcObject);
        }

        /*
         * Update impacts from brothers for global assets.
         */
        $instanceBrothers = current($instanceTable->getEntityByFields([
            'anr' => $anr->getId(),
            'id' => [
                'op' => '!=',
                'value' => $instance->getId(),
            ],
            'asset' => [
                'anr' => $anr->getId(),
                'uuid' => $instance->getAsset()->getUuid(),
            ],
            'object' => [
                'anr' => $anr->getId(),
                'uuid' => $instance->getObject()->getUuid(),
            ],
        ]));

        if (!empty($instanceBrothers)
            && $modeImport === 'merge'
            && $instance->getObject()->isScopeGlobal()
        ) {
            $instanceConseqBrothers = $instanceConsequenceTable->getEntityByFields([
                'anr' => $anr->getId(),
                'instance' => $instanceBrothers,
                'object' => [
                    'anr' => $anr->getId(),
                    'uuid' => $instance->getObject()->getUuid()
                ],
            ]);

            foreach ($instanceConseqBrothers as $icb) { //Update consequences for all brothers
                $this->get('instanceConsequenceService')->updateBrothersConsequences(
                    $anr->getId(),
                    $icb->getId()
                );
            }
            $ts = ['c', 'i', 'd'];
            foreach ($ts as $t) { //Update impacts in instance
                if ($instanceBrothers->get($t . 'h') == 0) {
                    $instance->set($t . 'h', 0);
                    $instance->set($t, $instanceBrothers->$t);
                } elseif ($instance->get('parent')) {
                    $instance->set($t . 'h', 1);
                    $instance->set($t, $instance->get('parent')->get($t));
                } else {
                    $instance->set($t . 'h', 1);
                    $instance->set($t, $instanceBrothers->$t);
                }
            }
        }

        $this->refreshImpactsInherited($instance);

        $this->createSetOfRecommendations($data, $anr, $monarcVersion);

        /** ThreatTable $threatTable */
        $threatTable = $this->get('threatTable');
        /** @var VulnerabilityTable $vulnerabilityTable */
        // TODO: inject vulnerabilityTable here.
        $vulnerabilityTable = $instanceRiskService->get('vulnerabilityTable');

        if (!empty($data['risks'])) {
            // load of the existing value
            $this->sharedData['ivuls'] = $vCodes = [];
            $this->sharedData['ithreats'] = $tCodes = [];
            if (version_compare($monarcVersion, '2.8.2') >= 0) { //TO DO:set the right value with the uuid version
                foreach ($data['threats'] as $t) {
                    $tCodes[] = $t['uuid'];
                }
                $existingThreats = $threatTable->getEntityByFields([
                    'anr' => $anr->getId(),
                    'uuid' => $tCodes
                ]);
                foreach ($existingThreats as $t) {
                    $this->sharedData['ithreats'][] = $t->getUuid();
                }
                foreach ($data['vuls'] as $v) {
                    $vCodes[] = $v['uuid'];
                }
                $existingVulnerabilities = $vulnerabilityTable->getEntityByFields([
                    'anr' => $anr->getId(),
                    'uuid' => $vCodes
                ]);
                foreach ($existingVulnerabilities as $v) {
                    $this->sharedData['ivuls'][] = $v->getUuid();
                }
            } else {
                foreach ($data['threats'] as $t) {
                    $tCodes[$t['code']] = $t['code'];
                }
                $existingRisks = $threatTable->getEntityByFields([
                    'anr' => $anr->getId(),
                    'code' => $tCodes
                ]);
                foreach ($existingRisks as $t) {
                    $this->sharedData['ithreats'][$t->getCode()] = $t->getUuid();
                }
                foreach ($data['vuls'] as $v) {
                    $vCodes[$v['code']] = $v['code'];
                }
                $existingRisks = $vulnerabilityTable->getEntityByFields([
                    'anr' => $anr->getId(),
                    'code' => $vCodes
                ]);
                foreach ($existingRisks as $v) {
                    $this->sharedData['ivuls'][$v->getCode()] = $v->getUuid();
                }
            }

            /** @var InstanceRiskTable $instanceRiskTable */
            $instanceRiskTable = $this->get('instanceRiskTable');
            /** @var RecommandationRiskTable $recommendationRiskTable */
            $recommendationRiskTable = $this->get('recommandationRiskTable');

            foreach ($data['risks'] as $risk) {
                //uuid id now the pivot instead of code
                if ($risk['specific']) {
                    // TODO: remove the support of the old version.
                    // on doit le créer localement mais pour ça il nous faut les pivots sur les menaces et vulnérabilités
                    // on checke si on a déjà les menaces et les vulnérabilités liées, si c'est pas le cas, faut les créer
                    if ((!in_array($risk['threat'], $this->sharedData['ithreats'])
                            && version_compare($monarcVersion, '2.8.2') >= 0
                        ) || (
                            version_compare($monarcVersion, '2.8.2') === -1
                            && !isset($this->sharedData['ithreats'][$data['threats'][$risk['threat']]['code']])
                        )
                    ) {
                        $toExchange = $data['threats'][$risk['threat']];
                        unset($toExchange['id']);
                        $toExchange['anr'] = $anr->getId();
                        $threat = new Threat();
                        $threat->setDbAdapter($threatTable->getDb());
                        $threat->setLanguage($this->getLanguage());
                        $threat->exchangeArray($toExchange);
                        $threat->setAnr($anr);
                        // TODO: replace with saveEntity everywhere.
                        $tuuid = $threatTable->save($threat, false);
                        // TODO: save the threat OBJECT (not uuid) anyway to the shared data.
                        if (!version_compare($monarcVersion, '2.8.2') === -1) {
                            $this->sharedData['ithreats'][$data['threats'][$risk['threat']]['code']] = $tuuid;
                        }
                    }

                    if ((!in_array($risk['vulnerability'], $this->sharedData['ivuls'])
                            && version_compare($monarcVersion, '2.8.2') >= 0
                        ) || (version_compare($monarcVersion, '2.8.2') === -1
                            && !isset($this->sharedData['ivuls'][$data['vuls'][$risk['vulnerability']]['code']])
                        )
                    ) {
                        $toExchange = $data['vuls'][$risk['vulnerability']];
                        unset($toExchange['id']);
                        $toExchange['anr'] = $anr->getId();
                        $vul = new Vulnerability();
                        $vul->setDbAdapter($instanceRiskService->get('vulnerabilityTable')->getDb());
                        $vul->setLanguage($this->getLanguage());
                        $vul->exchangeArray($toExchange);
                        $vul->setAnr($anr);
                        $vuuid = $vulnerabilityTable->save($vul, false);
                        if (version_compare($monarcVersion, "2.8.2") == -1) {
                            $this->sharedData['ivuls'][$data['vuls'][$risk['vulnerability']]['code']] = $vuuid;
                        }
                    }

                    /**
                     * Retrieve the linked(brother) instances.
                     * @var Instance[] $instanceBrothers
                     */
                    $instanceBrothers = $instanceTable->getEntityByFields([
                        'id' => ['op' => '!=', 'value' => $instance->getId()],
                        'anr' => $anr->getId(),
                        'asset' => [
                            'anr' => $anr->getId(),
                            'uuid' => $instance->getAsset()->getUuid()
                        ],
                        'object' => [
                            'anr' => $anr->getId(),
                            'uuid' => $monarcObject->getUuid(),
                        ]
                    ]);

                    /*
                     * Create specific risks linked to the brother instances.
                     */
                    foreach ($instanceBrothers as $ib) {
                        $toExchange = $risk;
                        unset($toExchange['id']);
                        $toExchange['amv'] = null;
                        $toExchange['threat'] = Uuid::isValid($risk['threat'])
                            ? $risk['threat']
                            : $this->sharedData['ithreats'][$data['threats'][$risk['threat']]['code']];
                        $toExchange['vulnerability'] = Uuid::isValid($risk['vulnerability'])
                            ? $risk['vulnerability']
                            : $this->sharedData['ivuls'][$data['vuls'][$risk['vulnerability']]['code']];
                        $rToBrother = new InstanceRisk();
                        // TODO: drop it when remove setDependencies.
                        $rToBrother->setDbAdapter($instanceRiskTable->getDb());
                        $rToBrother->setLanguage($this->getLanguage());
                        // TODO: replace with setters usage and set threat and vulnerability from objects.
                        $rToBrother->exchangeArray($toExchange);
                        $rToBrother->setAnr($anr)
                            ->setInstance($ib)
                            ->setAsset($monarcObject->getAsset());
                        $this->setDependencies($rToBrother, ['threat', 'vulnerability']);
                        // TODO: replace with saveEntity.
                        $instanceRiskTable->save($rToBrother, false);
                    }

                    $toExchange = $risk;
                    unset($toExchange['id']);
                    $toExchange['amv'] = null;
                    $toExchange['threat'] = Uuid::isValid($risk['threat'])
                        ? $risk['threat']
                        : $this->sharedData['ithreats'][$data['threats'][$risk['threat']]['code']];
                    $toExchange['vulnerability'] = Uuid::isValid($risk['vulnerability'])
                        ? $risk['vulnerability']
                        : $this->sharedData['ivuls'][$data['vuls'][$risk['vulnerability']]['code']];
                    $instanceRisk = new InstanceRisk();
                    // TODO: drop it when remove setDependencies.
                    $instanceRisk->setDbAdapter($instanceRiskTable->getDb());
                    $instanceRisk->setLanguage($this->getLanguage());
                    // TODO: replace with setters usage and set threat and vulnerability from objects.
                    $instanceRisk->exchangeArray($toExchange);
                    $instanceRisk->setAnr($anr)
                        ->setInstance($instance)
                        ->setAsset($monarcObject->getAsset());
                    $this->setDependencies($instanceRisk, ['threat', 'vulnerability']);
                    // TODO: replace with saveEntity.
                    $instanceRiskTable->save($instanceRisk, false);
                }

                $tuuid = Uuid::isValid($risk['threat'])
                    ? $risk['threat']
                    : $this->sharedData['ithreats'][$data['threats'][$risk['threat']]['code']];
                $vuuid = Uuid::isValid($risk['vulnerability'])
                    ? $risk['vulnerability']
                    : $this->sharedData['ivuls'][$data['vuls'][$risk['vulnerability']]['code']];

                /** @var InstanceRisk $instanceRisk */
                $instanceRisk = current($instanceRiskTable->getEntityByFields([
                    'anr' => $anr->getId(),
                    'instance' => $instance->getId(),
                    'asset' => $monarcObject ? [
                        'anr' => $anr->getId(),
                        'uuid' => $monarcObject->getAsset()->getUuid(),
                    ] : null,
                    'threat' => ['anr' => $anr->getId(), 'uuid' => $tuuid],
                    'vulnerability' => ['anr' => $anr->getId(), 'uuid' => $vuuid],
                ]));

                if ($instanceRisk !== null && $includeEval) {
                    $instanceRisk->set('threatRate', $this->approximate(
                        $risk['threatRate'],
                        $this->sharedData['scales']['orig'][Scale::TYPE_THREAT]['min'],
                        $this->sharedData['scales']['orig'][Scale::TYPE_THREAT]['max'],
                        $this->sharedData['scales']['dest'][Scale::TYPE_THREAT]['min'],
                        $this->sharedData['scales']['dest'][Scale::TYPE_THREAT]['max']
                    ));
                    $instanceRisk->set('vulnerabilityRate', $this->approximate(
                        $risk['vulnerabilityRate'],
                        $this->sharedData['scales']['orig'][Scale::TYPE_VULNERABILITY]['min'],
                        $this->sharedData['scales']['orig'][Scale::TYPE_VULNERABILITY]['max'],
                        $this->sharedData['scales']['dest'][Scale::TYPE_VULNERABILITY]['min'],
                        $this->sharedData['scales']['dest'][Scale::TYPE_VULNERABILITY]['max']
                    ));
                    $instanceRisk->set('mh', $risk['mh']);
                    $instanceRisk->set('kindOfMeasure', $risk['kindOfMeasure']);
                    $instanceRisk->set('comment', $risk['comment']);
                    $instanceRisk->set('commentAfter', $risk['commentAfter']);

                    // La valeur -1 pour le reduction_amount n'a pas de sens, c'est 0 le minimum. Le -1 fausse
                    // les calculs.
                    // Cas particulier, faudrait pas mettre n'importe quoi dans cette colonne si on part d'une scale
                    // 1 - 7 vers 1 - 3 on peut pas avoir une réduction de 4, 5, 6 ou 7
                    $instanceRisk->set(
                        'reductionAmount',
                        $risk['reductionAmount'] != -1
                            ? $this->approximate(
                            $risk['reductionAmount'],
                            0,
                            $risk['vulnerabilityRate'],
                            0,
                            $instanceRisk->get('vulnerabilityRate'),
                            0)
                            : 0
                    );
                    $instanceRiskTable->saveEntity($instanceRisk, false);

                    // Merge all fields for global assets.
                    if ($modeImport === 'merge'
                        && !$instanceRisk->isSpecific()
                        && $instance->getObject()->isScopeGlobal()
                    ) {
                        $objectIdsBrothers = $instanceTable->findByAnrAndObject($anr, $instance->getObject());

                        // TODO: check why do we take only a single one, use query instead of the generic method.
                        /** @var InstanceRisk $instanceRiskBrothers */
                        $instanceRiskBrothers = current($instanceRiskTable->getEntityByFields([
                            'anr' => $anr->getId(),
                            'instance' => ['op' => 'IN', 'value' => $objectIdsBrothers],
                            'amv' => [
                                'anr' => $anr->getId(),
                                'uuid' => $instanceRisk->getAmv()->getUuid(),
                            ]
                        ]));

                        if (!empty($instanceRiskBrothers)) {
                            $dataUpdate = [];
                            $dataUpdate['anr'] = $anr->getId();
                            $dataUpdate['threatRate'] = $instanceRiskBrothers->threatRate; // Merge threat rate
                            $dataUpdate['vulnerabilityRate'] = $instanceRiskBrothers->vulnerabilityRate; // Merge vulnerability rate
                            $dataUpdate['kindOfMeasure'] = $instanceRiskBrothers-kindOfMeasure; // Merge kind Of Measure
                            $dataUpdate['reductionAmount'] = $instanceRiskBrothers->reductionAmount; // Merge reduction amount
                            if (strcmp($instanceRiskBrothers->getComment(), $instanceRisk->getComment()) !== 0// Check if comment is different
                                && strpos($instanceRiskBrothers->getComment(), $instanceRisk->getComment()) === false
                            ) { // Check if comment exists
                                $dataUpdate['comment'] = $instanceRiskBrothers->getComment() . "\n\n" . $instanceRisk->getComment(); // Merge comments
                            } else {
                                $dataUpdate['comment'] = $instanceRiskBrothers->getComment();
                            }

                            $instanceRiskService->update($instanceRisk->getId(), $dataUpdate);
                        }
                    }

                    /** @var RecommandationTable $recommendationTable */
                    $recommendationTable = $this->get('recommandationTable');

                    // Process recommendations.
                    if (!empty($data['recos'][$risk['id']])) {
                        foreach ($data['recos'][$risk['id']] as $reco) {
                            $recommendation = $this->processRecommendationDataLinkedToRisk(
                                $anr,
                                $reco,
                                $risk['kindOfMeasure'] !== InstanceRiskSuperClass::KIND_NOT_TREATED
                            );
                            $recommendationRisk = (new RecommandationRisk())
                                ->setAnr($anr)
                                ->setInstance($instance)
                                ->setInstanceRisk($instanceRisk)
                                ->setGlobalObject($monarcObject->isScopeGlobal() ? $monarcObject : null)
                                ->setAsset($instanceRisk->getAsset())
                                ->setThreat($instanceRisk->getThreat())
                                ->setVulnerability($instanceRisk->getVulnerability())
                                ->setCommentAfter((string)$reco['commentAfter'])
                                ->setRecommandation($recommendation);

                            $recommendationRiskTable->saveEntity($recommendationRisk, false);

                            // Replicate recommendation to brothers.
                            if ($modeImport === 'merge' && $recommendationRisk->hasGlobalObjectRelation()) {
                                /** @var Instance[] $brotherInstances */
                                $brotherInstances = $instanceTable->getEntityByFields([ // Get the brothers
                                                                                        'anr' => $anr->getId(),
                                                                                        'asset' => [
                                                                                            'anr' => $anr->getId(),
                                                                                            'uuid' => $monarcObject->getAsset()->getUuid(),
                                                                                        ],
                                                                                        'object' => ['anr' => $anr->getId(), 'uuid' => $monarcObject->getUuid()]
                                ]);

                                if (!empty($brotherInstances)) {
                                    foreach ($brotherInstances as $brotherInstance) {
                                        // Get the risks of brothers
                                        /** @var InstanceRisk[] $brothers */
                                        if ($instanceRisk->isSpecific()) {
                                            $brothers = $recommendationRiskTable->getEntityByFields([
                                                'anr' => $anr->getId(),
                                                'specific' => InstanceRisk::TYPE_SPECIFIC,
                                                'instance' => $brotherInstance->getId(),
                                                'threat' => [
                                                    'anr' => $anr->getId(),
                                                    'uuid' => $instanceRisk->getThreat()->getUuid()
                                                ],
                                                'vulnerability' => [
                                                    'anr' => $anr->getId(),
                                                    'uuid' => $instanceRisk->getVulnerability()->getUuid()
                                                ]
                                            ]);
                                        } else {
                                            $brothers = $recommendationRiskTable->getEntityByFields([
                                                'anr' => $anr->getId(),
                                                'instance' => $brotherInstance->getId(),
                                                'amv' => [
                                                    'anr' => $anr->getId(),
                                                    'uuid' => $instanceRisk->getAmv()->getUuid()
                                                ]
                                            ]);
                                        }

                                        foreach ($brothers as $brother) {
                                            $recommendationRiskBrother = $recommendationRiskTable
                                                ->findByInstanceRiskAndRecommendation($brother, $recommendation);
                                            if ($recommendationRiskBrother === null) {
                                                $recommendationRiskBrother = (new RecommandationRisk())
                                                    ->setAnr($anr)
                                                    ->setInstance($brotherInstance)
                                                    ->setInstanceRisk($brother)
                                                    ->setGlobalObject(
                                                        $monarcObject->isScopeGlobal() ? $monarcObject : null
                                                    )
                                                    ->setAsset($instanceRisk->getAsset())
                                                    ->setThreat($instanceRisk->getThreat())
                                                    ->setVulnerability($instanceRisk->getVulnerability())
                                                    ->setCommentAfter((string)$reco['commentAfter'])
                                                    ->setRecommandation($recommendation);

                                                $recommendationRiskTable->saveEntity($recommendationRiskBrother, false);
                                            }
                                        }
                                    }
                                    $recommendationRiskTable->getDb()->flush();
                                }
                            }
                        }
                        $recommendationTable->getDb()->flush();
                    }
                }

                // Check recommendations from a brother
                /** @var Instance $instanceBrother */
                $instanceBrother = current($instanceTable->getEntityByFields([
                    'id' => ['op' => '!=', 'value' => $instance->getId()],
                    'anr' => $anr->getId(),
                    'asset' => ['anr' => $anr->getId(), 'uuid' => $monarcObject->getAsset()->getUuid()],
                    'object' => ['anr' => $anr->getId(), 'uuid' => $monarcObject->getUuid()]
                ]));

                if ($instanceBrother !== null && $instanceRisk !== null && !$instanceRisk->isSpecific()) {
                    /** @var InstanceRiskTable $instanceRiskTable */
                    $instanceRiskTable = $this->get('instanceRiskTable');
                    $instanceRiskBrothers = $instanceRiskTable->findByInstanceAndAmv(
                        $instanceBrother,
                        $instanceRisk->getAmv()
                    );

                    foreach ($instanceRiskBrothers as $instanceRiskBrother) {
                        /** @var RecommandationRisk[] $brotherRecoRisks */
                        // Get recommendation of brother
                        $brotherRecoRisks = $recommendationRiskTable->getEntityByFields([
                            'anr' => $anr->getId(),
                            'instanceRisk' => $instanceRiskBrother->getId(),
                            'instance' => ['op' => '!=', 'value' => $instance->getId()],
                            'globalObject' => [
                                'anr' => $anr->getId(),
                                'uuid' => $monarcObject->getUuid(),
                            ]
                        ]);

                        if (!empty($brotherRecoRisks)) {
                            foreach ($brotherRecoRisks as $brotherRecoRisk) {
                                $recommendationRisk = $recommendationRiskTable->findByInstanceRiskAndRecommendation(
                                    $instanceRisk,
                                    $brotherRecoRisk->getRecommandation()
                                );

                                if ($recommendationRisk === null) {
                                    $recommendationRisk = (new RecommandationRisk())
                                        ->setAnr($anr)
                                        ->setInstance($instance)
                                        ->setInstanceRisk($brotherRecoRisk->getInstanceRisk())
                                        ->setGlobalObject($brotherRecoRisk->getGlobalObject())
                                        ->setAsset($brotherRecoRisk->getAsset())
                                        ->setThreat($brotherRecoRisk->getThreat())
                                        ->setVulnerability($brotherRecoRisk->getVulnerability())
                                        ->setCommentAfter($brotherRecoRisk->getCommentAfter())
                                        ->setRecommandation($brotherRecoRisk->getRecommandation());

                                    $recommendationRiskTable->saveEntity($recommendationRisk, false);
                                }
                            }

                            $recommendationRiskTable->getDb()->flush();
                        }
                    }
                }
            }

            // Check recommandations from specific risk of brothers
            $recoToCreate = [];
            // TODO: replace all the queries with QueryBuilder. Review the logic.
            /** @var InstanceRisk[] $specificRisks */
            $specificRisks = $instanceRiskTable->getEntityByFields([ // Get all specific risks of instance
                                                                     'anr' => $anr->getId(),
                                                                     'instance' => $instance->getId(),
                                                                     'specific' => 1
            ]);
            foreach ($specificRisks as $specificRisk) {
                /** @var RecommandationRisk[] $exitingRecoRisks */
                $exitingRecoRisks = $recommendationRiskTable->getEntityByFields([ // Get recommandations of brothers
                                                                                  'anr' => $anr->getId(),
                                                                                  'asset' => ['anr' => $anr->getId(), 'uuid' => $specificRisk->getAsset()->getUuid()],
                                                                                  'threat' => ['anr' => $anr->getId(), 'uuid' => $specificRisk->getThreat()->getUuid()],
                                                                                  'vulnerability' => ['anr' => $anr->getId(), 'uuid' => $specificRisk->getVulnerability()->getUuid()]
                ]);
                foreach ($exitingRecoRisks as $exitingRecoRisk) {
                    if ($instance->getId() !== $exitingRecoRisk->getInstance()->getId()) {
                        $recoToCreate[] = $exitingRecoRisk;
                    }
                }
            }

            /** @var RecommandationRisk $recommendationRiskToCreate */
            foreach ($recoToCreate as $recommendationRiskToCreate) {
                $recoCreated = $recommendationRiskTable->getEntityByFields([ // Check if reco-risk link exist
                                                                             'recommandation' => [
                                                                                 'anr' => $anr->getId(),
                                                                                 'uuid' => $recommendationRiskToCreate->getRecommandation()->getUuid(),
                                                                             ],
                                                                             'instance' => $instance->getId(),
                                                                             'asset' => [
                                                                                 'anr' => $anr->getId(),
                                                                                 'uuid' => $recommendationRiskToCreate->getAsset()->getUuid(),
                                                                             ],
                                                                             'threat' => [
                                                                                 'anr' => $anr->getId(),
                                                                                 'uuid' => $recommendationRiskToCreate->getThreat()->getUuid(),
                                                                             ],
                                                                             'vulnerability' => [
                                                                                 'anr' => $anr->getId(),
                                                                                 'uuid' => $recommendationRiskToCreate->getVulnerability()->getUuid(),
                                                                             ]
                ]);

                if (empty($recoCreated)) {
                    // TODO: check if we can get it in different way as it is too heavy.
                    $instanceRiskSpecific = current($instanceRiskTable->getEntityByFields([
                        'anr' => $anr->getId(),
                        'instance' => $instance->getId(),
                        'specific' => 1,
                        'asset' => ['anr' => $anr->getId(), 'uuid' => $recommendationRiskToCreate->getAsset()->getUuid()],
                        'threat' => ['anr' => $anr->getId(), 'uuid' => $recommendationRiskToCreate->getThreat()->getUuid()],
                        'vulnerability' => ['anr' => $anr->getId(), 'uuid' => $recommendationRiskToCreate->getVulnerability()->getUuid()]
                    ]));

                    $recommendationRisk = (new RecommandationRisk())
                        ->setAnr($anr)
                        ->setInstance($instance)
                        ->setInstanceRisk($instanceRiskSpecific)
                        ->setGlobalObject($recommendationRiskToCreate->getGlobalObject())
                        ->setAsset($recommendationRiskToCreate->getAsset())
                        ->setThreat($recommendationRiskToCreate->getThreat())
                        ->setVulnerability($recommendationRiskToCreate->getVulnerability())
                        ->setCommentAfter($recommendationRiskToCreate->getCommentAfter())
                        ->setRecommandation($recommendationRiskToCreate->getRecommandation());

                    $recommendationRiskTable->saveEntity($recommendationRisk, false);
                }
            }
            $recommendationRiskTable->getDb()->flush();

            // on met finalement à jour les risques en cascade
            $this->updateRisks($instance);
        }

        if (!empty($data['risksop'])) {
            $toApproximate = [
                Scale::TYPE_THREAT => [
                    'netProb',
                    'targetedProb',
                ],
                Scale::TYPE_IMPACT => [
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
            $toApproximate[Scale::TYPE_THREAT][] = 'brutProb';
            $toApproximate[Scale::TYPE_IMPACT][] = 'brutR';
            $toApproximate[Scale::TYPE_IMPACT][] = 'brutO';
            $toApproximate[Scale::TYPE_IMPACT][] = 'brutL';
            $toApproximate[Scale::TYPE_IMPACT][] = 'brutF';
            $toApproximate[Scale::TYPE_IMPACT][] = 'brutP';

            /** @var InstanceRiskOpTable $instanceRiskOpTable */
            $instanceRiskOpTable = $this->get('instanceRiskOpTable');

            $k = 0;
            foreach ($data['risksop'] as $ro) {
                $instanceRiskOp = new InstanceRiskOp();
                $ro['rolfRisk'] = null;
                $toExchange = $ro;
                unset($toExchange['id']);
                if ($monarcObject->getRolfTag() !== null) {
                    // TODO: use getter.
                    $rolfRisks = $monarcObject->getRolfTag()->risks;
                    $toExchange['rolfRisk'] = $rolfRisks[$k];
                    $toExchange['riskCacheCode'] = $rolfRisks[$k]->code;
                    $k++;
                }

                // traitement de l'évaluation -> c'est complètement dépendant des échelles locales
                if ($includeEval) {
                    // pas d'impact des subscales, on prend les échelles nominales
                    foreach ($toApproximate as $type => $list) {
                        foreach ($list as $i) {
                            $toExchange[$i] = $this->approximate(
                                $toExchange[$i],
                                $this->sharedData['scales']['orig'][$type]['min'],
                                $this->sharedData['scales']['orig'][$type]['max'],
                                $this->sharedData['scales']['dest'][$type]['min'],
                                $this->sharedData['scales']['dest'][$type]['max']
                            );
                        }
                    }
                }

                $instanceRiskOp->setLanguage($this->getLanguage());
                $instanceRiskOp->exchangeArray($toExchange);
                $instanceRiskOp->setAnr($anr)
                    ->setInstance($instance)
                    ->setObject($monarcObject);
                if (isset($toExchange['rolfRisk'])) {
                    $instanceRiskOp->setRolfRisk($toExchange['rolfRisk']);
                }

                $instanceRiskOpTable->saveEntity($instanceRiskOp, false);

                /** @var RecommandationRiskTable $recommendationRiskTable */
                $recommendationRiskTable = $this->get('recommandationRiskTable');

                // Process recommendations related to the operational risk.
                if ($includeEval && !empty($data['recosop'][$ro['id']])) {
                    foreach ($data['recosop'][$ro['id']] as $reco) {
                        $recommendation = $this->processRecommendationDataLinkedToRisk(
                            $anr,
                            $reco,
                            $ro['kindOfMeasure'] !== InstanceRiskOpSuperClass::KIND_NOT_TREATED
                        );

                        $recommendationRisk = (new RecommandationRisk())
                            ->setAnr($anr)
                            ->setInstance($instance)
                            ->setInstanceRiskOp($instanceRiskOp)
                            ->setGlobalObject($monarcObject->isScopeGlobal() ? $monarcObject : null)
                            ->setCommentAfter($reco['commentAfter'])
                            ->setRecommandation($recommendation);

                        $recommendationRiskTable->saveEntity($recommendationRisk, false);
                    }
                }

                $recommendationRiskTable->getDb()->flush();
            }
        }

        if (!empty($data['children'])) {
            usort($data['children'], function ($a, $b) {
                return $a['instance']['position'] <=> $b['instance']['position'];
            });
            foreach ($data['children'] as $child) {
                $this->importInstanceFromArray($child, $anr, $instance, $modeImport);
            }
            $this->updateChildrenImpacts($instance->getId());
        }

        $instanceTable->getDb()->flush();

        return $instance->getId();
    }

    /**
     * @throws Exception
     * @throws OptimisticLockException
     */
    private function importAnrFromArray(
        array $data,
        Anr $anr,
        ?Instance $parent,
        string $modeImport,
        bool $isRoot = false
    ): array {
        /** @var AnrInstanceRiskService $instanceRiskService */
        $instanceRiskService = $this->get('instanceRiskService');
        /** @var InstanceConsequenceTable $instanceConsequenceTable */
        $instanceConsequenceTable = $this->get('instanceConsequenceTable');
        /** @var ThreatTable $threatTable */
        $threatTable = $this->get('threatTable');

        $labelKey = 'label' . $this->getLanguage();
        // Method information
        if (!empty($data['method'])) { //Steps checkboxes
            if (!empty($data['method']['steps'])) {
                $anrTable = $this->get('anrTable');
                foreach ($data['method']['steps'] as $key => $v) {
                    if ($anr->get($key) === 0) {
                        $anr->set($key, $v);
                        $anrTable->save($anr, false);
                    }
                }
                $anrTable->getDb()->flush();
            }

            if (!empty($data['method']['data'])) { //Data of textboxes
                $anrTable = $this->get('anrTable');
                foreach ($data['method']['data'] as $key => $v) {
                    if ($anr->get($key) === null) {
                        $anr->set($key, $v);
                        $anrTable->save($anr, false);
                    }
                }
                $anrTable->getDb()->flush();
            }

            if (!empty($data['method']['interviews'])) { //Data of interviews
                foreach ($data['method']['interviews'] as $key => $v) {
                    $toExchange = $data['method']['interviews'][$key];
                    $toExchange['anr'] = $anr->getId();
                    $newInterview = new Interview();
                    $newInterview->setLanguage($this->getLanguage());
                    $newInterview->exchangeArray($toExchange);
                    $newInterview->setAnr($anr);
                    $this->get('interviewTable')->save($newInterview, false);
                }
                $this->get('interviewTable')->getDb()->flush();
            }

            if (!empty($data['method']['thresholds'])) { // Value of thresholds
                $anrTable = $this->get('anrTable');
                foreach ($data['method']['thresholds'] as $key => $v) {
                    $anr->set($key, $v);
                    $anrTable->save($anr, false);
                }
                $anrTable->getDb()->flush();
            }

            if (!empty($data['method']['deliveries'])) { // Data of deliveries generation
                /** @var DeliveryTable $deliveryTable */
                $deliveryTable = $this->get('deliveryTable');
                foreach ($data['method']['deliveries'] as $key => $v) {
                    $toExchange = $data['method']['deliveries'][$key];
                    $toExchange['anr'] = $anr->getId();
                    $newDelivery = new Delivery();
                    $newDelivery->setLanguage($this->getLanguage());
                    $newDelivery->exchangeArray($toExchange);
                    $newDelivery->setAnr($anr);
                    // TODO: use saveEntity.
                    $deliveryTable->save($newDelivery, false);
                }
                $deliveryTable->getDb()->flush();
            }

            if (!empty($data['method']['questions'])) { // Questions of trends evaluation
                /** @var QuestionTable $questionTable */
                $questionTable = $this->get('questionTable');
                /** @var QuestionChoiceTable $questionChoiceTable */
                $questionChoiceTable = $this->get('questionChoiceTable');
                // TODO: findByAnr
                $questions = $questionTable->getEntityByFields(['anr' => $anr->getId()]);
                foreach ($questions as $question) {
                    $questionTable->delete($question->id);
                }

                foreach ($data['method']['questions'] as $position => $questionData) {
                    $newQuestion = new Question();
                    $newQuestion->setLanguage($this->getLanguage());
                    $newQuestion->exchangeArray($questionData);
                    $newQuestion->setAnr($anr);
                    // TODO: use setter.
                    $newQuestion->set('position', $position);
                    $questionTable->save($newQuestion, false);

                    if ((int)$questionData['multichoice'] === 1) {
                        foreach ($data['method']['questionChoice'] as $questionChoiceData) {
                            if ($questionChoiceData['question'] === $questionData['id']) {
                                $newQuestionChoice = new QuestionChoice();
                                $newQuestionChoice->setLanguage($this->getLanguage());
                                $newQuestionChoice->exchangeArray($questionChoiceData);
                                $newQuestionChoice->setAnr($anr)
                                    ->setQuestion($newQuestion);
                                $questionChoiceTable->save($newQuestionChoice, false);
                            }
                        }
                    }
                }

                $questionTable->getDb()->flush();

                /** @var Question[] $questions */
                // TODO: findByAnr or better use the saved questions before, we don't need to query the db.
                $questions = $questionTable->getEntityByFields(['anr' => $anr->getId()]);

                /** @var QuestionChoice[] $questionChoices */
                // TODO: findByAnr or better use the saved questions before, we don't need to query the db.
                $questionChoices = $questionChoiceTable->getEntityByFields(['anr' => $anr->getId()]);

                foreach ($data['method']['questions'] as $questionAnswerData) {
                    foreach ($questions as $question) {
                        if ($question->get($labelKey) === $questionAnswerData[$labelKey]) {
                            // TODO: check if the method exists
                            if ($question->isMultiChoice()) {
                                $originQuestionChoices = [];
                                $response = $questionAnswerData['response'] ?? '';
                                if (trim($response, '[]')) {
                                    $originQuestionChoices = explode(',', trim($response, '[]'));
                                }
                                $questionChoicesIds = [];
                                foreach ($originQuestionChoices as $originQuestionChoice) {
                                    $chosenQuestionLabel = $data['method']['questionChoice'][$originQuestionChoice][$labelKey];
                                    foreach ($questionChoices as $questionChoice) {
                                        if ($questionChoice->get($labelKey) === $chosenQuestionLabel) {
                                            $questionChoicesIds[] = $questionChoice->getId();
                                        }
                                    }
                                }
                                $question->response = '[' . implode(',', $questionChoicesIds) . ']';
                            } else {
                                $question->response = $questionAnswerData['response'];
                            }
                            // TODO: saveEntity.
                            $questionTable->save($question, false);
                        }
                    }
                }

                $this->get('questionTable')->getDb()->flush();
            }

            /*
             * Process the evaluation of threats.
             */
            if (!empty($data['method']['threats'])) {
                /** @var ThemeTable $themeTable */
                $themeTable = $this->get('themeTable');
                foreach ($data['method']['threats'] as $tId => $v) {
                    if (!empty($data['method']['threats'][$tId]['theme'])) {
                        // TODO: avoid such queries or check to add indexes or fetch all for the ANR and iterate in the code.
                        // TODO: we have findByAnrIdAndLabel() !
                        $themes = $themeTable->getEntityByFields([
                            'anr' => $anr->getId(),
                            $labelKey => $data['method']['threats'][$tId]['theme'][$labelKey]
                        ], ['id' => 'ASC']);
                        if (empty($themes)) { // Creation of new theme if no exist
                            $toExchange = $data['method']['threats'][$tId]['theme'];
                            $newTheme = new Theme();
                            $newTheme->setLanguage($this->getLanguage());
                            $newTheme->exchangeArray($toExchange);
                            $newTheme->setAnr($anr);
                            $themeTable->saveEntity($newTheme);
                            // TODO: set objects here to avoid querying the db.
                            $data['method']['threats'][$tId]['theme']['id'] = $newTheme->getId();
                        } else {
                            foreach ($themes as $th) {
                                // TODO: set objects here to avoid querying the db.
                                $data['method']['threats'][$tId]['theme']['id'] = $th->getId();
                            }
                        }
                    }
                    /** @var Threat[] $threats */
                    $threats = $threatTable->getEntityByFields([
                        'anr' => $anr->getId(),
                        'code' => $data['method']['threats'][$tId]['code']
                    ], ['uuid' => 'ASC']);
                    if (empty($threats)) {
                        $toExchange = $data['method']['threats'][$tId];
                        $toExchange['mode'] = 0;
                        // TODO: use objects here ans use setter.
                        $toExchange['theme'] = $data['method']['threats'][$tId]['theme']['id'];
                        $newThreat = new Threat();
                        // TODO: drop it after setDep is removed.
                        $newThreat->setDbAdapter($threatTable->getDb());
                        $newThreat->setLanguage($this->getLanguage());
                        $newThreat->exchangeArray($toExchange);
                        $this->setDependencies($newThreat, ['theme']);
                        $newThreat->setAnr($anr);
                        // TODO: saveEntity
                        $threatTable->save($newThreat, false);
                    } else {
                        foreach ($threats as $t) {
                            // TODO: use setters.
                            $t->set('trend', $data['method']['threats'][$tId]['trend']);
                            $t->set('comment', $data['method']['threats'][$tId]['comment']);
                            $t->set('qualification', $data['method']['threats'][$tId]['qualification']);
                            $this->get('threatTable')->save($t, false);
                        }
                        $threatTable->getDb()->flush();
                    }
                }
            }
        }

        /*
         * Import the referentials.
         */
        /** @var ReferentialTable $referentialTable */
        $referentialTable = $this->get('referentialTable');
        if (isset($data['referentials'])) {
            foreach ($data['referentials'] as $referentialUUID => $referential_array) {
                // check if the referential is not already present in the analysis
                // TODO: findByAnrAndUuid
                $referentials = $referentialTable
                    ->getEntityByFields(['anr' => $anr->getId(), 'uuid' => $referentialUUID]);
                if (empty($referentials)) {
                    $newReferential = new Referential($referential_array);
                    $newReferential->setAnr($anr);
                    // TODO: saveEntity
                    $referentialTable->save($newReferential, false);
                }
            }
        }

        /*
         * Import the soa categories.
         */
        /** @var SoaCategoryTable $soaCategoryTable */
        $soaCategoryTable = $this->get('soaCategoryTable');
        if (isset($data['soacategories'])) {
            foreach ($data['soacategories'] as $soaCategory) {
                // load the referential linked to the soacategory
                // TODO: findByAnrAndUuid
                $referentials = $referentialTable->getEntityByFields([
                    'anr' => $anr->getId(),
                    'uuid' => $soaCategory['referential']
                ]);
                if (!empty($referentials)) {
                    $categories = $soaCategoryTable->getEntityByFields(['anr' => $anr->getId(),
                                                                        $labelKey => $soaCategory[$labelKey],
                                                                        'referential' => [
                                                                            'anr' => $anr->getId(),
                                                                            'uuid' => $referentials[0]->getUuid(),
                                                                        ]
                    ]);
                    if (empty($categories)) {
                        $newSoaCategory = new SoaCategory($soaCategory);
                        $newSoaCategory->setAnr($anr);
                        $newSoaCategory->setReferential($referentials[0]);
                        // TODO: saveEntity
                        $soaCategoryTable->save($newSoaCategory, false);
                    }
                }
            }
            $soaCategoryTable->getDb()->flush();
        }

        /*
         * Import the measures.
         */
        /** @var SoaTable $soaTable */
        $soaTable = $this->get('soaTable');
        /** @var MeasureTable $measureTable */
        $measureTable = $this->get('measureTable');
        $measuresNewIds = [];
        if (isset($data['measures'])) {
            foreach ($data['measures'] as $measureUuid => $measure_array) {
                // check if the measure is not already in the analysis
                // TODO: findByAnrAndUuid
                $measures = $measureTable->getEntityByFields([
                    'anr' => $anr->getId(),
                    'uuid' => $measureUuid
                ]);
                if (empty($measures)) {
                    // load the referential linked to the measure
                    // TODO: findByAnrAndUuid
                    $referentials = $referentialTable->getEntityByFields([
                        'anr' => $anr->getId(),
                        'uuid' => $measure_array['referential']
                    ]);
                    $soaCategories = $soaCategoryTable->getEntityByFields([
                        'anr' => $anr->getId(),
                        $labelKey => $measure_array['category']
                    ]);
                    if (!empty($referentials) && !empty($soaCategories)) {
                        // a measure must be linked to a referential and a category
                        $newMeasure = new Measure($measure_array);
                        $newMeasure->setAnr($anr);
                        $newMeasure->setReferential($referentials[0]);
                        $newMeasure->setCategory($soaCategories[0]);
                        $newMeasure->setAmvs(new ArrayCollection()); // need to initialize the amvs link
                        $newMeasure->setRolfRisks(new ArrayCollection());
                        $measureTable->save($newMeasure, false);
                        $measuresNewIds[$measureUuid] = $newMeasure;

                        if (!isset($data['soas'])) {
                            // if no SOAs in the analysis to import, create new ones
                            $newSoa = (new Soa())
                                ->setAnr($anr);
                            // TODO: return $this in setMeasure and join with previous chain calls.
                            $newSoa->setMeasure($newMeasure);
                            $soaTable->saveEntity($newSoa, false);
                        }
                    }
                }
            }

            $measureTable->getDb()->flush();
        }
        // import the measuresmeasures
        if (isset($data['measuresMeasures'])) {
            /** @var MeasureMeasureTable $measureMeasureTable */
            $measureMeasureTable = $this->get('measureMeasureTable');
            foreach ($data['measuresMeasures'] as $measureMeasure) {
                // check if the measuremeasure is not already in the analysis
                // TODO: findByAnrFatherAndChild(), but before get father/child them from previously saved or find in the db
                $measuresMeasures = $measureMeasureTable
                    ->getEntityByFields(['anr' => $anr->getId(),
                                         'father' => $measureMeasure['father'],
                                         'child' => $measureMeasure['child']]);
                if (empty($measuresMeasures)) {
                    // TODO: change the part with use object setters ->setFather() ->setChild()
                    $newMeasureMeasure = (new MeasureMeasure($measuresMeasures))
                        ->setAnr($anr);
                    $measureMeasureTable->save($newMeasureMeasure, false);
                }
            }
            $measureMeasureTable->getDb()->flush();
        }

        // import the SOAs
        if (isset($data['soas'])) {
            // TODO: findByAnr and replace the map as it won't work.
            $measuresStoredId = $measureTable->fetchAllFiltered(['uuid'], 1, 0, null, null, ['anr' => $anr->getId()], null, null);
            $measuresStoredId = array_map(function ($elt) {
                return (string)$elt['uuid'];
            }, $measuresStoredId);
            foreach ($data['soas'] as $soa) {
                // check if the corresponding measure has been created during this import.
                if (array_key_exists($soa['measure_id'], $measuresNewIds)) {
                    $newSoa = (new Soa($soa))
                        ->setAnr($anr);
                    // TODO: return $this from setMeasure and join this with chain calls.
                    $newSoa->setMeasure($measuresNewIds[$soa['measure_id']]);
                    $soaTable->saveEntity($newSoa, false);
                } elseif (in_array($soa['measure_id'], $measuresStoredId)) { //measure exist so soa exist (normally)
                    // TODO: findByMeasure or find a measure then $measure->getSoa() if possible
                    $existedSoa = $soaTable->getEntityByFields([
                        'measure' => [
                            'anr' => $anr->getId(),
                            'uuid' => $soa['measure_id']
                        ]
                    ]);
                    if (empty($existedSoa)) {
                        $newSoa = (new Soa($soa))
                            ->setAnr($anr);
                        // TODO: join setMeasure with prev chain calls, $measureTable->findByAnrAndUuid
                        $newSoa->setMeasure($measureTable->getEntity([
                            'anr' => $anr->getId(),
                            'uuid' => $soa['measure_id']
                        ]));
                        $soaTable->saveEntity($newSoa, false);
                    } else {
                        $existedSoa = $existedSoa[0];
                        $existedSoa->remarks = $soa['remarks'];
                        $existedSoa->evidences = $soa['evidences'];
                        $existedSoa->actions = $soa['actions'];
                        $existedSoa->compliance = $soa['compliance'];
                        $existedSoa->EX = $soa['EX'];
                        $existedSoa->LR = $soa['LR'];
                        $existedSoa->CO = $soa['CO'];
                        $existedSoa->BR = $soa['BR'];
                        $existedSoa->BP = $soa['BP'];
                        $existedSoa->RRA = $soa['RRA'];
                        $soaTable->saveEntity($existedSoa, false);
                    }
                }
            }

            $soaTable->getDb()->flush();
        }

        // import the GDPR records
        if (!empty($data['records'])) { //Data of records
            foreach ($data['records'] as $v) {
                $this->get('recordService')->importFromArray($v, $anr->getId());
            }
        }

        /*
         * Import scales.
         */
        /** @var InstanceTable $instanceTable */
        $instanceTable = $this->get('table');
        if (!empty($data['scales'])) {
            //Approximate values from destination analyse
            $ts = ['c', 'i', 'd'];
            /** @var InstanceSuperClass[] $instances */
            $instances = $instanceTable->findByAnrId($anr->getId());
            // TODO: findByAnr
            $consequences = $instanceConsequenceTable->getEntityByFields(['anr' => $anr->getId()]);
            $scalesOrig = [];
            /** @var ScaleTable $scaleTable */
            $scaleTable = $this->get('scaleTable');
            $scales = $scaleTable->findByAnr($anr);
            foreach ($scales as $sc) {
                $scalesOrig[$sc->get('type')]['min'] = $sc->get('min');
                $scalesOrig[$sc->get('type')]['max'] = $sc->get('max');
            }

            $minScaleImpOrig = $scalesOrig[Scale::TYPE_IMPACT]['min'];
            $maxScaleImpOrig = $scalesOrig[Scale::TYPE_IMPACT]['max'];
            $minScaleImpDest = $data['scales'][Scale::TYPE_IMPACT]['min'];
            $maxScaleImpDest = $data['scales'][Scale::TYPE_IMPACT]['max'];

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
                    $this->refreshImpactsInherited($instance);
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
                    $instanceConsequenceTable->save($conseq, false);
                }
            }

            // Threat Qualification
            $threats = $threatTable->findByAnr($anr);
            foreach ($threats as $threat) {
                $threat->set('qualification', $this->approximate(
                    $threat->get('qualification'),
                    $scalesOrig[Scale::TYPE_THREAT]['min'],
                    $scalesOrig[Scale::TYPE_THREAT]['max'],
                    $data['scales'][Scale::TYPE_THREAT]['min'],
                    $data['scales'][Scale::TYPE_THREAT]['max']
                ));
                $threatTable->saveEntity($threat, false);
            }

            // Information Risks
            /** @var InstanceRiskTable $instanceRiskTable */
            $instanceRiskTable = $this->get('instanceRiskTable');
            $risks = $instanceRiskTable->findByAnr($anr);
            foreach ($risks as $r) {
                $r->set('threatRate', $this->approximate(
                    $r->get('threatRate'),
                    $scalesOrig[Scale::TYPE_THREAT]['min'],
                    $scalesOrig[Scale::TYPE_THREAT]['max'],
                    $data['scales'][Scale::TYPE_THREAT]['min'],
                    $data['scales'][Scale::TYPE_THREAT]['max']
                ));
                $oldVulRate = $r->vulnerabilityRate;
                $r->set('vulnerabilityRate', $this->approximate(
                    $r->get('vulnerabilityRate'),
                    $scalesOrig[Scale::TYPE_VULNERABILITY]['min'],
                    $scalesOrig[Scale::TYPE_VULNERABILITY]['max'],
                    $data['scales'][Scale::TYPE_VULNERABILITY]['min'],
                    $data['scales'][Scale::TYPE_VULNERABILITY]['max']
                ));
                $newVulRate = $r->vulnerabilityRate;
                $r->set(
                    'reductionAmount',
                    $r->get('reductionAmount') !== 0
                        ? $this->approximate($r->get('reductionAmount'), 0, $oldVulRate, 0, $newVulRate, 0)
                        : 0
                );

                //TODO: find a faster way of updating risks.
                $instanceRiskService->update($r->id, $risks);
            }

            //Operational Risks
            $risksOp = $this->get('instanceRiskOpTable')->getEntityByFields(['anr' => $anr->getId()]);
            if (!empty($risksOp)) {
                foreach ($risksOp as $rOp) {
                    $toApproximate = [
                        Scale::TYPE_THREAT => [
                            'netProb',
                            'targetedProb',
                            'brutProb',
                        ],
                        Scale::TYPE_IMPACT => [
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
                    $this->get('instanceRiskOpService')->update($rOp->id, $risksOp);
                }
            }

            // Finally update scales from import
            $scales = $scaleTable->findByAnr($anr);
            $types = [
                Scale::TYPE_IMPACT,
                Scale::TYPE_THREAT,
                Scale::TYPE_VULNERABILITY,
            ];
            foreach ($types as $type) {
                foreach ($scales as $s) {
                    if ($s->getType() === $type) {
                        // TODO: use setters.
                        $s->min = $data['scales'][$type]['min'];
                        $s->max = $data['scales'][$type]['max'];
                        // TODO: shell we save the entity ?
                        // $scaleTable->saveEntity($s, false);
                    }
                }
            }
        }

        $first = true;
        $instanceIds = [];
        /** @var ScaleImpactTypeTable $scaleImpactTypeTable */
        $scaleImpactTypeTable = $this->get('scaleImpactTypeTable');
        // TODO: findByAnr.
        $nbScaleImpactTypes = count($scaleImpactTypeTable->getEntityByFields(['anr' => $anr->getId()]));
        usort($data['instances'], function ($a, $b) {
            return $a['instance']['position'] <=> $b['instance']['position'];
        });
        foreach ($data['instances'] as $inst) {
            if ($first) {
                if ($data['with_eval'] && isset($data['scales'])) {
                    $inst['with_eval'] = $data['with_eval'];
                    $inst['scales'] = $data['scales'];
                }
                $first = false;
            }
            $instanceId = $this->importInstanceFromArray($inst, $anr, $parent, $modeImport, $isRoot);
            if ($instanceId !== false) {
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
            $scaleComment = $this->get('scaleCommentTable')->getEntityByFields(
                ['anr' => $anr->getId()],
                ['id' => 'ASC']
            );
            foreach ($scaleComment as $sc) {
                if ($sc->scaleImpactType === null || $sc->scaleImpactType->isSys === 1) {
                    $this->get('scaleCommentTable')->delete($sc->id);
                }
            }
            $nbComment = count($data['scalesComments']);

            for ($pos = 1; $pos <= $nbComment; $pos++) {
                $scale = $this->get('scaleTable')->getEntityByFields([
                    'anr' => $anr->getId(),
                    'type' => $data['scalesComments'][$scIds[$pos]]['scale']['type']
                ]);
                foreach ($scale as $s) {
                    $sId = $s->get('id');
                }
                $OrigPosition = $data['scalesComments'][$scIds[$pos]]['scaleImpactType']['position'] ?? 0;
                $position = ($OrigPosition > 8) ? $OrigPosition + ($nbScaleImpactTypes - 8) : $OrigPosition;
                $scaleImpactType = $this->get('scaleImpactTypeTable')->getEntityByFields([
                    'anr' => $anr->getId(),
                    'position' => $position
                ]);
                foreach ($scaleImpactType as $si) {
                    $siId = $si->get('id');
                }
                $toExchange = $data['scalesComments'][$scIds[$pos]];
                $toExchange['anr'] = $anr->getId();
                $toExchange['scale'] = $sId;
                $toExchange['scaleImpactType'] = $siId;
                $this->get('scaleCommentService')->create($toExchange);
            }
        }

        //Add user consequences to all instances
        $instances = $instanceTable->findByAnrId($anr->getId());
        // TODO: findByAnr
        $scaleImpactTypes = $scaleImpactTypeTable->getEntityByFields(['anr' => $anr->getId()]);
        foreach ($instances as $instance) {
            foreach ($scaleImpactTypes as $siType) {
                $instanceConsequence = $instanceConsequenceTable->getEntityByFields([
                    'anr' => $anr->getId(),
                    'instance' => $instance->id,
                    'scaleImpactType' => $siType->id
                ]);
                if (empty($instanceConsequence)) {
                    $consequence = new InstanceConsequence();
                    $consequence->setDbAdapter($instanceConsequenceTable->getDb());
                    $consequence->setLanguage($this->getLanguage());
                    $consequence->exchangeArray([
                        'anr' => $anr->getId(),
                        'instance' => $instance->getId(),
                        'object' => $instance->getObject(),
                        'scaleImpactType' => $siType->getId(),
                    ]);
                    $this->setDependencies($consequence, ['anr', 'object', 'instance', 'scaleImpactType']);
                    $instanceConsequenceTable->save($consequence, false);
                }
            }
        }

        $instanceConsequenceTable->getDb()->flush();

        return $instanceIds;
    }

    /**
     * Method to approximate the value within new bounds, typically when the exported object had a min/max bound
     * bigger than the target's ANR bounds.
     *
     * @param int $x The value to approximate
     * @param int $minorig The source min bound
     * @param int $maxorig The source max bound
     * @param int $mindest The target min bound
     * @param int $maxdest The target max bound
     *
     * @return int|mixed The approximated value
     */
    private function approximate($x, $minorig, $maxorig, $mindest, $maxdest, $defaultvalue = -1)
    {
        if ($x == $maxorig) {
            return $maxdest;
        } elseif ($x != -1 && ($maxorig - $minorig) != -1) {
            return min(max(round(($x / ($maxorig - $minorig + 1)) * ($maxdest - $mindest + 1)), $mindest), $maxdest);
        }

        return $defaultvalue;
    }

    private function getMonarcVersion(): ?string
    {
        return $this->monarcVersion;
    }

    private function createSetOfRecommendations(array $data, Anr $anr, ?string $monarcVersion): void
    {
        /** @var RecommandationSetTable $recommendationSetTable */
        $recommendationSetTable = $this->get('recommandationSetTable');
        if (!empty($data['recSets'])) {
            /** @var RecommandationSetTable $recommendationSetTable */
            foreach ($data['recSets'] as $recSetUuid => $recommendationSetData) {
                if (!isset($this->sharedData['recSets'][$recSetUuid])) {
                    try {
                        $recommendationsSet = $recommendationSetTable->findByAnrAndUuid($anr, $recSetUuid);
                    } catch (EntityNotFoundException $e) {
                        $recommendationsSet = (new RecommandationSet())
                            ->setUuid($recSetUuid)
                            ->setAnr($anr)
                            ->setLabel1($recommendationSetData['label1'])
                            ->setLabel2($recommendationSetData['label2'])
                            ->setLabel3($recommendationSetData['label3'])
                            ->setLabel4($recommendationSetData['label4']);

                        $recommendationSetTable->saveEntity($recommendationsSet);
                    }

                    $this->sharedData['recSets'][$recSetUuid] = $recommendationsSet;
                }
            }
        } elseif (version_compare($monarcVersion, '2.8.4') === -1) {
            $recommendationsSets = $recommendationSetTable->getEntityByFields([
                'anr' => $anr->getId(),
                'label1' => 'Recommandations importées'
            ]);
            if (!empty($recommendationsSets)) {
                $recommendationSet = current($recommendationsSets);
            } else {
                $recommendationSet = (new RecommandationSet())
                    ->setAnr($anr)
                    ->setLabel1('Recommandations importées')
                    ->setLabel2('Imported recommendations')
                    ->setLabel3('Importierte empfehlungen')
                    ->setLabel4('Geïmporteerde aanbevelingen');

                $recommendationSetTable->saveEntity($recommendationSet);
            }

            $this->sharedData['recSets'][$recommendationSet->getUuid()] = $recommendationSet;
        }

        // Create recommendations not linked with recommendation risks.
        if (!empty($data['recs'])) {
            /** @var RecommandationTable $recommendationTable */
            $recommendationTable = $this->get('recommandationTable');
            foreach ($data['recs'] as $recUuid => $recommendationData) {
                if (!isset($this->sharedData['recs'][$recUuid])) {
                    try {
                        $recommendation = $recommendationTable->findByAnrAndUuid($anr, $recUuid);
                    } catch (EntityNotFoundException $e) {
                        $recommendation = (new Recommandation())
                            ->setUuid($recommendationData['uuid'])
                            ->setAnr($anr)
                            ->setRecommandationSet(
                                $this->sharedData['recSets'][$recommendationData['recommandationSet']]
                            )
                            ->setComment($recommendationData['comment'])
                            ->setResponsable($recommendationData['responsable'])
                            ->setStatus($recommendationData['status'])
                            ->setImportance($recommendationData['importance'])
                            ->setCode($recommendationData['code'])
                            ->setDescription($recommendationData['description'])
                            ->setCounterTreated($recommendationData['counterTreated']);

                        if (!empty($recommendationData['duedate']['date'])) {
                            $recommendation->setDueDate(new DateTime($recommendationData['duedate']['date']));
                        }

                        $recommendationTable->saveEntity($recommendation, false);
                    }

                    $this->sharedData['recs'][$recUuid] = $recommendation;
                }
            }
            $recommendationTable->getDb()->flush();
        }
    }

    /**
     * @throws EntityNotFoundException
     */
    private function processRecommendationDataLinkedToRisk(
        Anr $anr,
        array $recommendationData,
        bool $isRiskTreated
    ): Recommandation {
        /** @var RecommandationTable $recommendationTable */
        $recommendationTable = $this->get('recommandationTable');

        if (isset($this->sharedData['recs'][$recommendationData['uuid']])) {
            /** @var Recommandation $recommendation */
            $recommendation = $this->sharedData['recs'][$recommendationData['uuid']];
            if ($isRiskTreated && $recommendation->isPositionEmpty()) {
                $recommendation->setPosition(++$this->currentAnalyseMaxRecommendationPosition);
                $recommendationTable->saveEntity($recommendation, false);
            }

            return $recommendation;
        }

        if (isset($this->sharedData['recSets'][$recommendationData['recommandationSet']])) {
            $recommendationSet = $this->sharedData['recSets'][$recommendationData['recommandationSet']];
        } else {
            /** @var RecommandationSetTable $recommendationSetTable */
            $recommendationSetTable = $this->get('recommandationSetTable');
            $recommendationSet = $recommendationSetTable
                ->findByAnrAndUuid($anr, $recommendationData['recommandationSet']);

            $this->sharedData['recSets'][$recommendationSet->getUuid()] = $recommendationSet;
        }

        $recommendation = $recommendationTable->findByAnrCodeAndRecommendationSet(
            $anr,
            $recommendationData['code'],
            $recommendationSet
        );
        if ($recommendation === null) {
            $recommendation = (new Recommandation())->setUuid($recommendationData['uuid']);
        }

        $recommendation->setAnr($anr)
            ->setRecommandationSet($recommendationSet)
            ->setComment($recommendationData['comment'])
            ->setResponsable($recommendationData['responsable'])
            ->setStatus($recommendationData['status'])
            ->setImportance($recommendationData['importance'])
            ->setCode($recommendationData['code'])
            ->setDescription($recommendationData['description'])
            ->setCounterTreated($recommendationData['counterTreated']);
        if (!empty($recommendationData['duedate']['date'])) {
            $recommendation->setDueDate(new DateTime($recommendationData['duedate']['date']));
        }

        if ($isRiskTreated && $recommendation->isPositionEmpty()) {
            $recommendation->setPosition(++$this->currentAnalyseMaxRecommendationPosition);
        }

        $recommendationTable->saveEntity($recommendation, false);

        $this->sharedData['recs'][$recommendation->getUuid()] = $recommendation;

        return $recommendation;
    }
}
