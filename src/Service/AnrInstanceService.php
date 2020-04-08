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
use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Entity\InstanceRiskSuperClass;
use Monarc\Core\Model\Entity\InstanceSuperClass;
use Monarc\Core\Model\Entity\MonarcObject;
use Monarc\Core\Model\Entity\ObjectSuperClass;
use Monarc\Core\Model\Entity\QuestionChoiceSuperClass;
use Monarc\Core\Model\Entity\QuestionSuperClass;
use Monarc\Core\Model\Entity\Scale;
use Monarc\Core\Service\InstanceService;
use Monarc\FrontOffice\Model\Entity\Instance;
use Monarc\FrontOffice\Model\Entity\InstanceRisk;
use Monarc\FrontOffice\Model\Entity\MeasureMeasure;
use Monarc\FrontOffice\Model\Entity\Question;
use Monarc\FrontOffice\Model\Entity\QuestionChoice;
use Monarc\FrontOffice\Model\Entity\Recommandation;
use Monarc\FrontOffice\Model\Entity\RecommandationRisk;
use Monarc\FrontOffice\Model\Entity\RecommandationSet;
use Monarc\FrontOffice\Model\Entity\Referential;
use Monarc\FrontOffice\Model\Entity\Soa;
use Monarc\FrontOffice\Model\Entity\SoaCategory;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\InstanceConsequenceTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskTable;
use Monarc\FrontOffice\Model\Table\InstanceTable;
use Monarc\FrontOffice\Model\Table\MonarcObjectTable;
use Monarc\FrontOffice\Model\Table\RecommandationRiskTable;
use Monarc\FrontOffice\Model\Table\RecommandationSetTable;
use Monarc\FrontOffice\Model\Table\RecommandationTable;
use Monarc\FrontOffice\Model\Table\ScaleTable;
use Monarc\FrontOffice\Model\Table\UserAnrTable;
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
     * @see #importFromFile
     *
     * @param array $data The instance data
     * @param AnrSuperClass $anr The target ANR
     * @param null|InstanceSuperClass $parent The parent under which the instance should be imported, or null if at the root
     * @param string $modeImport Import mode, either 'merge' or 'duplicate'
     * @param bool $isRoot If the imported instance should be treated as a root instance
     *
     * @return array|bool An array of created instances IDs, or false in case of error
     */
    public function importFromArray(array $data, AnrSuperClass $anr, ?Instance $parent = null, $modeImport = 'merge', $isRoot = false)
    {
        $this->monarcVersion = $data['monarc_version'] ?? null;

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
        AnrSuperClass $anr,
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
        // TODO: use setters.
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
                        'anr' => $anr->get('id'),
                        'type' => Scale::TYPE_IMPACT
                    ]));
                }
                $scalesImpactType = $this->get('scaleImpactTypeTable')->getEntityByFields(['anr' => $anr->get('id')]);
                $localScalesImpactType = [];
                foreach ($scalesImpactType as $scale) {
                    $localScalesImpactType[$scale->get($labelKey)] = $scale->get('id');
                }
                unset($scalesImpactType);

                foreach ($data['consequences'] as $conseq) {
                    if (!isset($localScalesImpactType[$conseq['scaleImpactType'][$labelKey]])) {
                        $toExchange = $conseq['scaleImpactType'];
                        unset($toExchange['id']);
                        $toExchange['anr'] = $anr->get('id');
                        $toExchange['scale'] = $localScaleImpact->get('id');
                        $toExchange['implicitPosition'] = 2;

                        $class = $this->get('scaleImpactTypeTable')->getEntityClass();
                        $scaleImpT = new $class();
                        $scaleImpT->setDbAdapter($this->get('table')->getDb());
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
                    $toExchange['anr'] = $anr->get('id');
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
            // on génère celles par défaut

            $this->createInstanceConsequences($instance->getId(), $anr->get('id'), $monarcObject);
        }

        // Update impacts from brothers for global assets

        $instanceBrothers = current($this->get('table')->getEntityByFields([ // Get instance risk of brother
            'id' => ['op' => '!=', 'value' => $instance->getId()],
            'anr' => $anr->get('id'),
            'asset' => ['anr' => $anr->get('id'), 'uuid' => (string)$instance->get('asset')->get('uuid')],
            'object' => ['anr' => $anr->get('id'), 'uuid' => (string)$instance->get('object')->get('uuid')]
        ]));

        if (!empty($instanceBrothers)) {
            if ($instance->get('object')->get('scope') == MonarcObject::SCOPE_GLOBAL
                && $modeImport == 'merge'
            ) {
                $instanceConseqBrothers = $instanceConsequenceTable->getEntityByFields([ // Get consequences of brother
                    'anr' => $anr->get('id'),
                    'instance' => $instanceBrothers,
                    'object' => ['anr' => $anr->get('id'), 'uuid' => (string)$instance->get('object')->get('uuid')]
                ]);

                foreach ($instanceConseqBrothers as $icb) { //Update consequences for all brothers
                    $this->get('instanceConsequenceService')->updateBrothersConsequences(
                        $anr->get('id'),
                        $icb->get('id')
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
        }

        $this->refreshImpactsInherited($instance);

        $this->createSetOfRecommendations($data, $anr, $monarcVersion);

        if (!empty($data['risks'])) {
            // load of the existing value
            $this->sharedData['ivuls'] = $vCodes = [];
            $this->sharedData['ithreats'] = $tCodes = [];
            if (version_compare($monarcVersion, '2.8.2') >= 0) { //TO DO:set the right value with the uuid version
                foreach ($data['threats'] as $t) {
                    $tCodes[] = $t['uuid'];
                }
                $existingRisks = $instanceRiskService->get('threatTable')->getEntityByFields([
                    'anr' => $anr->get('id'),
                    'uuid' => $tCodes
                ]);
                foreach ($existingRisks as $t) {
                    $this->sharedData['ithreats'][] = (string)$t->get('uuid');
                }
                foreach ($data['vuls'] as $v) {
                    $vCodes[] = $v['uuid'];
                }
                $existingRisks = $instanceRiskService->get('vulnerabilityTable')->getEntityByFields([
                    'anr' => $anr->get('id'),
                    'uuid' => $vCodes
                ]);
                foreach ($existingRisks as $t) {
                    $this->sharedData['ivuls'][] = (string)$t->get('uuid');
                }
            } else {
                foreach ($data['threats'] as $t) {
                    $tCodes[$t['code']] = $t['code'];
                }
                $existingRisks = $instanceRiskService->get('threatTable')->getEntityByFields([
                    'anr' => $anr->get('id'),
                    'code' => $tCodes
                ]);
                foreach ($existingRisks as $t) {
                    $this->sharedData['ithreats'][$t->get('code')] = (string)$t->get('uuid');
                }
                foreach ($data['vuls'] as $v) {
                    $vCodes[$v['code']] = $v['code'];
                }
                $existingRisks = $instanceRiskService->get('vulnerabilityTable')->getEntityByFields([
                    'anr' => $anr->get('id'),
                    'code' => $vCodes
                ]);
                foreach ($existingRisks as $t) {
                    $this->sharedData['ivuls'][$t->get('code')] = (string)$t->get('uuid');
                }
            }

            /** @var InstanceRiskTable $instanceRiskTable */
            $instanceRiskTable = $this->get('instanceRiskTable');

            foreach ($data['risks'] as $risk) {
                //uuid id now the pivot instead of code
                if ($risk['specific']) {
                    // TODO: remove the support of the old version because it's a mess.
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
                        $toExchange['anr'] = $anr->get('id');
                        $class = $this->get('threatTable')->getEntityClass();
                        $threat = new $class();
                        $threat->setDbAdapter($instanceConsequenceTable->getDb());
                        $threat->setLanguage($this->getLanguage());
                        $threat->exchangeArray($toExchange);
                        $this->setDependencies($threat, ['anr']);
                        $tuuid = $instanceConsequenceTable->save($threat, false);
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
                        $toExchange['anr'] = $anr->get('id');
                        $class = $instanceRiskService->get('vulnerabilityTable')->getEntityClass();
                        $vul = new $class();
                        $vul->setDbAdapter($instanceRiskService->get('vulnerabilityTable')->getDb());
                        $vul->setLanguage($this->getLanguage());
                        $vul->exchangeArray($toExchange);
                        $this->setDependencies($vul, ['anr']);
                        $vuuid = $instanceRiskService->get('vulnerabilityTable')->save($vul, false);
                        if (version_compare($monarcVersion, "2.8.2") == -1) {
                            $this->sharedData['ivuls'][$data['vuls'][$risk['vulnerability']]['code']] = $vuuid;
                        }
                    }

                    $instanceBrothers = $this->get('table')->getEntityByFields([ // Get the Instance of brothers
                        'id' => ['op' => '!=', 'value' => $instance->getId()],
                        'anr' => $anr->get('id'),
                        'asset' => [
                            'anr' => $anr->get('id'),
                            'uuid' => (string)$instance->get('asset')->get('uuid')
                        ],
                        'object' => [
                            'anr' => $anr->get('id'),
                            'uuid' => (string)$monarcObject->getUuid()
                        ]
                    ]);

                    // Creation of specific risks to brothers
                    foreach ($instanceBrothers as $ib) {
                        $toExchange = $risk;
                        unset($toExchange['id']);
                        $toExchange['anr'] = $anr->get('id');
                        $toExchange['instance'] = $ib->get('id');
                        $toExchange['asset'] = (string)$monarcObject->getAsset()->getUuid();
                        $toExchange['amv'] = null;
                        $toExchange['threat'] = Uuid::isValid($risk['threat'])
                            ? $risk['threat']
                            : $this->sharedData['ithreats'][$data['threats'][$risk['threat']]['code']];
                        $toExchange['vulnerability'] = Uuid::isValid($risk['vulnerability'])
                            ? $risk['vulnerability']
                            : $this->sharedData['ivuls'][$data['vuls'][$risk['vulnerability']]['code']];
                        $rToBrother = new InstanceRisk();
                        $rToBrother->setDbAdapter($instanceRiskTable->getDb());
                        $rToBrother->setLanguage($this->getLanguage());
                        $rToBrother->exchangeArray($toExchange);
                        $this->setDependencies(
                            $rToBrother,
                            ['anr', 'amv', 'instance', 'asset', 'threat', 'vulnerability']
                        );
                        $idRiskSpecific = $instanceRiskTable->save($rToBrother, false);
                        $rToBrother->set('id', $idRiskSpecific);
                    }

                    $toExchange = $risk;
                    unset($toExchange['id']);
                    $toExchange['anr'] = $anr->get('id');
                    $toExchange['instance'] = $instance->getId();
                    $toExchange['asset'] = (string)$monarcObject->getAsset()->getUuid();
                    $toExchange['amv'] = null;
                    $toExchange['threat'] = Uuid::isValid($risk['threat'])
                        ? $risk['threat']
                        : $this->sharedData['ithreats'][$data['threats'][$risk['threat']]['code']];
                    $toExchange['vulnerability'] = Uuid::isValid($risk['vulnerability'])
                        ? $risk['vulnerability']
                        : $this->sharedData['ivuls'][$data['vuls'][$risk['vulnerability']]['code']];
                    $instanceRisk = new InstanceRisk();
                    $instanceRisk->setDbAdapter($instanceRiskTable->getDb());
                    $instanceRisk->setLanguage($this->getLanguage());
                    $instanceRisk->exchangeArray($toExchange);
                    $this->setDependencies($instanceRisk, ['anr', 'amv', 'instance', 'asset', 'threat', 'vulnerability']);
                    $idRisk = $instanceRiskTable->save($instanceRisk, false);
                    $instanceRisk->set('id', $idRisk);
                }

                $tuuid = Uuid::isValid($risk['threat'])
                    ? $risk['threat']
                    : $this->sharedData['ithreats'][$data['threats'][$risk['threat']]['code']];
                $vuuid = Uuid::isValid($risk['vulnerability'])
                    ? $risk['vulnerability']
                    : $this->sharedData['ivuls'][$data['vuls'][$risk['vulnerability']]['code']];

                /** @var InstanceRisk $instanceRisk */
                $instanceRisk = current($instanceRiskService->get('table')->getEntityByFields([
                    'anr' => $anr->get('id'),
                    'instance' => $instance->getId(),
                    'asset' => $monarcObject ? [
                        'anr' => $anr->getId(),
                        'uuid' => (string)$monarcObject->getAsset()->getUuid()
                    ] : null,
                    'threat' => ['anr' => $anr->get('id'), 'uuid' => $tuuid],
                    'vulnerability' => ['anr' => $anr->get('id'), 'uuid' => $vuuid]
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
                            ? $this->approximate($risk['reductionAmount'], 0, $risk['vulnerabilityRate'], 0, $instanceRisk->get('vulnerabilityRate'), 0)
                            : 0
                    );
                    $idRisk = $instanceRiskService->get('table')->save($instanceRisk, false);

                    // Merge all fields for global assets

                    if ($instance->get('object')->get('scope') === MonarcObject::SCOPE_GLOBAL
                        && $instanceRisk->get('specific') === 0
                        && $modeImport === 'merge'
                    ) {
                        $objectIdsBrothers = $this->get('table')->getEntityByFields([ // Get object Ids of brother
                            'anr' => $anr->get('id'),
                            'object' => [
                                'anr' => $anr->get('id'),
                                'uuid' => (string)$instance->get('object')->get('uuid'),
                            ],
                        ]);

                        // Get instance risk of brother
                        $instanceRiskBrothers = current($this->get('instanceRiskTable')->getEntityByFields([
                            'anr' => $anr->get('id'),
                            'instance' => ['op' => 'IN', 'value' => $objectIdsBrothers],
                            'amv' => [
                                'anr' => $anr->get('id'),
                                'uuid' => (string)$instanceRisk->get('amv')->get('uuid')
                            ]
                        ]));

                        if (!empty($instanceRiskBrothers)) {
                            $dataUpdate = [];
                            $dataUpdate['anr'] = $anr->get('id');
                            $dataUpdate['threatRate'] = $instanceRiskBrothers->threatRate; // Merge threat rate
                            $dataUpdate['vulnerabilityRate'] = $instanceRiskBrothers->vulnerabilityRate; // Merge vulnerability rate
                            $dataUpdate['kindOfMeasure'] = $instanceRiskBrothers->kindOfMeasure; // Merge kind Of Measure
                            $dataUpdate['reductionAmount'] = $instanceRiskBrothers->reductionAmount; // Merge reduction amount
                            if (strcmp($instanceRiskBrothers->comment, $instanceRisk->get('comment')) !== 0// Check if comment is different
                                && strpos($instanceRiskBrothers->comment, $instanceRisk->get('comment')) == false
                            ) { // Check if comment is not exist yet
                                $dataUpdate['comment'] = $instanceRiskBrothers->comment . "\n\n" . $instanceRisk->get('comment'); // Merge comments
                            } else {
                                $dataUpdate['comment'] = $instanceRiskBrothers->comment;
                            }

                            $instanceRiskService->update($instanceRisk->get('id'), $dataUpdate); // Finally update the risks
                        }
                    }

                    // Recommandations
                    if (!empty($data['recos'][$risk['id']])) {
                        /** @var RecommandationTable $recommendationTable */
                        $recommendationTable = $this->get('recommandationTable');

                        foreach ($data['recos'][$risk['id']] as $reco) {
                            if (isset($this->sharedData['recs'][$reco['uuid']])) {
                                /** @var Recommandation $recommendation */
                                $recommendation = $this->sharedData['recs'][$reco['uuid']];
                                if ($risk['kindOfMeasure'] !== InstanceRiskSuperClass::KIND_NOT_TREATED
                                    && $recommendation->isPositionEmpty()
                                ) {
                                    $recommendation->setPosition(++$this->currentAnalyseMaxRecommendationPosition);
                                    $recommendationTable->saveEntity($recommendation, false);
                                }
                            } else {
                                if (isset($this->sharedData['recSets'][$reco['recommandationSet']])) {
                                    $recommendationSet = $this->sharedData['recSets'][$reco['recommandationSet']];
                                } else {
                                    /** @var RecommandationSetTable $recommendationSetTable */
                                    $recommendationSetTable = $this->get('recommandationSetTable');
                                    $recommendationSet = $recommendationSetTable
                                        ->findByAnrAndUuid($anr, $reco['recommandationSet']);

                                    $this->sharedData['recSets'][$recommendationSet->getUuid()] = $recommendationSet;
                                }

                                $recommendation = $recommendationTable
                                    ->findByAnrCodeAndRecommendationSet($anr, $reco['code'], $recommendationSet);
                                if ($recommendation === null) {
                                    $recommendation = (new Recommandation())
                                        ->setUuid($reco['uuid']);
                                }

                                $recommendation->setAnr($anr)
                                    ->setRecommandationSet($recommendationSet)
                                    ->setComment($reco['comment'])
                                    ->setResponsable($reco['responsable'])
                                    ->setStatus($reco['status'])
                                    ->setImportance($reco['importance'])
                                    ->setCode($reco['code'])
                                    ->setDescription($reco['description'])
                                    ->setCounterTreated($reco['counterTreated']);

                                if ($risk['kindOfMeasure'] !== InstanceRiskSuperClass::KIND_NOT_TREATED
                                    && $recommendation->isPositionEmpty()
                                ) {
                                    $recommendation->setPosition(++$this->currentAnalyseMaxRecommendationPosition);
                                }

                                if (!empty($reco['duedate']['date'])) {
                                    $recommendation->setDueDate(new DateTime($reco['duedate']['date']));
                                }

                                $recommendationTable->saveEntity($recommendation, false);

                                $this->sharedData['recs'][$recommendation->getUuid()] = $recommendation;
                            }

                            /** @var RecommandationRiskTable $recommendationRiskTable */
                            $recommendationRiskTable = $this->get('recommandationRiskTable');
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
                            if ($recommendationRisk->hasGlobalObjectRelation() && $modeImport === 'merge') {
                                /** @var Instance[] $brotherInstances */
                                $brotherInstances = $this->get('table')->getEntityByFields([ // Get the brothers
                                    'anr' => $anr->get('id'),
                                    'asset' => [
                                        'anr' => $anr->get('id'),
                                        'uuid' => (string)$monarcObject->get('asset')->get('uuid')
                                    ],
                                    'object' => ['anr' => $anr->get('id'), 'uuid' => (string)$monarcObject->get('uuid')]
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
                                                    'uuid' => (string)$instanceRisk->getThreat()->getUuid()
                                                ],
                                                'vulnerability' => [
                                                    'anr' => $anr->get('id'),
                                                    'uuid' => (string)$instanceRisk->get('vulnerability')->get('uuid')
                                                ]
                                            ]);
                                        } else {
                                            $brothers = $recommendationRiskTable->getEntityByFields([
                                                'anr' => $anr->getId(),
                                                'instance' => $brotherInstance->getId(),
                                                'amv' => [
                                                    'anr' => $anr->getId(),
                                                    'uuid' => (string)$instanceRisk->getAmv()->getUuid()
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
                $instanceBrother = current($this->get('table')->getEntityByFields([
                    'id' => ['op' => '!=', 'value' => $instance->getId()],
                    'anr' => $anr->get('id'),
                    'asset' => ['anr' => $anr->get('id'), 'uuid' => (string)$monarcObject->get('asset')->get('uuid')],
                    'object' => ['anr' => $anr->get('id'), 'uuid' => (string)$monarcObject->get('uuid')]
                ]));

                if ($instanceBrother !== null && $instanceRisk !== null && !$instanceRisk->isSpecific()) {
                    /** @var InstanceRiskTable $instanceRiskTable */
                    $instanceRiskTable = $this->get('instanceRiskTable');
                    $instanceRiskBrothers = $instanceRiskTable->findByInstanceAndAmv(
                        $instanceBrother,
                        $instanceRisk->getAmv()
                    );

                    /** @var RecommandationRiskTable $recommendationRiskTable */
                    $recommendationRiskTable = $this->get('recommandationRiskTable');
                    foreach ($instanceRiskBrothers as $instanceRiskBrother) {
                        /** @var RecommandationRisk[] $brotherRecoRisks */
                        $brotherRecoRisks = $recommendationRiskTable->getEntityByFields([ // Get recommendation of brother
                            'anr' => $anr->getId(),
                            'instanceRisk' => $instanceRiskBrother->getId(),
                            'instance' => ['op' => '!=', 'value' => $instance->getId()],
                            'globalObject' => [
                                'anr' => $anr->getId(),
                                'uuid' => (string)$monarcObject->getUuid(),
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
            $specificRisks = $this->get('instanceRiskTable')->getEntityByFields([ // Get all specific risks of instance
                'anr' => $anr->get('id'),
                'instance' => $instance->getId(),
                'specific' => 1]);
            foreach ($specificRisks as $sr) {
                $exitingRecoRisks = $this->get('recommandationRiskTable')->getEntityByFields([ // Get recommandations of brothers
                    'anr' => $anr->get('id'),
                    'asset' => ['anr' => $anr->get('id'), 'uuid' => (string)$sr->get('asset')->get('uuid')],
                    'threat' => ['anr' => $anr->get('id'), 'uuid' => (string)$sr->get('threat')->get('uuid')],
                    'vulnerability' => ['anr' => $anr->get('id'), 'uuid' => (string)$sr->get('vulnerability')->get('uuid')]]);
                foreach ($exitingRecoRisks as $err) {
                    if ($instance->getId() != $err->get('instance')->get('id')) {
                        $recoToCreate[] = $err;
                    }
                }
            }
            /** @var RecommandationRiskTable $recommendationRiskTable */
            $recommendationRiskTable = $this->get('recommandationRiskTable');
            foreach ($recoToCreate as $rtc) {
                $RecoCreated = $recommendationRiskTable->getEntityByFields([ // Check if reco-risk link exist
                    'recommandation' => ['anr' => $anr->get('id'), 'uuid' => (string)$rtc->recommandation->uuid],
                    'instance' => $instance->getId(),
                    'asset' => ['anr' => $anr->get('id'), 'uuid' => (string)$rtc->asset->uuid],
                    'threat' => ['anr' => $anr->get('id'), 'uuid' => (string)$rtc->threat->uuid],
                    'vulnerability' => ['anr' => $anr->get('id'), 'uuid' => (string)$rtc->vulnerability->uuid]]);

                if (empty($RecoCreated)) {// Creation of link reco -> risk
                    $rrb = new RecommandationRisk();
                    // TODO: use setters.
                    $rrb->setDbAdapter($recommendationRiskTable->getDb());
                    $rrb->setLanguage($this->getLanguage());
                    $toExchange = [
                        'anr' => $anr->get('id'),
                        'recommandation' => ['anr' => $anr->get('id'), 'uuid' => $rtc->recommandation->uuid],
                        'instanceRisk' => $idRiskSpecific = current($this->get('instanceRiskTable')->getEntityByFields([
                            'anr' => $anr->get('id'),
                            'instance' => $instance->getId(),
                            'specific' => 1,
                            'asset' => ['anr' => $anr->get('id'), 'uuid' => (string)$rtc->getAsset()->getUuid()],
                            'threat' => ['anr' => $anr->get('id'), 'uuid' => (string)$rtc->getThreat()->getUuid()],
                            'vulnerability' => ['anr' => $anr->get('id'), 'uuid' => (string)$rtc->getVulnerability()->getUuid()]])),
                        'instance' => $instance->getId(),
                        'globalObject' => ['anr' => $anr->get('id'), 'uuid' => (string)$rtc->getGlobalObject()->getUuid()],
                        'asset' => ['anr' => $anr->get('id'), 'uuid' => (string)$rtc->getAsset()->getUuid()],
                        'threat' => ['anr' => $anr->get('id'), 'uuid' => (string)$rtc->getThreat()->getUuid()],
                        'vulnerability' => ['anr' => $anr->get('id'), 'uuid' => (string)$rtc->getVulnerability()->getUuid()],
                        'commentAfter' => $rtc->commentAfter,
                        'op' => 0,
                        'risk' => $idRiskSpecific,
                    ];
                    $rrb->exchangeArray($toExchange);
                    $this->setDependencies($rrb, ['anr', 'recommandation', 'instanceRisk', 'instance', 'globalObject', 'asset', 'threat', 'vulnerability']);
                    $recommendationRiskTable->saveEntity($rrb, false);
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
            $k = 0;

            foreach ($data['risksop'] as $ro) {
                // faut penser à actualiser l'anr_id, l'instance_id, l'object_id. Le risk_id quant à lui n'est pas repris dans l'export, on s'en moque donc
                $class = $this->get('instanceRiskOpService')->get('table')->getEntityClass();
                // TODO: use entity class directly and setters.
                $instanceRisk = new $class();
                $ro['rolfRisk'] = null;
                $toExchange = $ro;
                unset($toExchange['id']);
                $toExchange['anr'] = $anr->getId();
                $toExchange['instance'] = $instance->getId();
                $toExchange['object'] = $idObject;
                $tagId = $this->get('objectExportService')->get('table')->getEntity([
                    'anr' => $anr->getId(),
                    'uuid' => $idObject
                ])->get('rolfTag');
                if (null !== $tagId) {
                    $rolfRisks = $tagId->risks;
                    $toExchange['rolfRisk'] = $rolfRisks[$k]->id;
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
                $instanceRisk->setDbAdapter($this->get('instanceRiskOpService')->get('table')->getDb());
                $instanceRisk->setLanguage($this->getLanguage());
                $instanceRisk->exchangeArray($toExchange);
                $this->setDependencies($instanceRisk, ['anr', 'instance', 'object', 'rolfRisk']);
                $idRiskOp = $this->get('instanceRiskOpService')->get('table')->save($instanceRisk, false);

                // Recommandations
                $recommendationRiskTable = $this->get('recommandationRiskTable');
                if ($includeEval && !empty($data['recosop'][$ro['id']]) && !empty($idRiskOp)) {
                    /** @var RecommandationTable $recommendationTable */
                    $recommendationTable = $this->get('recommandationTable');
                    foreach ($data['recosop'][$ro['id']] as $reco) {
                        //2.8.3
                        if (version_compare($monarcVersion, "2.8.4") == -1) {
                            unset($reco['id']);
                            $recs = $recommendationTable->getEntityByFields([
                                'code' => $reco['code'],
                                'description' => $reco['description']
                            ]);
                            if (!empty($recs)) {
                                $reco['uuid'] = $recs[0]->get('uuid');
                            }
                            $reco['recommandationSet'] = current($this->sharedData['recSets']);
                        }
                        // TODO: use the table find method.
                        $recommendationSetTable = $this->get('recommandationSetTable');
                        $recSets = $recommendationSetTable->getEntityByFields([
                            'anr' => $anr->getId(),
                            'uuid' => $reco['recommandationSet']
                        ]);
                        // La recommandation
                        if (isset($this->sharedData['recs'][$reco['uuid']])) {
                            // Cette recommandation a déjà été gérée dans cet import
                            if ($ro['kindOfMeasure'] != InstanceRiskSuperClass::KIND_NOT_TREATED) {
                                if ($recommendation->isPositionEmpty()) {
                                    $recommendation->setPosition(++$this->currentAnalyseMaxRecommendationPosition);
                                    $recommendation->setRecommandationSet($recSets[0]);
                                    // Check if we call saveEntity with second argument = false.
                                    $reco['uuid'] = $recommendationTable->saveEntity($recommendation, false);
                                }
                            }
                        } else {
                            // sinon, on teste sa présence
                            $toExchange = $reco;
                            unset($toExchange['commentAfter']); // data du link
                            $toExchange['anr'] = $anr->getId();

                            $recommendation = $recommendationTable->findByAnrCodeAndRecommendationSet(
                                $anr,
                                $reco['code'],
                                $recSets[0]
                            );
                            if ($recommendation === null) {
                                // extract the other part to a separate method and use it here as well
                                $recommendation = new Recommandation();
                                if ($ro['kindOfMeasure'] === InstanceRiskSuperClass::KIND_NOT_TREATED) {
                                    $toExchange['position'] = 0;
                                } else {
                                    $toExchange['position'] = ++$this->currentAnalyseMaxRecommendationPosition;
                                }
                            } elseif ($recommendation->get('position') <= 0
                                && $ro['kindOfMeasure'] !== InstanceRiskSuperClass::KIND_NOT_TREATED
                            ) {
                                $toExchange['position'] = ++$this->currentAnalyseMaxRecommendationPosition;
                            }

                            $recommendation->setDbAdapter($recommendationTable->getDb());
                            $recommendation->setLanguage($this->getLanguage());
                            $recommendation->exchangeArray($toExchange, $recommendation->get('uuid'));
                            $this->setDependencies($recommendation, ['anr']);
                            if (isset($toExchange['duedate']['date'])) {
                                $recommendation->setDueDate(new DateTime($toExchange['duedate']['date']));
                            }
                            $recommendation->setRecommandationSet($recSets[0]);
                            $reco['uuid'] = $recommendationTable->save($recommendation);
                            $sharedData['recos'][$reco['uuid']] = $reco['uuid'];
                        }

                        $rr = new RecommandationRisk();
                        // TODO: use setters.
                        $rr->setDbAdapter($recommendationRiskTable->getDb());
                        $rr->setLanguage($this->getLanguage());
                        $toExchange = [
                            'recommandation' => $reco['uuid'],
                            'instanceRiskOp' => $idRiskOp,
                            'instance' => $instance->getId(),
                            'globalObject' => $monarcObject && $monarcObject->get('scope') === ObjectSuperClass::SCOPE_GLOBAL
                                ? (string)$monarcObject->get('uuid')
                                : null,
                            'asset' => null,
                            'threat' => null,
                            'vulnerability' => null,
                            'commentAfter' => $reco['commentAfter'],
                            'op' => 1,
                            'risk' => $idRiskOp,
                        ];
                        $rr->exchangeArray($toExchange);
                        $this->setDependencies($rr, ['recommandation', 'instanceRiskOp', 'instance', 'globalObject', 'asset', 'threat', 'vulnerability']);
                        $rr->setAnr($anr);
                        $recommendationRiskTable->saveEntity($rr, false);
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
        AnrSuperClass $anr,
        ?Instance $parent,
        string $modeImport,
        bool $isRoot = false
    ): array {
        /** @var AnrInstanceRiskService $instanceRiskService */
        $instanceRiskService = $this->get('instanceRiskService');
        /** @var InstanceConsequenceTable $instanceConsequenceTable */
        $instanceConsequenceTable = $this->get('instanceConsequenceTable');

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
                    $toExchange['anr'] = $anr->get('id');
                    $class = $this->get('interviewTable')->getEntityClass();
                    $newInterview = new $class();
                    $newInterview->setDbAdapter($this->get('interviewTable')->getDb());
                    $newInterview->setLanguage($this->getLanguage());
                    $newInterview->exchangeArray($toExchange);
                    $this->setDependencies($newInterview, ['anr']);
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
                foreach ($data['method']['deliveries'] as $key => $v) {
                    $toExchange = $data['method']['deliveries'][$key];
                    $toExchange['anr'] = $anr->get('id');
                    $class = $this->get('deliveryTable')->getEntityClass();
                    $newDelivery = new $class();
                    $newDelivery->setDbAdapter($this->get('deliveryTable')->getDb());
                    $newDelivery->setLanguage($this->getLanguage());
                    $newDelivery->exchangeArray($toExchange);
                    $this->setDependencies($newDelivery, ['anr']);
                    $this->get('deliveryTable')->save($newDelivery, false);
                }
                $this->get('deliveryTable')->getDb()->flush();
            }

            if (!empty($data['method']['questions'])) { // Questions of trends evaluation
                $questions = $this->get('questionTable')->getEntityByFields(['anr' => $anr->getId()]);
                foreach ($questions as $question) {
                    $this->get('questionTable')->delete($question->id);
                }

                foreach ($data['method']['questions'] as $position => $questionData) {
                    $newQuestion = new Question();
                    $newQuestion->setDbAdapter($this->get('questionTable')->getDb());
                    $newQuestion->setLanguage($this->getLanguage());
                    $newQuestion->setAnr($anr);
                    $newQuestion->set('position', $position);
                    $newQuestion->exchangeArray($questionData);
                    $this->get('questionTable')->save($newQuestion, false);

                    if ((int)$questionData['multichoice'] === 1) {
                        foreach ($data['method']['questionChoice'] as $questionChoiceData) {
                            if ($questionChoiceData['question'] === $questionData['id']) {
                                $newQuestionChoice = new QuestionChoice();
                                $newQuestionChoice->setDbAdapter($this->get('questionChoiceTable')->getDb());
                                $newQuestionChoice->setLanguage($this->getLanguage());
                                $newQuestionChoice->exchangeArray($questionChoiceData);
                                $newQuestionChoice->setAnr($anr)->setQuestion($newQuestion);
                                $this->get('questionChoiceTable')->save($newQuestionChoice, false);
                            }
                        }
                    }
                }

                $this->get('questionTable')->getDb()->flush();

                /** @var QuestionSuperClass[] $questions */
                $questions = $this->get('questionTable')->getEntityByFields(['anr' => $anr->getId()]);

                /** @var QuestionChoiceSuperClass[] $questionChoices */
                $questionChoices = $this->get('questionChoiceTable')->getEntityByFields(['anr' => $anr->getId()]);

                foreach ($data['method']['questions'] as $questionAnswerData) {
                    foreach ($questions as $question) {
                        if ($question->get($labelKey) === $questionAnswerData[$labelKey]) {
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
                                $this->get('questionTable')->save($question, false);
                            } else {
                                $question->response = $questionAnswerData['response'];
                                $this->get('questionTable')->save($question, false);
                            }
                        }
                    }
                }

                $this->get('questionTable')->getDb()->flush();
            }

            if (!empty($data['method']['threats'])) { // Evaluation of threats
                foreach ($data['method']['threats'] as $tId => $v) {
                    if (!empty($data['method']['threats'][$tId]['theme'])) {
                        // TODO: avoid such queries or check to add indexes or fetch all for the ANR and iterate in the code.
                        $themes = $this->get('themeTable')->getEntityByFields([
                            'anr' => $anr->getId(),
                            $labelKey => $data['method']['threats'][$tId]['theme'][$labelKey]
                        ], ['id' => 'ASC']);
                        if (empty($themes)) { // Creation of new theme if no exist
                            $toExchange = $data['method']['threats'][$tId]['theme'];
                            $toExchange['anr'] = $anr->get('id');
                            $class = $this->get('themeTable')->getEntityClass();
                            $newTheme = new $class();
                            $newTheme->setDbAdapter($this->get('themeTable')->getDb());
                            $newTheme->setLanguage($this->getLanguage());
                            $newTheme->exchangeArray($toExchange);
                            $this->setDependencies($newTheme, ['anr']);
                            $this->get('themeTable')->save($newTheme, false);
                            $data['method']['threats'][$tId]['theme']['id'] = $newTheme->id;
                        } else {
                            foreach ($themes as $th) {
                                $data['method']['threats'][$tId]['theme']['id'] = $th->id;
                            }
                        }
                    }
                    $threats = $this->get('threatTable')->getEntityByFields([
                        'anr' => $anr->getId(),
                        'code' => $data['method']['threats'][$tId]['code']
                    ], ['uuid' => 'ASC']);
                    if (empty($threats)) {
                        $toExchange = $data['method']['threats'][$tId];
                        $toExchange['anr'] = $anr->get('id');
                        $toExchange['mode'] = 0;
                        $toExchange['theme'] = $data['method']['threats'][$tId]['theme']['id'];
                        $class = $this->get('threatTable')->getEntityClass();
                        $newThreat = new $class();
                        $newThreat->setDbAdapter($this->get('threatTable')->getDb());
                        $newThreat->setLanguage($this->getLanguage());
                        $newThreat->exchangeArray($toExchange);
                        $this->setDependencies($newThreat, ['anr', 'theme']);
                        $this->get('threatTable')->save($newThreat, false);
                    } else {
                        foreach ($threats as $t) {
                            $t->set('trend', $data['method']['threats'][$tId]['trend']);
                            $t->set('comment', $data['method']['threats'][$tId]['comment']);
                            $t->set('qualification', $data['method']['threats'][$tId]['qualification']);
                            $this->get('threatTable')->save($t, false);
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
                    ->getEntityByFields(['anr' => $anr->getId(), 'uuid' => $referentialUUID]);
                if (empty($referentials)) {
                    $newReferential = new Referential($referential_array);
                    $newReferential->setAnr($anr);
                    $this->get('referentialTable')->save($newReferential, false);
                }
            }
        }
        // import the soacategories
        if (isset($data['soacategories'])) {
            foreach ($data['soacategories'] as $soaCategory) {
                // load the referential linked to the soacategory
                $referentials = $this->get('referentialTable')->getEntityByFields([
                    'anr' => $anr->getId(),
                    'uuid' => $soaCategory['referential']
                ]);
                if (!empty($referentials)) {
                    $categories = $this->get('soaCategoryTable')->getEntityByFields(['anr' => $anr->getId(),
                        $labelKey => $soaCategory[$labelKey],
                        'referential' => [
                            'anr' => $anr->getId(),
                            'uuid' => $referentials[0]->uuid
                        ]
                    ]);
                    if (empty($categories)) {
                        $newSoaCategory = new SoaCategory($soaCategory);
                        $newSoaCategory->setAnr($anr);
                        $newSoaCategory->setReferential($referentials[0]);
                        $this->get('soaCategoryTable')->save($newSoaCategory, false);
                    }
                }
            }
            $this->get('soaCategoryTable')->getDb()->flush();
        }

        // import the measures
        $measuresNewIds = [];
        if (isset($data['measures'])) {
            foreach ($data['measures'] as $measureUuid => $measure_array) {
                // check if the measure is not already in the analysis
                $measures = $this->get('measureTable')->getEntityByFields([
                    'anr' => $anr->getId(),
                    'uuid' => $measureUuid
                ]);
                if (empty($measures)) {
                    // load the referential linked to the measure
                    $referentials = $this->get('referentialTable')
                        ->getEntityByFields(['anr' => $anr->getId(),
                            'uuid' => $measure_array['referential']]);
                    $soaCategories = $this->get('soaCategoryTable')
                        ->getEntityByFields(['anr' => $anr->getId(),
                            $labelKey => $measure_array['category']]);
                    if (!empty($referentials) && !empty($soaCategories)) {
                        // a measure must be linked to a referential and a category
                        $newMeasure = new \Monarc\FrontOffice\Model\Entity\Measure($measure_array);
                        $newMeasure->setAnr($anr);
                        $newMeasure->setReferential($referentials[0]);
                        $newMeasure->setCategory($soaCategories[0]);
                        $newMeasure->setAmvs(new ArrayCollection()); // need to initialize the amvs link
                        $newMeasure->setRolfRisks(new ArrayCollection());
                        $this->get('measureTable')->save($newMeasure, false);
                        $measuresNewIds[$measureUuid] = $newMeasure;

                        if (!isset($data['soas'])) {
                            // if no SOAs in the analysis to import, create new ones
                            $newSoa = new Soa();
                            $newSoa->setAnr($anr);
                            $newSoa->setMeasure($newMeasure);
                            $this->get('soaTable')->save($newSoa, false);
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
                    ->getEntityByFields(['anr' => $anr->getId(),
                        'father' => $measureMeasure['father'],
                        'child' => $measureMeasure['child']]);
                if (empty($measuresmeasures)) {
                    $newMeasureMeasure = new MeasureMeasure($measureMeasure);
                    $newMeasureMeasure->setAnr($anr);
                    $this->get('measureMeasureTable')->save($newMeasureMeasure, false);
                }
            }

            $this->get('measureMeasureTable')->getDb()->flush();
        }

        // import the SOAs
        if (isset($data['soas'])) {
            $measuresStoredId = $this->get('measureTable')->fetchAllFiltered(['uuid'], 1, 0, null, null, ['anr' => $anr->get('id')], null, null);
            $measuresStoredId = array_map(function ($elt) {
                return (string)$elt['uuid'];
            }, $measuresStoredId);
            foreach ($data['soas'] as $soa) {
                // check if the corresponding measure has been created during
                // this import
                if (array_key_exists($soa['measure_id'], $measuresNewIds)) {
                    $newSoa = new Soa($soa);
                    $newSoa->setAnr($anr);
                    $newSoa->setMeasure($measuresNewIds[$soa['measure_id']]);
                    $this->get('soaTable')->save($newSoa, false);
                } elseif (in_array($soa['measure_id'], $measuresStoredId)) { //measure exist so soa exist (normally)
                    $soaExistant = $this->get('soaTable')->getEntityByFields([
                        'measure' => [
                            'anr' => $anr->getId(),
                            'uuid' => $soa['measure_id']
                        ]
                    ]);
                    if (empty($soaExistant)) {
                        $newSoa = new Soa($soa);
                        $newSoa->setAnr($anr);
                        $newSoa->setMeasure($this->get('measureTable')->getEntity([
                            'anr' => $anr->getId(),
                            'uuid' => $soa['measure_id']
                        ]));
                        $this->get('soaTable')->save($newSoa, false);
                    } else {
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
                        $this->get('soaTable')->save($soaExistant, false);
                    }
                }
            }

            $this->get('soaTable')->getDb()->flush();
        }

        // import the GDPR records
        if (!empty($data['records'])) { //Data of records
            foreach ($data['records'] as $v) {
                $this->get('recordService')->importFromArray($v, $anr->get('id'));
            }
        }
        // import scales
        if (!empty($data['scales'])) {
            //Approximate values from destination analyse
            $ts = ['c', 'i', 'd'];
            /** @var InstanceSuperClass[] $instances */
            $instances = $this->get('table')->getEntityByFields(['anr' => $anr->getId()]);
            $consequences = $instanceConsequenceTable->getEntityByFields(['anr' => $anr->getId()]);
            $scalesOrig = [];
            $scales = $this->get('scaleTable')->getEntityByFields(['anr' => $anr->getId()]);
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
            $threats = $this->get('threatTable')->getEntityByFields(['anr' => $anr->get('id')]);
            foreach ($threats as $t) {
                $t->set('qualification', $this->approximate(
                    $t->get('qualification'),
                    $scalesOrig[Scale::TYPE_THREAT]['min'],
                    $scalesOrig[Scale::TYPE_THREAT]['max'],
                    $data['scales'][Scale::TYPE_THREAT]['min'],
                    $data['scales'][Scale::TYPE_THREAT]['max']
                ));
                $this->get('threatTable')->save($t, false);
            }

            // Information Risks
            $risks = $instanceRiskService->get('table')->getEntityByFields(['anr' => $anr->get('id')]);
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

                $instanceRiskService->update($r->id, $risks);
            }

            //Operational Risks
            $risksOp = $this->get('instanceRiskOpService')->get('table')->getEntityByFields(['anr' => $anr->get('id')]);
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
            $scales = $this->get('scaleTable')->getEntityByFields(['anr' => $anr->getId()]);
            $types = [
                Scale::TYPE_IMPACT,
                Scale::TYPE_THREAT,
                Scale::TYPE_VULNERABILITY,
            ];
            foreach ($types as $type) {
                foreach ($scales as $s) {
                    if ($s->type === $type) {
                        $s->min = $data['scales'][$type]['min'];
                        $s->max = $data['scales'][$type]['max'];
                    }
                }
            }
        }

        $first = true;
        $instanceIds = [];
        $nbScaleImpactTypes = count($this->get('scaleImpactTypeTable')->getEntityByFields(['anr' => $anr->getId()]));
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
                $toExchange['anr'] = $anr->get('id');
                $toExchange['scale'] = $sId;
                $toExchange['scaleImpactType'] = $siId;
                $this->get('scaleCommentService')->create($toExchange);
            }
        }

        //Add user consequences to all instances
        $instances = $this->get('table')->getEntityByFields(['anr' => $anr->getId()]);
        $scaleImpactTypes = $this->get('scaleImpactTypeTable')->getEntityByFields(['anr' => $anr->getId()]);
        foreach ($instances as $instance) {
            foreach ($scaleImpactTypes as $siType) {
                $instanceConsequence = $instanceConsequenceTable->getEntityByFields([
                    'anr' => $anr->getId(),
                    'instance' => $instance->id,
                    'scaleImpactType' => $siType->id
                ]);
                if (empty($instanceConsequence)) {
                    $class = $instanceConsequenceTable->getEntityClass();
                    $consequence = new $class();
                    $consequence->setDbAdapter($instanceConsequenceTable->getDb());
                    $consequence->setLanguage($this->getLanguage());
                    $consequence->exchangeArray([
                        'anr' => $anr->get('id'),
                        'instance' => $instance->id,
                        'object' => $instance->object,
                        'scaleImpactType' => $siType->id,
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

    private function createSetOfRecommendations(array $data, AnrSuperClass $anr, ?string $monarcVersion): void
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
}
