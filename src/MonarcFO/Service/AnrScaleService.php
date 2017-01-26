<?php
namespace MonarcFO\Service;

use MonarcFO\Model\Entity\Scale;

/**
 * Anr Scale Service
 *
 * Class AnrScaleService
 * @package MonarcFO\Service
 */
class AnrScaleService extends \MonarcCore\Service\AbstractService
{
    protected $filterColumns = [];
    protected $anrTable;
    protected $userAnrTable;
    protected $AnrCheckStartedService;
    protected $scaleImpactTypeService;
    protected $dependencies = ['anr'];
    protected $types = [
        Scale::TYPE_IMPACT => 'impact',
        Scale::TYPE_THREAT => 'threat',
        Scale::TYPE_VULNERABILITY => 'vulnerability',
    ];

    /**
     * Get Types
     *
     * @return array
     */
    public function getTypes()
    {
        return $this->types;
    }

    /**
     * Get List
     *
     * @param int $page
     * @param int $limit
     * @param null $order
     * @param null $filter
     * @return mixed
     */
    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null)
    {
        $anrId = isset($filterAnd['anr']) ? $filterAnd['anr'] : null;

        $scales = parent::getList($page, $limit, $order, $filter, $filterAnd);

        $types = $this->getTypes();

        foreach ($scales as $key => $scale) {
            $scales[$key]['type'] = $types[$scale['type']];
        }

        return [$scales, $this->get('AnrCheckStartedService')->canChange($anrId)];
    }

    /**
     * Create
     *
     * @param $data
     * @param bool $last
     * @return mixed
     * @throws \Exception
     */
    public function create($data, $last = true)
    {
        $anrId = isset($filterAnd['anr']) ? $filterAnd['anr'] : null;
        if ($this->get('AnrCheckStartedService')->canChange($anrId)) {
            //scale
            $class = $this->get('entity');
            $entity = new $class();

            $entity->exchangeArray($data);
            $entity->setId(null);

            $dependencies = (property_exists($this, 'dependencies')) ? $this->dependencies : [];
            $this->setDependencies($entity, $dependencies);

            $scaleId = $this->get('table')->save($entity);

            //scale type
            if ($entity->type == Scale::TYPE_IMPACT) {
                $langs = [
                    'fr' => [
                        'C' => 'Confidentialité',
                        'I' => 'Intégrité',
                        'D' => 'Disponibilité',
                        'R' => 'Réputation',
                        'O' => 'Opérationnel',
                        'L' => 'Légal',
                        'F' => 'Financier',
                        'P' => 'Personne'
                    ],
                    'en' => [
                        'C' => 'Confidentiality',
                        'I' => 'Integrity',
                        'D' => 'Availability',
                        'R' => 'Reputation',
                        'O' => 'Operational',
                        'L' => 'Legal',
                        'F' => 'Financial',
                        'P' => 'Person'
                    ],
                    'de' => [
                        'C' => 'Vertraulichkeit',
                        'I' => 'Integrität',
                        'D' => 'Verfügbarkeit',
                        'R' => 'Ruf',
                        'O' => 'Einsatzbereit',
                        'L' => 'Legal',
                        'F' => 'Finanziellen',
                        'P' => 'Person'
                    ],
                    '0' => [
                        'C' => '',
                        'I' => '',
                        'D' => '',
                        'R' => '',
                        'O' => '',
                        'L' => '',
                        'F' => '',
                        'P' => ''
                    ]
                ];

                $configLangStruct = $this->config->getlanguage();
                $configLang = $configLangStruct['languages'];
                $outLang = [];

                foreach ($configLang as $index => $lang) {
                    $outLang[$index] = strtolower(substr($lang, 0, 2));
                }

                for ($i = 0; $i <= 4; ++$i) {
                    if (!isset($outLang[$i])) {
                        $outLang[$i] = '0';
                    }
                }

                $scaleImpactTypes = [
                    [
                        'anr' => $data['anr'], 'scale' => $scaleId, 'type' => 1, 'isSys' => 1, 'isHidden' => 0,
                        'implicitPosition' => 2, 'label1' => $langs[$outLang[1]]['C'], 'label2' => $langs[$outLang[2]]['C'], 'label3' => $langs[$outLang[3]]['C'], 'label4' => $langs[$outLang[4]]['C'],
                    ],
                    [
                        'anr' => $data['anr'], 'scale' => $scaleId, 'type' => 2, 'isSys' => 1, 'isHidden' => 0,
                        'implicitPosition' => 2, 'label1' => $langs[$outLang[1]]['I'], 'label2' => $langs[$outLang[2]]['I'], 'label3' => $langs[$outLang[3]]['I'], 'label4' => $langs[$outLang[4]]['I'],
                    ],
                    [
                        'anr' => $data['anr'], 'scale' => $scaleId, 'type' => 3, 'isSys' => 1, 'isHidden' => 0,
                        'implicitPosition' => 2, 'label1' => $langs[$outLang[1]]['D'], 'label2' => $langs[$outLang[2]]['D'], 'label3' => $langs[$outLang[3]]['D'], 'label4' => $langs[$outLang[4]]['D'],
                    ],
                    [
                        'anr' => $data['anr'], 'scale' => $scaleId, 'type' => 4, 'isSys' => 1, 'isHidden' => 0,
                        'implicitPosition' => 2, 'label1' => $langs[$outLang[1]]['R'], 'label2' => $langs[$outLang[2]]['R'], 'label3' => $langs[$outLang[3]]['R'], 'label4' => $langs[$outLang[4]]['R'],
                    ],
                    [
                        'anr' => $data['anr'], 'scale' => $scaleId, 'type' => 5, 'isSys' => 1, 'isHidden' => 0,
                        'implicitPosition' => 2, 'label1' => $langs[$outLang[1]]['O'], 'label2' => $langs[$outLang[2]]['O'], 'label3' => $langs[$outLang[3]]['O'], 'label4' => $langs[$outLang[4]]['O'],
                    ],
                    [
                        'anr' => $data['anr'], 'scale' => $scaleId, 'type' => 6, 'isSys' => 1, 'isHidden' => 0,
                        'implicitPosition' => 2, 'label1' => $langs[$outLang[1]]['L'], 'label2' => $langs[$outLang[2]]['L'], 'label3' => $langs[$outLang[3]]['L'], 'label4' => $langs[$outLang[4]]['L'],
                    ],
                    [
                        'anr' => $data['anr'], 'scale' => $scaleId, 'type' => 7, 'isSys' => 1, 'isHidden' => 0,
                        'implicitPosition' => 2, 'label1' => $langs[$outLang[1]]['F'], 'label2' => $langs[$outLang[2]]['F'], 'label3' => $langs[$outLang[3]]['F'], 'label4' => $langs[$outLang[4]]['F'],
                    ],
                    [
                        'anr' => $data['anr'], 'scale' => $scaleId, 'type' => 8, 'isSys' => 1, 'isHidden' => 0,
                        'implicitPosition' => 2, 'label1' => $langs[$outLang[1]]['P'], 'label2' => $langs[$outLang[2]]['P'], 'label3' => $langs[$outLang[3]]['P'], 'label4' => $langs[$outLang[4]]['P'],
                    ]
                ];
                $i = 1;
                $nbScaleImpactTypes = count($scaleImpactTypes);
                foreach ($scaleImpactTypes as $scaleImpactType) {
                    /** @var ScaleImpactTypeService $scaleImpactTypeService */
                    $scaleImpactTypeService = $this->get('scaleImpactTypeService');
                    $scaleImpactTypeService->create($scaleImpactType, ($i == $nbScaleImpactTypes));
                    $i++;
                }
            }

            return $scaleId;
        } else {
            throw new \Exception('Scale is not editable', 412);
        }
    }

    /**
     * Patch
     *
     * @param $id
     * @param $data
     * @throws \Exception
     */
    public function patch($id, $data)
    {
        $anrId = isset($data['anr']) ? $data['anr'] : null;
        if ($this->get('AnrCheckStartedService')->canChange($anrId)) {
            //security
            $this->filterPatchFields($data);

            parent::patch($id, $data);
        } else {
            throw new \Exception('Scale is not editable', 412);
        }
    }

    /**
     * Update
     *
     * @param $id
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    public function update($id, $data)
    {
        $anrId = isset($data['anr']) ? $data['anr'] : null;
        if ($this->get('AnrCheckStartedService')->canChange($anrId)) {
            $result = parent::patch($id, $data);

            return $result;
        } else {
            throw new \Exception('Scale is not editable', 412);
        }
    }
}